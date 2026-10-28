<?php
require_once __DIR__ . '/includes/auth.php';
require_login();

$user_id = current_user_id();

// Which month are we viewing? Defaults to the current month.
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');
$year = isset($_GET['year']) ? (int) $_GET['year'] : (int) date('Y');

$firstOfMonth = new DateTime("$year-$month-01");
$daysInMonth = (int) $firstOfMonth->format('t');
$startWeekday = (int) $firstOfMonth->format('N'); // 1 (Mon) - 7 (Sun)
$today = date('Y-m-d');

$prevMonth = (clone $firstOfMonth)->modify('-1 month');
$nextMonth = (clone $firstOfMonth)->modify('+1 month');

// Pull every active task with a due date in this month, plus overdue ones
// (shown on their real due date, even if that's in the past) — no new
// table needed, this reuses the same tasks table as the dashboard.
$stmt = $pdo->prepare(
    "SELECT task_id, title, module_name, due_date, assessment_weight, status
     FROM tasks WHERE user_id = ? AND MONTH(due_date) = ? AND YEAR(due_date) = ?
     ORDER BY due_date ASC"
);
$stmt->execute([$user_id, $month, $year]);
$tasksByDay = [];
foreach ($stmt->fetchAll() as $t) {
    $day = (int) date('j', strtotime($t['due_date']));
    $tasksByDay[$day][] = $t;
}

$active_page = 'calendar';
?>
<!DOCTYPE html>
<html lang="en">
<script>(function(){var t=localStorage.getItem("todolist-theme")||"light";document.documentElement.setAttribute("data-theme",t);})();</script>
<head>
    <meta charset="UTF-8">
    <title>Calendar — ClariTask</title>
    <style><?php include __DIR__ . "/css/style.css"; ?></style>
</head>
<body>
<?php include __DIR__ . '/includes/sidebar.php'; ?>

    <div class="container">
        <div style="display:flex; justify-content:space-between; align-items:center;">
            <h1><?= $firstOfMonth->format('F Y') ?></h1>
            <div>
                <a href="?month=<?= $prevMonth->format('n') ?>&year=<?= $prevMonth->format('Y') ?>" class="btn btn-secondary">← Prev</a>
                <a href="?month=<?= date('n') ?>&year=<?= date('Y') ?>" class="btn btn-secondary">Today</a>
                <a href="?month=<?= $nextMonth->format('n') ?>&year=<?= $nextMonth->format('Y') ?>" class="btn btn-secondary">Next →</a>
            </div>
        </div>

        <div class="calendar-grid">
            <?php foreach (['Mon','Tue','Wed','Thu','Fri','Sat','Sun'] as $d): ?>
                <div class="calendar-day-label"><?= $d ?></div>
            <?php endforeach; ?>

            <?php for ($i = 1; $i < $startWeekday; $i++): ?>
                <div class="calendar-cell empty"></div>
            <?php endfor; ?>

            <?php for ($day = 1; $day <= $daysInMonth; $day++):
                $dateStr = sprintf('%04d-%02d-%02d', $year, $month, $day);
                $isToday = $dateStr === $today;
                $weekdayNum = (int) date('N', strtotime($dateStr));
                $isWeekend = $weekdayNum >= 6;
            ?>
                <div class="calendar-cell <?= $isToday ? 'today' : ($isWeekend ? 'weekend' : '') ?>">
                    <div class="calendar-date"><?= $day ?></div>
                    <?php foreach (($tasksByDay[$day] ?? []) as $t):
                        if ($t['status'] === 'complete') continue;
                        $isOverdue = $dateStr < $today;
                        $cls = $isOverdue ? 'overdue' : (($t['assessment_weight'] >= 30) ? 'high' : '');
                    ?>
                        <div class="calendar-task <?= $cls ?>" title="<?= htmlspecialchars($t['title']) ?>">
                            <?= htmlspecialchars($t['title']) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endfor; ?>
        </div>

        <p class="muted" style="margin-top:16px; font-size:12px;">
            <span class="calendar-task" style="display:inline-block;">Normal</span>
            <span class="calendar-task high" style="display:inline-block;">High grade weight (30%+)</span>
            <span class="calendar-task overdue" style="display:inline-block;">Overdue</span>
        </p>
    </div>
<?php include __DIR__ . '/includes/sidebar_end.php'; ?>
</body>
</html>
