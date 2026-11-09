<?php
// =========================================================
// quotes.php
// Short original encouragement lines shown after completing
// a task. Written for this project, not quoted from anyone.
// =========================================================

function get_encouragement(): string {
    $lines = [
        "One task down. That's real progress, not just a checkbox.",
        "Nice work — future-you just got a little less stressed.",
        "That's done. Let the next one wait its turn.",
        "Small steps add up. You just took one.",
        "Progress, not perfection — and that counts.",
        "You showed up for this. That's the hard part.",
        "Done is better than perfect. Well done.",
        "Every finished task is one less thing pulling at your attention.",
        "That's off your plate now. Take a breath before the next one.",
        "Consistency beats intensity — and you're building it.",
    ];
    return $lines[array_rand($lines)];
}
