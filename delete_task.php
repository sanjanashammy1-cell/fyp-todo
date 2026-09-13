<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();
$task_id = $_GET['id'] ?? null;

$stmt = $pdo->prepare("DELETE FROM tasks WHERE task_id = ? AND user_id = ?");
$stmt->execute([$task_id, $user_id]);

header("Location: dashboard.php");
exit;
