<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();

// --- Task counts ---
$stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM tasks WHERE user_id = ? GROUP BY status");
$stmt->execute([$user_id]);
$statusCounts = ['active' => 0, 'complete' => 0];
foreach ($stmt->fetchAll() as $row) { $statusCounts[$row['status']] = (int) $row['c']; }
$total_tasks = $statusCounts['active'] + $statusCounts['complete'];
$completion_rate = $total_tasks > 0 ? round(($statusCounts['complete'] / $total_tasks) * 100) : 0;

// --- Overdue active tasks ---
$stmt = $pdo->prepare("SELECT COUNT(*) FROM tasks WHERE user_id = ? AND status = 'active' AND due_date < CURDATE()");
$stmt->execute([$user_id]);
$overdue_count = (int) $stmt->fetchColumn();

// --- Workload by module (active tasks only) ---
$stmt = $pdo->prepare(
    "SELECT COALESCE(module_name, 'No module') AS module, SUM(estimated_hours) AS hrs
     FROM tasks WHERE user_id = ? AND status = 'active'
     GROUP BY module ORDER BY hrs DESC"
);
$stmt->execute([$user_id]);
$byModule = $stmt->fetchAll();

// --- Tasks completed over the last 14 days (from activity_logs, real logged events) ---
$stmt = $pdo->prepare(
    "SELECT DATE(logged_at) AS d, COUNT(*) AS c FROM activity_logs
     WHERE user_id = ? AND action = 'task_completed' AND logged_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY d"
);
$stmt->execute([$user_id]);
$completedByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$last14 = [];
for ($i = 13; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $last14[$d] = (int) ($completedByDay[$d] ?? 0);
}

// --- Average priority score trend (from priority_explanations, real logged recommendations) ---
$stmt = $pdo->prepare(
    "SELECT DATE(calculated_at) AS d, AVG(total_score) AS avg_score FROM priority_explanations
     WHERE user_id = ? AND calculated_at >= DATE_SUB(CURDATE(), INTERVAL 13 DAY)
     GROUP BY d"
);
$stmt->execute([$user_id]);
$scoreByDay = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$scoreTrend = [];
foreach (array_keys($last14) as $d) {
    $scoreTrend[$d] = isset($scoreByDay[$d]) ? round($scoreByDay[$d] * 100) : null;
}

