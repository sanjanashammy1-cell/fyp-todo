<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/activity_log.php';
require_login();

$user_id = current_user_id();
$task_id = $_GET['id'] ?? $_POST['task_id'] ?? null;

$stmt = $pdo->prepare("SELECT * FROM tasks WHERE task_id = ? AND user_id = ?");
$stmt->execute([$task_id, $user_id]);
$task = $stmt->fetch();

if (!$task) {
    header("Location: dashboard.php");
    exit;
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $module_name = trim($_POST['module_name'] ?? '');
    $due_date = $_POST['due_date'] ?? '';
    $assessment_weight = (int) ($_POST['assessment_weight'] ?? 20);
    $estimated_hours = (float) ($_POST['estimated_hours'] ?? 2);
    $progress_percent = (int) ($_POST['progress_percent'] ?? 0);

    if ($title === '' || $due_date === '') {
        $error = "Please fill in the title and due date.";
    } elseif ($assessment_weight < 0 || $assessment_weight > 100) {
        $error = "Grade weighting must be between 0 and 100.";
    } elseif ($estimated_hours < 0) {
        $error = "Estimated hours can't be negative.";
    } elseif ($progress_percent < 0 || $progress_percent > 100) {
        $error = "Progress must be between 0 and 100.";
    } else {
        $update = $pdo->prepare(
            "UPDATE tasks SET title=?, module_name=?, due_date=?, assessment_weight=?, estimated_hours=?, progress_percent=?
             WHERE task_id = ? AND user_id = ?"
        );
        $update->execute([$title, $module_name ?: null, $due_date, $assessment_weight, $estimated_hours, $progress_percent, $task_id, $user_id]);
        log_activity($pdo, $user_id, 'task_edited', $title);
        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Edit Task — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
    <div class="container">
        <h1>Edit task</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="task_id" value="<?= $task['task_id'] ?>">

            <label>Task title</label>
            <input type="text" name="title" value="<?= htmlspecialchars($task['title']) ?>" required>

            <label>Module (optional)</label>
            <input type="text" name="module_name" value="<?= htmlspecialchars($task['module_name'] ?? '') ?>" placeholder="e.g. Database Systems">

            <label>Due date</label>
            <input type="date" name="due_date" value="<?= htmlspecialchars($task['due_date']) ?>" required>

            <label>How much of your module grade is this worth? (%)</label>
            <input type="number" name="assessment_weight" min="0" max="100" value="<?= htmlspecialchars($task['assessment_weight']) ?>" required>

            <label>Roughly how many hours of work are left?</label>
            <input type="number" name="estimated_hours" min="0" step="0.5" value="<?= htmlspecialchars($task['estimated_hours']) ?>" required>

            <label>How much have you already done? (%)</label>
            <input type="number" name="progress_percent" min="0" max="100" value="<?= htmlspecialchars($task['progress_percent']) ?>" required>

            <button type="submit" class="btn">Save changes</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
