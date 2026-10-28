<?php
// =========================================================
// tests/run_tests.php
// Automated tests for ClariTask's recommender and deadline-
// clustering logic. No framework needed.
//
// HOW TO RUN (VS Code terminal, inside the todolist folder):
//     php tests/run_tests.php
//
// Each test prints PASS or FAIL. At the end it prints a total.
// These tests use plain PHP arrays, so they do NOT touch the
// database and they cannot change your real data.
// =========================================================

if (PHP_SAPI !== 'cli' && !defined('RUN_FROM_BROWSER')) {
    exit('Run this file from the terminal: php tests/run_tests.php');
}

require_once __DIR__ . '/../includes/recommender.php';
require_once __DIR__ . '/../includes/clustering.php';

$passed = 0;
$failed = 0;

// ---------- small helper functions ----------

// Prints PASS or FAIL for one check and keeps the count.
function check(string $name, bool $ok, string $detail = ''): void {
    global $passed, $failed;
    if ($ok) {
        $passed++;
        echo "  PASS  $name\n";
    } else {
        $failed++;
        echo "  FAIL  $name" . ($detail !== '' ? "   ($detail)" : '') . "\n";
    }
}

// Builds a fake task. $days = days from today (negative = overdue).
function makeTask(int $days, int $weight, float $hours, int $progress, string $title = 'Task'): array {
    $sign = $days >= 0 ? '+' : '';
    return [
        'title'             => $title,
        'due_date'          => (new DateTime('today'))->modify("{$sign}{$days} days")->format('Y-m-d'),
        'assessment_weight' => $weight,
        'estimated_hours'   => $hours,
        'progress_percent'  => $progress,
    ];
}

// The score shown on the dashboard (0 to 100, rounded).
function points(array $task, array $weights = DEFAULT_WEIGHTS): int {
    return (int) round(score_task($task, $weights)['total'] * 100);
}

// Compares two decimal numbers safely.
function same(float $a, float $b): bool {
    return abs($a - $b) < 0.000001;
}

// ---------- GROUP 1: worked examples (thesis Section 4.5.3) ----------
echo "\nGROUP 1: Worked examples\n";

$taskA = makeTask(2, 40, 8, 20, 'A');
$taskB = makeTask(14, 20, 2, 80, 'B');

check('Default weights add up to 100', array_sum(DEFAULT_WEIGHTS) === 100);
check('Test A scores 70', points($taskA) === 70, 'got ' . points($taskA));
check('Test B scores 12', points($taskB) === 12, 'got ' . points($taskB));

$taskA80 = makeTask(2, 40, 8, 80, 'A');
check('Test A with progress 80 scores 61', points($taskA80) === 61, 'got ' . points($taskA80));

$urgentWeights = ['urgency' => 70, 'weight' => 10, 'workload' => 10, 'progress' => 10];
check('Weights 70/10/10/10: Test A scores 80', points($taskA, $urgentWeights) === 80, 'got ' . points($taskA, $urgentWeights));
check('Weights 70/10/10/10: Test B scores 6', points($taskB, $urgentWeights) === 6, 'got ' . points($taskB, $urgentWeights));

// ---------- GROUP 2: boundaries (extreme values) ----------
echo "\nGROUP 2: Boundaries\n";

$today   = score_task(makeTask(0, 20, 2, 0), DEFAULT_WEIGHTS);
$overdue = score_task(makeTask(-3, 20, 2, 0), DEFAULT_WEIGHTS);
$day14   = score_task(makeTask(14, 20, 2, 0), DEFAULT_WEIGHTS);
$day30   = score_task(makeTask(30, 20, 2, 0), DEFAULT_WEIGHTS);

check('Due today: 0 days left, urgency 1.0', $today['days_remaining'] === 0 && same($today['urgency'], 1.0));
check('Overdue by 3 days: urgency stays at 1.0 (not above)', $overdue['days_remaining'] === -3 && same($overdue['urgency'], 1.0));
check('Due in 14 days: urgency 0.0', same($day14['urgency'], 0.0));
check('Due in 30 days: urgency stays at 0.0 (not negative)', same($day30['urgency'], 0.0));

check('Grade weight 150% is capped at 1.0', same(score_task(makeTask(5, 150, 2, 0), DEFAULT_WEIGHTS)['weight_score'], 1.0));
check('Grade weight 0% gives 0.0', same(score_task(makeTask(5, 0, 2, 0), DEFAULT_WEIGHTS)['weight_score'], 0.0));
check('25 hours of work is capped at 1.0', same(score_task(makeTask(5, 20, 25, 0), DEFAULT_WEIGHTS)['workload_score'], 1.0));
check('Progress 100% gives progress gap 0.0', same(score_task(makeTask(5, 20, 2, 100), DEFAULT_WEIGHTS)['progress_gap'], 0.0));
check('Progress 0% gives progress gap 1.0', same(score_task(makeTask(5, 20, 2, 0), DEFAULT_WEIGHTS)['progress_gap'], 1.0));

check('Highest possible score is 100', points(makeTask(0, 100, 10, 0)) === 100, 'got ' . points(makeTask(0, 100, 10, 0)));
check('Lowest possible score is 0', points(makeTask(14, 0, 0, 100)) === 0, 'got ' . points(makeTask(14, 0, 0, 100)));

// ---------- GROUP 3: ranking ----------
echo "\nGROUP 3: Ranking\n";

$ranked = rank_tasks([$taskB, $taskA], DEFAULT_WEIGHTS);
check('Task A is ranked above Task B', $ranked[0]['task']['title'] === 'A' && $ranked[1]['task']['title'] === 'B');

