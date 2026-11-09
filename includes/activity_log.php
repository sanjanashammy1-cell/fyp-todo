<?php
// =========================================================
// activity_log.php
// Saves key actions (register, login, task added, edited,
// completed) with a time stamp in activity_logs (FR17).
// This gives a traceable record that test T18 checks.
// =========================================================

function log_activity(PDO $pdo, int $user_id, string $action, ?string $details = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $details]);
}
