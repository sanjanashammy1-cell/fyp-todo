<?php
// =========================================================
// consistency.php
// Data behind the "Your consistency" chart: tasks completed
// per day for the last 7 days, plus the current streak.
// =========================================================

// Counts completed tasks for each of the last 7 days (FR16, need N4).
// Progress feedback helps students feel in control (Macan et al., 1990).
function get_weekly_consistency(PDO $pdo, int $user_id): array {
    $today = new DateTime('today');
    $start = (clone $today)->modify('-6 days');

    $stmt = $pdo->prepare(
        "SELECT DATE(completed_at) AS d, COUNT(*) AS c
         FROM tasks
         WHERE user_id = ? AND completed_at IS NOT NULL AND completed_at >= ?
         GROUP BY DATE(completed_at)"
    );
    $stmt->execute([$user_id, $start->format('Y-m-d 00:00:00')]);
    // FETCH_KEY_PAIR gives an array like ['2026-10-08' => 2].
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $days = [];
    // Fill in all 7 days, using 0 for days with no completed task.
    for ($i = 0; $i < 7; $i++) {
        $d = (clone $start)->modify("+$i days");
        $key = $d->format('Y-m-d');
        $days[] = [
            'label' => $d->format('D'),
            'count' => (int) ($rows[$key] ?? 0),
            'is_today' => $key === $today->format('Y-m-d'),
        ];
    }
    return $days;
}

// Streak = how many days in a row, ending today, had at least one completed task.
function get_current_streak(array $days): int {
    $streak = 0;
    for ($i = count($days) - 1; $i >= 0; $i--) {
        if ($days[$i]['count'] > 0) {
            $streak++;
        } else {
            break;
        }
    }
    return $streak;
}
