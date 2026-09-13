<?php
// =========================================================
// reminders.php
// Schedules reminders when a task is created, and fetches
// reminders that are due to be shown right now — both as an
// in-app banner and, if the student saved a phone number, as
// a real SMS via Twilio (sms.php).
// =========================================================

require_once __DIR__ . '/sms.php';

/**
 * Creates two reminders for a task: one a week before it's due,
 * one the day before. Skips any reminder time already in the past.
 */
function schedule_reminders(PDO $pdo, int $task_id, string $due_date): void {
    $due = new DateTime($due_date);
    $now = new DateTime();

    $candidates = [
        (clone $due)->modify('-7 days')->setTime(9, 0),
        (clone $due)->modify('-1 day')->setTime(9, 0),
    ];

    $insert = $pdo->prepare("INSERT INTO reminders (task_id, remind_at) VALUES (?, ?)");
    foreach ($candidates as $remind_at) {
        if ($remind_at > $now) {
            $insert->execute([$task_id, $remind_at->format('Y-m-d H:i:s')]);
        }
    }
}

/**
 * Finds reminders whose time has come, for this student's tasks,
 * that haven't been shown in-app yet. Marks them shown, and — if
 * the student has a phone number saved — also attempts an SMS.
 */
function get_due_reminders(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare(
        "SELECT r.reminder_id, r.sms_sent, t.title, t.module_name, t.due_date, t.task_id
         FROM reminders r
         JOIN tasks t ON t.task_id = r.task_id
         WHERE t.user_id = ? AND t.status = 'active'
           AND r.remind_at <= NOW() AND r.sent = 0"
    );
    $stmt->execute([$user_id]);
    $due = $stmt->fetchAll();

    if (empty($due)) {
        return [];
    }

    // Look up the student's phone number once, not per-reminder.
    $userStmt = $pdo->prepare("SELECT phone_number FROM users WHERE user_id = ?");
    $userStmt->execute([$user_id]);
    $phone = $userStmt->fetchColumn();

    $markShown = $pdo->prepare("UPDATE reminders SET sent = 1 WHERE reminder_id = ?");
    $markSms = $pdo->prepare("UPDATE reminders SET sms_sent = 1 WHERE reminder_id = ?");

    foreach ($due as $r) {
        $markShown->execute([$r['reminder_id']]);

        if (!empty($phone)) {
            $sent = send_sms($phone, build_reminder_message($r));
            if ($sent) {
                $markSms->execute([$r['reminder_id']]);
            }
        }
    }

    return $due;
}
