<?php
// auth.php: login check used at the top of every private page.
require_once __DIR__ . '/config.php';

// Sends visitors who are not logged in back to the login page,
// so nobody can open a private page by typing its address.
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

// Returns the logged-in student's id. Every task query uses this id,
// so a student can only ever see and change their own tasks.
function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
