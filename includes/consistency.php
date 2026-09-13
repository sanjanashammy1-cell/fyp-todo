<?php
// =========================================================
// consistency.php
// Data behind the "Your consistency" chart: tasks completed
// per day for the last 7 days, plus the current streak.
// =========================================================

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
    $rows = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

    $days = [];
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
