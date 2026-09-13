<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/recommender.php';
require_once __DIR__ . '/includes/reminders.php';
require_once __DIR__ . '/includes/consistency.php';
require_once __DIR__ . '/includes/clustering.php';
require_once __DIR__ . '/includes/quotes.php';
require_login();

$user_id = current_user_id();

$stmt = $pdo->prepare(
    "SELECT * FROM tasks WHERE user_id = ? AND status = 'active' ORDER BY due_date ASC"
);
$stmt->execute([$user_id]);
$tasks = $stmt->fetchAll();

$stmt2 = $pdo->prepare(
    "SELECT * FROM tasks WHERE user_id = ? AND status = 'complete' ORDER BY due_date DESC"
);
$stmt2->execute([$user_id]);
$completed = $stmt2->fetchAll();

// --- The recommender: scored fresh every load (the "adaptive" part) ---
$ranked = rank_tasks($tasks);
$top = $ranked[0] ?? null;

// --- Reminders due right now (in-app banner + attempts SMS if phone saved) ---
$due_reminders = get_due_reminders($pdo, $user_id);

// --- Deadline clustering / overload warning ---
$cluster_warning = detect_deadline_cluster($tasks);

// --- Just-completed congratulations banner ---
$just_completed = isset($_GET['completed']);
$encouragement = $just_completed ? get_encouragement() : null;

// --- Progress overview ---
$total_tasks = count($tasks) + count($completed);
$completion_rate = $total_tasks > 0 ? round((count($completed) / $total_tasks) * 100) : 0;

function importance_tag(int $weight): string {
    if ($weight >= 30) return 'High';
    if ($weight >= 15) return 'Medium';
    return 'Low';
}

// --- Consistency chart data ---
$week = get_weekly_consistency($pdo, $user_id);
$streak = get_current_streak($week);
$max_count = max(1, max(array_column($week, 'count')));

// --- Priority breakdown pie chart ---
$priority_counts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($tasks as $t) {
    $priority_counts[importance_tag((int) $t['assessment_weight'])]++;
}
$priority_total = max(1, array_sum($priority_counts));
$priority_colors = ['High' => '#E5503F', 'Medium' => '#E5A64B', 'Low' => '#0E7C7B'];
$stops = [];
$running = 0;
foreach ($priority_counts as $label => $count) {
    $pct = round(($count / $priority_total) * 100, 1);
    $from = $running;
    $running += $pct;
    if ($count > 0) {
        $stops[] = "{$priority_colors[$label]} {$from}% {$running}%";
    }
}
$conic_gradient = empty($stops) ? "#E6E6E6 0% 100%" : implode(', ', $stops);

