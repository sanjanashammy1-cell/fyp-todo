<?php
// =========================================================
// clustering.php
// Detects "deadline clustering": several high-weight tasks
// falling close together (Section 2.1 procrastination-by-
// course literature). Reuses existing task data — no new
// database fields needed.
// =========================================================

const CLUSTER_WINDOW_DAYS = 3;
const CLUSTER_MIN_WEIGHT  = 20;

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

        if ($gap <= CLUSTER_WINDOW_DAYS && $from_today >= -1) {
            $names = htmlspecialchars($significant[$i]['title']) . '" and "' . htmlspecialchars($significant[$i + 1]['title']);
            return "Heads up: \"{$names}\" are both high-weight and due within {$gap} day(s) of each other. Consider starting the earlier one now to avoid a crunch.";
        }
    }
    return null;
}
