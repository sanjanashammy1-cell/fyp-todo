<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/recommender.php';
require_once __DIR__ . '/includes/reminders.php';
require_once __DIR__ . '/includes/consistency.php';
require_once __DIR__ . '/includes/clustering.php';
require_once __DIR__ . '/includes/quotes.php';
require_login();

$user_id = current_user_id();

// Load this student's active tasks (only their own, by user_id).
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

// --- Recommender: this user's own weight profile, scored fresh every load ---
$weights = get_user_weights($pdo, $user_id);
// Score and rank every active task (recommender.php). This runs fresh on
// every page load, so the ranking always uses today's date.
$ranked = rank_tasks($tasks, $weights);
// The three highest-scoring tasks are shown in "Today's Focus".
$top3 = array_slice($ranked, 0, 3);

// Log the top pick to build a real historical record (Chapter 6 evidence)
if (!empty($ranked)) {
    log_recommendation($pdo, $user_id, $ranked[0]);
}

// Show due reminders as a banner and send SMS if switched on (FR10, FR11).
$due_reminders = get_due_reminders($pdo, $user_id);
// Warn if two heavy deadlines are close together (FR8).
$cluster_warning = detect_deadline_cluster($tasks);

$just_completed = isset($_GET['completed']);
$encouragement = $just_completed ? get_encouragement() : null;

$total_tasks = count($tasks) + count($completed);
$completion_rate = $total_tasks > 0 ? round((count($completed) / $total_tasks) * 100) : 0;

function importance_tag(int $weight): string {
    if ($weight >= 30) return 'High';
    if ($weight >= 15) return 'Medium';
    return 'Low';
}

// --- Search / filter / sort (applied to the display list only —
// the recommender above always scores the FULL active task set,
// so filtering the view never distorts the actual recommendation) ---
$search = trim($_GET['search'] ?? '');
$filter_module = trim($_GET['module'] ?? '');
$sort_by = $_GET['sort'] ?? 'score';

$all_modules = array_values(array_unique(array_filter(array_column($tasks, 'module_name'))));

$visible = $ranked;
if ($search !== '') {
    $visible = array_filter($visible, fn($r) => stripos($r['task']['title'], $search) !== false);
}
if ($filter_module !== '') {
    $visible = array_filter($visible, fn($r) => ($r['task']['module_name'] ?? '') === $filter_module);
}
$visible = array_values($visible);

if ($sort_by === 'due_date') {
    usort($visible, fn($a, $b) => strcmp($a['task']['due_date'], $b['task']['due_date']));
} elseif ($sort_by === 'progress') {
    usort($visible, fn($a, $b) => $b['task']['progress_percent'] <=> $a['task']['progress_percent']);
}
// 'score' needs no re-sort — $ranked is already score-ordered.


// Data for the consistency chart and streak (FR16).
$week = get_weekly_consistency($pdo, $user_id);
$streak = get_current_streak($week);
$max_count = max(1, max(array_column($week, 'count')));

// Count tasks by importance for the donut chart (a CSS conic-gradient).
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

$phoneStmt = $pdo->prepare("SELECT phone_number FROM users WHERE user_id = ?");
$phoneStmt->execute([$user_id]);
$has_phone = (bool) $phoneStmt->fetchColumn();

