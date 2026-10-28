<?php
// =========================================================
// recommender.php
// The explainable, multi-factor priority recommendation
// algorithm (project contract, Section 3.5 / Objective 5).
//
// UPDATED: weights are no longer fixed constants. Each user
// has their own weight profile (recommender_weights table),
// defaulting to 40/30/15/15 if they haven't customised it.
// This makes the algorithm's tuning explicit and testable —
// a student can change their own weighting and immediately
// see how recommendations shift (useful for Chapter 6's
// sensitivity-analysis testing).
//
// Every time the top recommendation is calculated for a
// dashboard view, it is also logged to priority_explanations,
// building a real historical record of the system's decisions.
// =========================================================

const DEFAULT_WEIGHTS = [
    'urgency'  => 40,
    'weight'   => 30,
    'workload' => 15,
    'progress' => 15,
];

/**
 * Loads this user's own weight profile, or the defaults if
 * they have never customised one.
 */
function get_user_weights(PDO $pdo, int $user_id): array {
    $stmt = $pdo->prepare("SELECT w_urgency, w_weight, w_workload, w_progress FROM recommender_weights WHERE user_id = ?");
    $stmt->execute([$user_id]);
    $row = $stmt->fetch();

    if (!$row) {
        return DEFAULT_WEIGHTS;
    }
    return [
        'urgency'  => (int) $row['w_urgency'],
        'weight'   => (int) $row['w_weight'],
        'workload' => (int) $row['w_workload'],
        'progress' => (int) $row['w_progress'],
    ];
}

/**
 * Saves a user's chosen weight profile. Caller is responsible
 * for checking the four values sum to 100 before calling this.
 */
function save_user_weights(PDO $pdo, int $user_id, array $weights): void {
    $stmt = $pdo->prepare(
        "INSERT INTO recommender_weights (user_id, w_urgency, w_weight, w_workload, w_progress)
         VALUES (?, ?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE w_urgency = VALUES(w_urgency), w_weight = VALUES(w_weight),
             w_workload = VALUES(w_workload), w_progress = VALUES(w_progress)"
    );
    $stmt->execute([$user_id, $weights['urgency'], $weights['weight'], $weights['workload'], $weights['progress']]);
}

function score_task(array $task, array $weights): array {
    $due = new DateTime($task['due_date']);
    $today = new DateTime('today');
    $days_remaining = (int) $today->diff($due)->format('%r%a');

    $urgency = max(0.0, min(1.0, 1 - ($days_remaining / 14)));
    $weight_score = min(1.0, ((int) $task['assessment_weight']) / 100);
    $workload_score = min(1.0, ((float) $task['estimated_hours']) / 10);
    $progress_gap = (100 - (int) $task['progress_percent']) / 100;

    $total = ($urgency * $weights['urgency'] / 100)
           + ($weight_score * $weights['weight'] / 100)
           + ($workload_score * $weights['workload'] / 100)
           + ($progress_gap * $weights['progress'] / 100);

    return [
        'total' => $total,
        'urgency' => $urgency,
        'weight_score' => $weight_score,
        'workload_score' => $workload_score,
        'progress_gap' => $progress_gap,
        'days_remaining' => $days_remaining,
    ];
}

function explain_task(array $task, array $scores): string {
    $reasons = [];

    if ($scores['days_remaining'] <= 1) {
        $reasons[] = "it's due " . ($scores['days_remaining'] <= 0 ? "today" : "tomorrow");
    } elseif ($scores['days_remaining'] <= 3) {
        $reasons[] = "it's due in {$scores['days_remaining']} days";
    }

    if ((int) $task['assessment_weight'] >= 25) {
        $reasons[] = "it's worth {$task['assessment_weight']}% of your grade";
    }

    if ((int) $task['progress_percent'] < 30) {
        $reasons[] = "you've only completed {$task['progress_percent']}% of it";
    }

    if ((float) $task['estimated_hours'] >= 5) {
        $reasons[] = "it still needs about {$task['estimated_hours']} hours of work";
    }

    if (empty($reasons)) {
        return "Recommended based on its overall priority score compared to your other tasks.";
    }

    $last = array_pop($reasons);
    $text = empty($reasons) ? $last : implode(', ', $reasons) . ', and ' . $last;
    return "Recommended because " . $text . ".";
}

/**
 * Scores and ranks every active task, highest priority first,
 * using this user's own weight profile. Called fresh on every
 * dashboard load — the "adaptive" part: nothing is stored or
 * reused from a previous calculation for the ranking itself.
 */
function rank_tasks(array $tasks, array $weights): array {
    $ranked = [];
    foreach ($tasks as $task) {
        $scores = score_task($task, $weights);
        $ranked[] = [
            'task' => $task,
            'scores' => $scores,
            'explanation' => explain_task($task, $scores),
        ];
    }
    usort($ranked, fn($a, $b) => $b['scores']['total'] <=> $a['scores']['total']);
    return $ranked;
}

/**
 * Logs the top recommendation to priority_explanations, building
 * a permanent, queryable history of what the system decided and
 * why — used directly as evaluation evidence in Chapter 6.
 */
function log_recommendation(PDO $pdo, int $user_id, array $top): void {
    $stmt = $pdo->prepare(
        "INSERT INTO priority_explanations
         (user_id, task_id, urgency_score, weight_score, workload_score, progress_score, total_score, explanation)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $s = $top['scores'];
    $stmt->execute([
        $user_id, $top['task']['task_id'],
        $s['urgency'], $s['weight_score'], $s['workload_score'], $s['progress_gap'],
        $s['total'], $top['explanation'],
    ]);
}
