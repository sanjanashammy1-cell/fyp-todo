<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();
$task_id = $_GET['id'] ?? null;

$stmt = $pdo->prepare(
    "UPDATE tasks SET status = 'complete', completed_at = NOW() WHERE task_id = ? AND user_id = ?"
);
$stmt->execute([$task_id, $user_id]);

// Redirect with a flag so the dashboard knows to show the
// congratulations banner + encouragement quote just this once.
header("Location: dashboard.php?completed=1");
exit;
