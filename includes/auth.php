<?php
require_once __DIR__ . '/config.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
    // Prevent the browser from serving a cached copy of this page after
    // logout — without this, pressing "Back" can show stale authenticated
    // content instead of correctly bouncing to the login page.
    header("Cache-Control: no-store, no-cache, must-revalidate");
    header("Pragma: no-cache");
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
