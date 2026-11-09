<?php
// =========================================================
// reminders.php
// In-app and SMS reminders (FR10, FR11).
//
// - schedule_reminders(): saves two reminder times when a task
//   is added (default 7 days and 1 day before, at 09:00).
// - get_due_reminders(): runs when the dashboard opens; shows due
//   reminders as a banner and sends an SMS if the student wants one.
// - send_due_sms_for_all_users(): used by send_reminders.php, the
//   background job that sends SMS even if nobody opens the app.
//
// Each student controls the timing and can switch SMS off
// (notification_preferences table), because too many or badly
// timed reminders can make students rely on them (Nobbe et al., 2024).
// =========================================================

require_once __DIR__ . '/sms.php';
// Use Copenhagen time in PHP, because the online database server may
// use a different time zone (Section 4.6).
date_default_timezone_set('Europe/Copenhagen'); // match the MySQL clock

// Used when a student has not saved their own reminder settings.
const DEFAULT_PREFS = [
    'sms_enabled' => true,
    'first_reminder_days' => 7,
    'second_reminder_days' => 1,
];

// Reads this student's reminder settings, or the defaults.
function get_notification_preferences(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare("SELECT sms_enabled, first_reminder_days, second_reminder_days FROM notification_preferences WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return DEFAULT_PREFS;
    }
    return [
        'sms_enabled' => (bool) $row['sms_enabled'],
        'first_reminder_days' => (int) $row['first_reminder_days'],
        'second_reminder_days' => (int) $row['second_reminder_days'],
    ];
}

// Saves the settings. ON DUPLICATE KEY UPDATE inserts a new row the first
// time and updates the same row after that (one row per student).
function save_notification_preferences(PDO $pdo, int $user_id, bool $sms_enabled, int $first_days, int $second_days): void {
    $stmt = $pdo->prepare(
        "INSERT INTO notification_preferences (user_id, sms_enabled, first_reminder_days, second_reminder_days)
         VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE sms_enabled = VALUES(sms_enabled),
             first_reminder_days = VALUES(first_reminder_days), second_reminder_days = VALUES(second_reminder_days)"
    );
    $stmt->execute([$user_id, $sms_enabled ? 1 : 0, $first_days, $second_days]);
}

/**
 * Creates two reminders for a task, timed according to this
 * student's own preferences (defaulting to 7 days and 1 day
 * before the deadline). Skips any reminder time already past.
 */
function schedule_reminders(PDO $pdo, int $task_id, string $due_date, int $user_id): void {
    $prefs = get_notification_preferences($pdo, $user_id);
    $due = new DateTime($due_date);
    $now = new DateTime();

    // The two reminder times, both at 09:00 on the chosen days before the deadline.
    // clone is used so the original due date is not changed.
    $candidates = [
        (clone $due)->modify("-{$prefs['first_reminder_days']} days")->setTime(9, 0),
        (clone $due)->modify("-{$prefs['second_reminder_days']} days")->setTime(9, 0),
    ];

    $insert = $pdo->prepare("INSERT INTO reminders (task_id, remind_at) VALUES (?, ?)");
    $catch_up_added = false;
    foreach ($candidates as $remind_at) {
        if ($remind_at > $now) {
            $insert->execute([$task_id, $remind_at->format('Y-m-d H:i:s')]);
        } elseif (!$catch_up_added && $due > $now) {
            // The reminder time has already passed, but the deadline has not.
            // Remind the student straight away instead of skipping it
            // (for example, a task added the day before it is due).
            $insert->execute([$task_id, $now->format('Y-m-d H:i:s')]);
            $catch_up_added = true;
        }
    }
}

/**
 * Finds reminders whose time has come, for this student's tasks,
 * not yet shown in-app. Marks them shown, and — only if the
 * student has SMS enabled and saved a phone number — attempts
 * an SMS too.
 */
function get_due_reminders(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare(
        "SELECT r.reminder_id, r.sms_sent, t.title, t.module_name, t.due_date, t.task_id
         FROM reminders r
         JOIN tasks t ON t.task_id = r.task_id
         WHERE t.user_id = ? AND t.status = 'active'
           AND r.remind_at <= ? AND r.sent = 0"
    );
    // Use PHP's Copenhagen time, not the database clock, so the
    // online server's time zone does not delay reminders.
    $stmt->execute([$user_id, date('Y-m-d H:i:s')]);
    $due = $stmt->fetchAll();

    if (empty($due)) {
        return [];
    }

    $prefs = get_notification_preferences($pdo, $user_id);

    $userStmt = $pdo->prepare("SELECT phone_number FROM users WHERE user_id = ?");
    $userStmt->execute([$user_id]);
    $phone = $userStmt->fetchColumn();

    // sent = shown in the app; sms_sent = SMS delivered. Two separate flags mean
    // the banner and the SMS are each only done once.
    $markShown = $pdo->prepare("UPDATE reminders SET sent = 1 WHERE reminder_id = ?");
    $markSms = $pdo->prepare("UPDATE reminders SET sms_sent = 1 WHERE reminder_id = ?");

    foreach ($due as $r) {
        $markShown->execute([$r['reminder_id']]);

        // Only send the SMS if the background job has not sent it already.
        if ($prefs['sms_enabled'] && !empty($phone) && (int) $r['sms_sent'] === 0) {
            $sent = send_sms($phone, build_reminder_message($r));
            if ($sent) {
                $markSms->execute([$r['reminder_id']]);
            }
        }
    }

    return $due;
}

/**
 * Background job: sends SMS for every due reminder of every student,
 * even if the student has not opened the dashboard. It does NOT mark
 * the reminder as shown in-app, so the student still sees the banner
 * the next time they open ClariTask. Returns how many SMS were sent.
 */
function send_due_sms_for_all_users(PDO $pdo): int {
    $stmt = $pdo->prepare(
        "SELECT r.reminder_id, t.title, t.module_name, t.due_date, t.user_id, u.phone_number
         FROM reminders r
         JOIN tasks t ON t.task_id = r.task_id
         JOIN users u ON u.user_id = t.user_id
         WHERE t.status = 'active' AND r.remind_at <= ? AND r.sms_sent = 0
           AND t.due_date >= ?
           AND u.phone_number IS NOT NULL AND u.phone_number <> ''"
    );
    $stmt->execute([date('Y-m-d H:i:s'), date('Y-m-d')]);
    $markSms = $pdo->prepare("UPDATE reminders SET sms_sent = 1 WHERE reminder_id = ?");
    $count = 0;
    foreach ($stmt->fetchAll() as $r) {
        $prefs = get_notification_preferences($pdo, (int) $r['user_id']);
        if (!$prefs['sms_enabled']) {
            continue;
        }
        if (send_sms($r['phone_number'], build_reminder_message($r))) {
            $markSms->execute([$r['reminder_id']]);
            $count++;
        }
    }
    return $count;
}
