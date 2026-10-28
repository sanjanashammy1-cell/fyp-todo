<?php
// =========================================================
// send_reminders.php
// Background job: sends due SMS reminders to all students,
// even when they do not open ClariTask.
// Run it every 15 minutes, either:
//  - on a laptop: Windows Task Scheduler runs
//      C:\xampp\php\php.exe C:\xampp\htdocs\todolist\send_reminders.php
//  - online: a free timer service (e.g. cron-job.org) opens
//      https://YOUR-SITE/send_reminders.php?key=CRON_SECRET
// =========================================================
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/reminders.php';

$from_command_line = (php_sapi_name() === 'cli');
if (!$from_command_line && !hash_equals(CRON_SECRET, (string) ($_GET['key'] ?? ''))) {
    http_response_code(403);
    exit("Not allowed.");
}

$sent = send_due_sms_for_all_users($pdo);
echo date('Y-m-d H:i') . " - SMS sent: $sent\n";
