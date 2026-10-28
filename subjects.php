<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();

// This page has no dedicated "subjects" table — it's built entirely
// by grouping the existing tasks.module_name field, so a module only
// shows up here once the student has actually used it on a task.
$stmt = $pdo->prepare(
    "SELECT
        COALESCE(module_name, 'No module') AS module,
        COUNT(*) AS total_tasks,
        SUM(CASE WHEN status = 'complete' THEN 1 ELSE 0 END) AS completed_tasks,
        AVG(progress_percent) AS avg_progress,
        SUM(CASE WHEN status = 'active' THEN estimated_hours ELSE 0 END) AS remaining_hours
     FROM tasks
     WHERE user_id = ?
     GROUP BY module
     ORDER BY total_tasks DESC"
);
$stmt->execute([$user_id]);
$subjects = $stmt->fetchAll();

$active_page = 'subjects';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Subjects — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container">
        <h1>Your subjects</h1>
        <p class="muted">Built automatically from the module name on each task — add a module when creating a task and it appears here.</p>

        <?php if (empty($subjects)): ?>
            <p class="muted">No tasks yet. Once you add tasks with a module name, they'll be grouped here.</p>
        <?php else: ?>
        <div class="subject-grid">
            <?php foreach ($subjects as $s):
                $rate = $s['total_tasks'] > 0 ? round(($s['completed_tasks'] / $s['total_tasks']) * 100) : 0;
            ?>
                <div class="subject-card">
                    <h3><?= htmlspecialchars($s['module']) ?></h3>
                    <div class="muted" style="font-size:12px;">
                        <?= (int) $s['total_tasks'] ?> task<?= $s['total_tasks'] == 1 ? '' : 's' ?>
                        &middot; <?= (int) $s['completed_tasks'] ?> completed
                    </div>
                    <div class="subject-progress-track">
                        <div class="subject-progress-fill" style="width: <?= $rate ?>%;"></div>
                    </div>
                    <div class="muted" style="font-size:11px; margin-top:6px;">
                        <?= $rate ?>% complete
                        <?php if ($s['remaining_hours'] > 0): ?>
                            &middot; ~<?= round($s['remaining_hours'], 1) ?>h of active work left
                        <?php endif; ?>
                    </div>
                    <a href="dashboard.php?module=<?= urlencode($s['module']) ?>" class="btn btn-secondary" style="margin-top:12px; font-size:12px; padding:6px 14px;">View tasks</a>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