$many = [
    makeTask(1, 10, 1, 90, 'T1'), makeTask(10, 60, 9, 0, 'T2'), makeTask(3, 30, 5, 50, 'T3'),
    makeTask(7, 15, 3, 10, 'T4'), makeTask(0, 50, 6, 20, 'T5'),
];
$sorted = rank_tasks($many, DEFAULT_WEIGHTS);
$inOrder = true;
for ($i = 0; $i < count($sorted) - 1; $i++) {
    if ($sorted[$i]['scores']['total'] < $sorted[$i + 1]['scores']['total']) { $inOrder = false; }
}
check('Five tasks are sorted from highest score to lowest', $inOrder);

// Changing the weights can change which task comes first.
$p = makeTask(1, 10, 1, 90, 'P');   // due tomorrow, small, almost finished
$q = makeTask(10, 60, 9, 0, 'Q');   // due later, big, not started
$defaultFirst = rank_tasks([$p, $q], DEFAULT_WEIGHTS)[0]['task']['title'];
$urgentFirst  = rank_tasks([$p, $q], ['urgency' => 85, 'weight' => 5, 'workload' => 5, 'progress' => 5])[0]['task']['title'];
check('Default weights put Q first', $defaultFirst === 'Q', "got $defaultFirst");
check('Deadline-heavy weights put P first', $urgentFirst === 'P', "got $urgentFirst");

// ---------- GROUP 4: explanations ----------
echo "\nGROUP 4: Plain-language explanations\n";

function explain(array $task): string {
    return explain_task($task, score_task($task, DEFAULT_WEIGHTS));
}

check('Due tomorrow is mentioned',        str_contains(explain(makeTask(1, 10, 2, 50)), 'due tomorrow'));
check('Due today is mentioned',           str_contains(explain(makeTask(0, 10, 2, 50)), 'due today'));
check('Due in 3 days is mentioned',       str_contains(explain(makeTask(3, 10, 2, 50)), 'due in 3 days'));
check('High grade weight is mentioned',   str_contains(explain(makeTask(10, 40, 2, 50)), '40% of your grade'));
check('Low grade weight is NOT mentioned', !str_contains(explain(makeTask(10, 10, 2, 50)), 'of your grade'));
check('Low progress is mentioned',        str_contains(explain(makeTask(10, 10, 2, 10)), "only completed 10%"));
check('Good progress is NOT mentioned',   !str_contains(explain(makeTask(10, 10, 2, 50)), 'only completed'));
check('Large workload is mentioned',      str_contains(explain(makeTask(10, 10, 6, 50)), '6 hours of work'));
check('Small workload is NOT mentioned',  !str_contains(explain(makeTask(10, 10, 2, 50)), 'hours of work'));
check('No strong reason gives the general message',
      str_starts_with(explain(makeTask(10, 10, 2, 50)), 'Recommended based on its overall priority score'));
check('Strong reasons start with "Recommended because"',
      str_starts_with(explain(makeTask(1, 40, 6, 0)), 'Recommended because'));
check('Several reasons are joined with ", and"', str_contains(explain(makeTask(1, 40, 6, 0)), ', and '));

// ---------- GROUP 5: deadline clustering warning ----------
echo "\nGROUP 5: Deadline clustering warning\n";

check('Two heavy tasks 2 days apart give a warning',
      detect_deadline_cluster([makeTask(3, 25, 2, 0, 'X'), makeTask(5, 25, 2, 0, 'Y')]) !== null);
check('Two light tasks (weight 10) give no warning',
      detect_deadline_cluster([makeTask(3, 10, 2, 0), makeTask(5, 10, 2, 0)]) === null);
check('Only one heavy task gives no warning',
      detect_deadline_cluster([makeTask(3, 25, 2, 0), makeTask(5, 10, 2, 0)]) === null);
check('Heavy tasks 10 days apart give no warning',
      detect_deadline_cluster([makeTask(3, 25, 2, 0), makeTask(13, 25, 2, 0)]) === null);
check('Gap of exactly 3 days gives a warning',
      detect_deadline_cluster([makeTask(2, 25, 2, 0), makeTask(5, 25, 2, 0)]) !== null);
check('Gap of 4 days gives no warning',
      detect_deadline_cluster([makeTask(2, 25, 2, 0), makeTask(6, 25, 2, 0)]) === null);
check('Weight exactly 20 counts as heavy',
      detect_deadline_cluster([makeTask(2, 20, 2, 0), makeTask(3, 20, 2, 0)]) !== null);
check('Weight 19 does not count as heavy',
      detect_deadline_cluster([makeTask(2, 19, 2, 0), makeTask(3, 19, 2, 0)]) === null);
check('Old overdue tasks (5 days late) give no warning',
      detect_deadline_cluster([makeTask(-5, 25, 2, 0), makeTask(-4, 25, 2, 0)]) === null);

$unsafe = detect_deadline_cluster([makeTask(3, 25, 2, 0, '<script>x</script>'), makeTask(4, 25, 2, 0, 'Y')]);
check('Task titles in the warning are made safe (no raw <script>)',
      $unsafe !== null && !str_contains($unsafe, '<script>'));

// ---------- summary ----------
$total = $passed + $failed;
echo "\n=========================================\n";
echo "RESULT: $passed of $total tests passed";
echo $failed === 0 ? "  -- ALL PASSED\n" : "  -- $failed FAILED\n";
echo "=========================================\n";
if (PHP_SAPI === 'cli') {
    exit($failed === 0 ? 0 : 1);
}