// --- Check if phone number is saved (for the SMS nudge) ---
$phoneStmt = $pdo->prepare("SELECT phone_number FROM users WHERE user_id = ?");
$phoneStmt->execute([$user_id]);
$has_phone = (bool) $phoneStmt->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard — To-Do List</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body>
    <div class="topbar">
        <div class="brand">TO-DO LIST</div>
        <div>
            <span class="muted" style="color:#cfd8ea;">Hi, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container" style="max-width:780px;">

        <?php if ($just_completed): ?>
            <div class="congrats-banner">
                <div class="congrats-emoji">🎉</div>
                <div>
                    <div class="congrats-title">Task complete!</div>
                    <div class="congrats-quote"><?= htmlspecialchars($encouragement) ?></div>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!empty($due_reminders)): ?>
            <?php foreach ($due_reminders as $r): ?>
                <div class="error" style="background:#FFF6DD; color:#8a6d00;">
                    🔔 Reminder: "<?= htmlspecialchars($r['title']) ?>" is due <?= htmlspecialchars($r['due_date']) ?>.
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if ($cluster_warning): ?>
            <div class="error" style="background:#FCE9E4; color:#A6431F;">
                ⚠️ <?= $cluster_warning /* already escaped inside detect_deadline_cluster */ ?>
            </div>
        <?php endif; ?>

        <?php if (!$has_phone): ?>
            <div class="sms-nudge">
                💡 Want deadline reminders by text too? <a href="edit_profile.php">Add your phone number</a>.
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Your tasks</h1>
            <a href="add_task.php" class="btn">+ Add Task</a>
        </div>

        <?php if ($total_tasks > 0): ?>
        <div class="progress-overview">
            <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">
                Overall completion
            </div>
            <div class="progress-bar-track">
                <div class="progress-bar-fill" style="width: <?= $completion_rate ?>%;"></div>
            </div>
            <div class="muted" style="font-size:12px; margin-top:4px;">
                <?= count($completed) ?> of <?= $total_tasks ?> tasks complete (<?= $completion_rate ?>%)
            </div>
        </div>
        <?php endif; ?>

        <div class="charts-row">
            <div class="consistency-card" style="flex:1.3;">
                <div class="consistency-header">
                    <div>
                        <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px;">Your consistency</div>
                        <div class="muted" style="font-size:12px;">Tasks completed per day, last 7 days</div>
                    </div>
                    <?php if ($streak > 0): ?>
                        <span class="streak-badge">🔥 <?= $streak ?>-day streak</span>
                    <?php else: ?>
                        <span class="muted" style="font-size:12px;">No active streak yet</span>
                    <?php endif; ?>
                </div>
                <div class="chart-row">
                    <?php foreach ($week as $day): ?>
                        <div class="chart-col">
                            <?php if ($day['count'] > 0): ?>
                                <div class="chart-count"><?= $day['count'] ?></div>
                            <?php endif; ?>
                            <div class="chart-bar <?= $day['count'] > 0 ? 'has-count' : '' ?> <?= $day['is_today'] ? 'is-today' : '' ?>"
                                 style="height: <?= max(6, ($day['count'] / $max_count) * 70) ?>px;"></div>
                            <div class="chart-label"><?= $day['label'] ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="consistency-card" style="flex:1; display:flex; flex-direction:column; align-items:center;">
                <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase; letter-spacing:0.5px; align-self:flex-start;">
                    Active tasks by priority
                </div>
                <div class="donut" style="background: conic-gradient(<?= $conic_gradient ?>);">
                    <div class="donut-hole"><?= count($tasks) ?><span>active</span></div>
                </div>
                <div class="donut-legend">
                    <?php foreach ($priority_counts as $label => $count): if ($count === 0) continue; ?>
                        <div class="legend-item">
                            <span class="legend-dot" style="background: <?= $priority_colors[$label] ?>;"></span>
                            <?= $label ?> (<?= $count ?>)
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <?php if ($top): ?>
        <div class="suggested-panel">
            <div class="label">Suggested next task</div>
            <div class="task-title">
                <?= htmlspecialchars($top['task']['title']) ?>
                <?php if (!empty($top['task']['module_name'])): ?>
                    <span class="muted" style="font-weight:normal;">&mdash; <?= htmlspecialchars($top['task']['module_name']) ?></span>
                <?php endif; ?>
            </div>
            <div class="muted" style="margin-top:6px;"><?= htmlspecialchars($top['explanation']) ?></div>
            <div class="muted" style="margin-top:6px; font-size:11px;">
                Priority score: <?= round($top['scores']['total'] * 100) ?>/100
                (urgency <?= round($top['scores']['urgency']*100) ?>%,
                grade weight <?= round($top['scores']['weight_score']*100) ?>%,
                workload <?= round($top['scores']['workload_score']*100) ?>%,
                remaining <?= round($top['scores']['progress_gap']*100) ?>%)
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($tasks)): ?>
            <p class="muted">No active tasks yet — add your first one above.</p>
        <?php endif; ?>

        <?php foreach ($ranked as $r): $t = $r['task']; $tag = importance_tag((int)$t['assessment_weight']); ?>
            <div class="task-row">
                <div>
                    <strong><?= htmlspecialchars($t['title']) ?></strong>
                    <span class="importance-tag importance-<?= $tag ?>"><?= $t['assessment_weight'] ?>%</span>
                    <div class="task-meta">
                        <?php if (!empty($t['module_name'])): ?>
                            <?= htmlspecialchars($t['module_name']) ?> &middot;
                        <?php endif; ?>
                        Due <?= htmlspecialchars($t['due_date']) ?>
                        &middot; <?= $t['progress_percent'] ?>% done
                        &middot; ~<?= $t['estimated_hours'] ?>h left
                        &middot; score <?= round($r['scores']['total'] * 100) ?>
                    </div>
                </div>
                <div class="task-actions">
                    <a href="complete_task.php?id=<?= $t['task_id'] ?>">Mark complete</a>
                    <a href="edit_task.php?id=<?= $t['task_id'] ?>">Edit</a>
                    <a href="delete_task.php?id=<?= $t['task_id'] ?>" class="delete" onclick="return confirm('Delete this task?');">Delete</a>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (!empty($completed)): ?>
            <h2 style="margin-top:32px; font-size:16px;">Completed</h2>
            <?php foreach ($completed as $t): ?>
                <div class="task-row complete">
                    <div>
                        <?= htmlspecialchars($t['title']) ?>
                        <?php if (!empty($t['module_name'])): ?>
                            <span class="muted">&mdash; <?= htmlspecialchars($t['module_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="task-meta">Was due <?= htmlspecialchars($t['due_date']) ?></div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>