// --- Priority category breakdown (active tasks) ---
$stmt = $pdo->prepare("SELECT assessment_weight FROM tasks WHERE user_id = ? AND status = 'active'");
$stmt->execute([$user_id]);
$priorityCounts = ['High' => 0, 'Medium' => 0, 'Low' => 0];
foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $w) {
    if ($w >= 30) $priorityCounts['High']++;
    elseif ($w >= 15) $priorityCounts['Medium']++;
    else $priorityCounts['Low']++;
}
$active_page = 'analytics';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Analytics — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.4/chart.umd.min.js"></script>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container" style="max-width:900px;">
        <h1>Your analytics</h1>
        <p class="muted">Every chart here is built from data you've actually logged — task records, completion timestamps, and past recommender calculations — not placeholder numbers.</p>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number"><?= $total_tasks ?></div>
                <div class="stat-label">Total tasks</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $statusCounts['complete'] ?></div>
                <div class="stat-label">Completed</div>
            </div>
            <div class="stat-card <?= $overdue_count > 0 ? 'stat-warning' : '' ?>">
                <div class="stat-number"><?= $overdue_count ?></div>
                <div class="stat-label">Overdue</div>
            </div>
            <div class="stat-card">
                <div class="stat-number"><?= $completion_rate ?>%</div>
                <div class="stat-label">Completion rate</div>
            </div>
        </div>

        <div class="charts-row">
            <div class="consistency-card" style="flex:1.4;">
                <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase;">Tasks completed — last 14 days</div>
                <canvas id="completedChart" height="180"></canvas>
            </div>
            <div class="consistency-card" style="flex:1;">
                <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase;">Active tasks by priority</div>
                <canvas id="priorityChart" height="180"></canvas>
            </div>
        </div>

        <div class="charts-row">
            <div class="consistency-card" style="flex:1.4;">
                <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase;">Average recommender score — last 14 days</div>
                <div class="muted" style="font-size:11px; margin-bottom:6px;">Gaps mean no recommendation was calculated that day (you didn't open the dashboard).</div>
                <canvas id="scoreTrendChart" height="180"></canvas>
            </div>
            <div class="consistency-card" style="flex:1;">
                <div class="muted" style="font-size:12px; font-weight:bold; text-transform:uppercase;">Workload by module (hours left)</div>
                <?php if (empty($byModule)): ?>
                    <p class="muted" style="font-size:13px;">No active tasks yet.</p>
                <?php else: ?>
                    <canvas id="moduleChart" height="180"></canvas>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script>
        const gridColor = getComputedStyle(document.documentElement).getPropertyValue('--border').trim();
        const tickColor = getComputedStyle(document.documentElement).getPropertyValue('--grey').trim();
        Chart.defaults.color = tickColor;
        Chart.defaults.font.family = "'Segoe UI', Arial, sans-serif";

        const dayLabels = <?= json_encode(array_map(fn($d) => date('D j', strtotime($d)), array_keys($last14))) ?>;
        const totalCompleted14 = <?= array_sum($last14) ?>;

        function emptyState(canvasId, message) {
            const canvas = document.getElementById(canvasId);
            const wrap = document.createElement('div');
            wrap.className = 'chart-empty-state';
            wrap.textContent = message;
            canvas.replaceWith(wrap);
        }

        if (totalCompleted14 === 0) {
            emptyState('completedChart', 'No completions logged in the last 14 days yet — finish a task to see this fill in.');
        } else {
            new Chart(document.getElementById('completedChart'), {
                type: 'bar',
                data: {
                    labels: dayLabels,
                    datasets: [{ label: 'Tasks completed', data: <?= json_encode(array_values($last14)) ?>, backgroundColor: '#0F766E', borderRadius: 4, maxBarThickness: 28 }]
                },
                options: {
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, ticks: { stepSize: 1 }, grid: { color: gridColor } },
                        x: { grid: { display: false } }
                    }
                }
            });
        }

        <?php if ($priority_total_active = array_sum($priorityCounts)): ?>
        new Chart(document.getElementById('priorityChart'), {
            type: 'doughnut',
            data: {
                labels: ['High', 'Medium', 'Low'],
                datasets: [{ data: <?= json_encode(array_values($priorityCounts)) ?>, backgroundColor: ['#DC2626', '#D97706', '#0F766E'], borderWidth: 2, borderColor: getComputedStyle(document.documentElement).getPropertyValue('--bg-card') }]
            },
            options: { cutout: '62%', plugins: { legend: { position: 'bottom', labels: { padding: 14, boxWidth: 11 } } } }
        });
        <?php else: ?>
        <?php endif; ?>
        <?php if (!$priority_total_active): ?>
        emptyState('priorityChart', 'No active tasks yet — add one to see this chart.');
        <?php endif; ?>

        <?php if (array_filter($scoreTrend)): ?>
        new Chart(document.getElementById('scoreTrendChart'), {
            type: 'line',
            data: {
                labels: dayLabels,
                datasets: [{
                    label: 'Avg. priority score',
                    data: <?= json_encode(array_values($scoreTrend)) ?>,
                    borderColor: '#4338CA',
                    backgroundColor: 'rgba(67,56,202,0.12)',
                    pointBackgroundColor: '#4338CA',
                    spanGaps: true,
                    tension: 0.3,
                    fill: true
                }]
            },
            options: {
                plugins: { legend: { display: false } },
                scales: { y: { min: 0, max: 100, grid: { color: gridColor } }, x: { grid: { display: false } } }
            }
        });
        <?php else: ?>
        emptyState('scoreTrendChart', 'Open your dashboard a few times over the coming days to build up this trend.');
        <?php endif; ?>

        <?php if (!empty($byModule)): ?>
        new Chart(document.getElementById('moduleChart'), {
            type: 'bar',
            data: {
                labels: <?= json_encode(array_column($byModule, 'module')) ?>,
                datasets: [{ label: 'Hours remaining', data: <?= json_encode(array_map('floatval', array_column($byModule, 'hrs'))) ?>, backgroundColor: '#D97706', borderRadius: 4 }]
            },
            options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true, grid: { color: gridColor } }, y: { grid: { display: false } } } }
        });
        <?php endif; ?>
    </script>
<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
