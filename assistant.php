<?php
// =========================================================
// assistant.php
// Full-page AI Study Assistant (the AI component).
// Uses the shared sidebar and style, and the reusable
// chat box in includes/chat_widget.php.
// =========================================================
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();

// Only active tasks - those are the ones a student needs a plan for
$stmt = $pdo->prepare(
    "SELECT * FROM tasks WHERE user_id = ? AND status = 'active' ORDER BY due_date ASC"
);
$stmt->execute([$user_id]);
$chat_tasks = $stmt->fetchAll();
$chat_selected = isset($_GET['task']) ? (int) $_GET['task'] : null;

$active_page = 'assistant';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Study Assistant — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container" style="max-width:780px;">
        <h1>🤖 Study Assistant</h1>
        <p class="muted">Ask how to start or plan a task. The assistant reads the task's deadline, grade weight, estimated hours and progress, then suggests realistic next steps.</p>
        <?php include __DIR__ . '/includes/chat_widget.php'; ?>
    </div>

<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