$active_page = 'dashboard';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Dashboard — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container" style="max-width:780px;">
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:4px;">
            <h1 style="margin-bottom:0;">Good to see you, <?= htmlspecialchars($_SESSION['user_name']) ?> 👋</h1>
        </div>

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
                ⚠️ <?= $cluster_warning ?>
            </div>
        <?php endif; ?>

        <?php if (!$has_phone): ?>
            <div class="sms-nudge">
                💡 Want deadline reminders by text too? <a href="settings.php">Add your phone number</a>.
            </div>
        <?php endif; ?>

        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1>Your tasks</h1>
            <div>
                <a href="add_task.php" class="btn">+ Add Task</a>
            </div>
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

        <?php if (!empty($top3)): ?>
        <div class="suggested-panel">
            <div class="label">Today's Focus — top <?= count($top3) ?> recommended</div>
            <?php foreach ($top3 as $i => $r): ?>
                <div style="<?= $i > 0 ? 'margin-top:14px; padding-top:14px; border-top:1px solid rgba(14,124,123,0.2);' : 'margin-top:6px;' ?>">
                    <div class="task-title" style="font-size: <?= $i === 0 ? '17px' : '14px' ?>;">
                        #<?= $i + 1 ?> <?= htmlspecialchars($r['task']['title']) ?>
                        <?php if (!empty($r['task']['module_name'])): ?>
                            <span class="muted" style="font-weight:normal;">&mdash; <?= htmlspecialchars($r['task']['module_name']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="muted" style="margin-top:4px; font-size: <?= $i === 0 ? '13px' : '12px' ?>;"><?= htmlspecialchars($r['explanation']) ?></div>
                    <?php if ($i === 0): ?>
                    <div class="muted" style="margin-top:6px; font-size:11px;">
                        Priority score: <?= round($r['scores']['total'] * 100) ?>/100
                        &middot; urgency <?= round($r['scores']['urgency']*100) ?>%
                        &middot; grade weight <?= round($r['scores']['weight_score']*100) ?>%
                        &middot; workload <?= round($r['scores']['workload_score']*100) ?>%
                        &middot; remaining <?= round($r['scores']['progress_gap']*100) ?>%
                    </div>
                    <div class="factor-bars">
                        <div class="factor-bar-row"><span>Urgency</span><div class="factor-bar-track"><div class="factor-bar-fill" style="width:<?= round($r['scores']['urgency']*100) ?>%; background:#E5503F;"></div></div></div>
                        <div class="factor-bar-row"><span>Grade weight</span><div class="factor-bar-track"><div class="factor-bar-fill" style="width:<?= round($r['scores']['weight_score']*100) ?>%; background:#E5A64B;"></div></div></div>
                        <div class="factor-bar-row"><span>Workload</span><div class="factor-bar-track"><div class="factor-bar-fill" style="width:<?= round($r['scores']['workload_score']*100) ?>%; background:#0E7C7B;"></div></div></div>
                        <div class="factor-bar-row"><span>Remaining</span><div class="factor-bar-track"><div class="factor-bar-fill" style="width:<?= round($r['scores']['progress_gap']*100) ?>%; background:#1F3864;"></div></div></div>
                    </div>
                    <?php else: ?>
                    <div class="muted" style="font-size:10px;">Score <?= round($r['scores']['total'] * 100) ?>/100</div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if (empty($tasks)): ?>
            <p class="muted">No active tasks yet — add your first one above.</p>
        <?php else: ?>
        <form method="GET" class="filter-bar">
            <input type="text" name="search" placeholder="Search tasks…" value="<?= htmlspecialchars($search) ?>">
            <select name="module">
                <option value="">All modules</option>
                <?php foreach ($all_modules as $m): ?>
                    <option value="<?= htmlspecialchars($m) ?>" <?= $filter_module === $m ? 'selected' : '' ?>><?= htmlspecialchars($m) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="sort">
                <option value="score" <?= $sort_by === 'score' ? 'selected' : '' ?>>Sort: Priority score</option>
                <option value="due_date" <?= $sort_by === 'due_date' ? 'selected' : '' ?>>Sort: Due date</option>
                <option value="progress" <?= $sort_by === 'progress' ? 'selected' : '' ?>>Sort: Progress</option>
            </select>
            <button type="submit" class="btn btn-secondary" style="margin-top:0;">Apply</button>
            <?php if ($search !== '' || $filter_module !== '' || $sort_by !== 'score'): ?>
                <a href="dashboard.php" class="btn btn-secondary" style="margin-top:0;">Clear</a>
            <?php endif; ?>
        </form>

        <?php if (empty($visible)): ?>
            <p class="muted">No tasks match your search/filter.</p>
        <?php endif; ?>
        <?php endif; ?>

        <?php foreach ($visible as $r): $t = $r['task']; $tag = importance_tag((int)$t['assessment_weight']); ?>
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
                    <a href="assistant.php?task=<?= $t['task_id'] ?>">🤖 Ask AI</a>
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
<!-- AI Study Assistant: floating button + slide-out chat panel -->
<button type="button" class="ai-fab" onclick="document.getElementById('ai-panel').classList.add('open')">🤖 Study Assistant</button>
<div class="ai-panel" id="ai-panel">
    <div class="ai-panel-header">
        <h2>🤖 Study Assistant</h2>
        <button type="button" class="ai-panel-close" aria-label="Close" onclick="document.getElementById('ai-panel').classList.remove('open')">&times;</button>
    </div>
    <?php $chat_tasks = $tasks; $chat_selected = $ranked[0]['task']['task_id'] ?? null; ?>
    <?php include __DIR__ . '/includes/chat_widget.php'; ?>
    <a href="assistant.php" class="ai-panel-full">Open full-page assistant &rarr;</a>
</div>

<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
