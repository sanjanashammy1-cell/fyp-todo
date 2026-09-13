<?php
// =========================================================
// recommender.php
// The explainable, multi-factor priority recommendation
// algorithm from the project contract (Section 3.5 / Obj. 5).
//
// Every active task is scored fresh from four factors:
//   - urgency          (how close the deadline is)
//   - weight_score      (% of module grade)
//   - workload_score    (hours of work left, relative to time left)
//   - progress_gap      (how much is NOT yet done)
// Scores are combined with fixed weights into one 0-1 total,
// then a plain-language explanation is generated separately
// from whichever factors are actually significant for that task.
// =========================================================

const W_URGENCY  = 0.40;
const W_WEIGHT   = 0.30;
const W_WORKLOAD = 0.15;
const W_PROGRESS = 0.15;

function score_task(array $task): array {
    $due = new DateTime($task['due_date']);
    $today = new DateTime('today');
    $days_remaining = (int) $today->diff($due)->format('%r%a');

    $urgency = max(0.0, min(1.0, 1 - ($days_remaining / 14)));
    $weight_score = min(1.0, ((int) $task['assessment_weight']) / 100);
    $workload_score = min(1.0, ((float) $task['estimated_hours']) / 10);
    $progress_gap = (100 - (int) $task['progress_percent']) / 100;

    $total = ($urgency * W_URGENCY) + ($weight_score * W_WEIGHT)
           + ($workload_score * W_WORKLOAD) + ($progress_gap * W_PROGRESS);

    return [
        'total' => $total,
        'urgency' => $urgency,
        'weight_score' => $weight_score,
        'workload_score' => $workload_score,
        'progress_gap' => $progress_gap,
        'days_remaining' => $days_remaining,
    ];
}

/**
 * Builds the plain-language explanation for one task, using only
 * the factors that are actually significant — not all four every time.
 */
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
 * Scores and ranks every active task, highest priority first.
 * Called fresh on every dashboard load — this is the "adaptive"
 * part: nothing is stored or reused from a previous calculation.
 */
function rank_tasks(array $tasks): array {
    $ranked = [];
    foreach ($tasks as $task) {
        $scores = score_task($task);
        $ranked[] = [
            'task' => $task,
            'scores' => $scores,
            'explanation' => explain_task($task, $scores),
        ];
    }
    usort($ranked, fn($a, $b) => $b['scores']['total'] <=> $a['scores']['total']);
    return $ranked;
}
