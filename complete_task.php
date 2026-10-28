<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
require_login();

$user_id = current_user_id();
$task_id = $_GET['id'] ?? null;

$titleStmt = $pdo->prepare("SELECT title FROM tasks WHERE task_id = ? AND user_id = ?");
$titleStmt->execute([$task_id, $user_id]);
$title = $titleStmt->fetchColumn();

$stmt = $pdo->prepare(
    "UPDATE tasks SET status = 'complete', completed_at = NOW() WHERE task_id = ? AND user_id = ?"
);
$stmt->execute([$task_id, $user_id]);

if ($title !== false) {
    log_activity($pdo, $user_id, 'task_completed', $title);
}

header("Location: dashboard.php?completed=1");
exit;
