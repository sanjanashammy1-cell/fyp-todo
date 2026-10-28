<?php
// =========================================================
// activity_log.php
// Records key user actions with a timestamp. This is a
// standard audit-trail pattern, and the recorded timestamps
// also strengthen the consistency evaluation in Chapter 6 —
// e.g. showing exactly when tasks were added vs completed,
// not just the daily counts already shown on the dashboard.
// =========================================================

function log_activity(PDO $pdo, int $user_id, string $action, ?string $details = null): void {
    $stmt = $pdo->prepare(
        "INSERT INTO activity_logs (user_id, action, details) VALUES (?, ?, ?)"
    );
    $stmt->execute([$user_id, $action, $details]);
}
