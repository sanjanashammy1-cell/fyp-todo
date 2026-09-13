<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/reminders.php';
require_login();

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
        $stmt = $pdo->prepare(
            "INSERT INTO tasks (user_id, title, module_name, due_date, assessment_weight, estimated_hours, progress_percent)
             VALUES (?, ?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([
            current_user_id(), $title, $module_name ?: null, $due_date,
            $assessment_weight, $estimated_hours, $progress_percent
        ]);

        schedule_reminders($pdo, (int) $pdo->lastInsertId(), $due_date);

        header("Location: dashboard.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Add Task — To-Do List</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="container">
        <h1>Add a task</h1>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <label>Task title</label>
            <input type="text" name="title" required>

            <label>Module (optional)</label>
            <input type="text" name="module_name" placeholder="e.g. Database Systems">

            <label>Due date</label>
            <input type="date" name="due_date" required>

            <label>How much of your module grade is this worth? (%)</label>
            <input type="number" name="assessment_weight" min="0" max="100" value="20" required>

            <label>Roughly how many hours of work are left?</label>
            <input type="number" name="estimated_hours" min="0" step="0.5" value="2" required>

            <label>How much have you already done? (%)</label>
            <input type="number" name="progress_percent" min="0" max="100" value="0" required>

            <p class="muted">These answers feed the priority recommender — a rough guess is fine.</p>

            <button type="submit" class="btn">Save</button>
            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
        </form>
    </div>
</body>
</html>
