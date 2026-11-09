<?php
// =========================================================
// clustering.php
// Deadline-clash warning (FR12, needs N2 and N8).
// Warns the student when two heavy tasks (worth 20% or more)
// are due within 3 days of each other, because overlapping
// deadlines were the main source of stress in the survey.
// It only reads the task list that the dashboard already has,
// so no extra database table is needed.
// =========================================================

// Two heavy deadlines within this many days count as a clash.
const CLUSTER_WINDOW_DAYS = 3;
// Only tasks worth at least this percent of the grade are checked.
const CLUSTER_MIN_WEIGHT  = 20;

function detect_deadline_cluster(array $tasks): ?string {
    // Keep only the heavy tasks.
    $significant = array_filter($tasks, fn($t) => (int) $t['assessment_weight'] >= CLUSTER_MIN_WEIGHT);
    // A clash needs at least two heavy tasks.
    if (count($significant) < 2) {
        return null;
    }

    // Sort by due date so neighbouring tasks in the list are the closest in time.
    usort($significant, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

    $today = new DateTime('today');
    for ($i = 0; $i < count($significant) - 1; $i++) {
        $d1 = new DateTime($significant[$i]['due_date']);
        $d2 = new DateTime($significant[$i + 1]['due_date']);
        $gap = (int) $d1->diff($d2)->format('%a');
        $from_today = (int) $today->diff($d1)->format('%r%a');

        // Warn if two neighbouring heavy tasks are close together and not long overdue.
        // htmlspecialchars() stops a task title from injecting HTML (XSS).
        if ($gap <= CLUSTER_WINDOW_DAYS && $from_today >= -1) {
            $names = htmlspecialchars($significant[$i]['title']) . '" and "' . htmlspecialchars($significant[$i + 1]['title']);
            return "Heads up: \"{$names}\" are both high-weight and due within {$gap} day(s) of each other. Consider starting the earlier one now to avoid a crunch.";
        }
    }
    return null;
}
