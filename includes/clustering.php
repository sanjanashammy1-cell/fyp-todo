<?php
// =========================================================
// clustering.php
// Detects "deadline clustering": several high-weight tasks
// falling close together, which the literature on academic
// procrastination and overload identifies as a key risk
// point for students (Section 2.1 of the thesis).
//
// This reuses data the recommender already has — no new
// database fields needed.
// =========================================================

const CLUSTER_WINDOW_DAYS = 3;      // "close together" = within this many days
const CLUSTER_MIN_WEIGHT  = 20;     // only count tasks worth at least this % of a grade

/**
 * Returns a warning message if 2+ significant tasks are due within
 * CLUSTER_WINDOW_DAYS of each other, or null if there's no cluster.
 */
function detect_deadline_cluster(array $tasks): ?string {
    $significant = array_filter($tasks, fn($t) => (int) $t['assessment_weight'] >= CLUSTER_MIN_WEIGHT);
    if (count($significant) < 2) {
        return null;
    }

    usort($significant, fn($a, $b) => strcmp($a['due_date'], $b['due_date']));

    $today = new DateTime('today');
    for ($i = 0; $i < count($significant) - 1; $i++) {
        $d1 = new DateTime($significant[$i]['due_date']);
        $d2 = new DateTime($significant[$i + 1]['due_date']);
        $gap = (int) $d1->diff($d2)->format('%a');
        $from_today = (int) $today->diff($d1)->format('%r%a');

        // Only warn about clusters that are still upcoming, not past.
        if ($gap <= CLUSTER_WINDOW_DAYS && $from_today >= -1) {
            $names = htmlspecialchars($significant[$i]['title']) . '" and "' . htmlspecialchars($significant[$i + 1]['title']);
            return "Heads up: \"{$names}\" are both high-weight and due within {$gap} day(s) of each other. Consider starting the earlier one now to avoid a crunch.";
        }
    }
    return null;
}
