<?php
// =========================================================
// reminders.php
// Schedules reminders when a task is created, and fetches
// reminders due to be shown right now — as an in-app banner
// and, if the student has SMS enabled, as a real text via
// Twilio (sms.php).
//
// UPDATED: reminder timing and whether SMS is sent at all
// now come from notification_preferences, not fixed values,
// so each student can control their own reminder schedule.
// =========================================================

require_once __DIR__ . '/sms.php';
date_default_timezone_set('Europe/Copenhagen'); // match the MySQL clock

const DEFAULT_PREFS = [
    'sms_enabled' => true,
    'first_reminder_days' => 7,
    'second_reminder_days' => 1,
];

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
