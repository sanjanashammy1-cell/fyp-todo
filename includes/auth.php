<?php
// =========================================================
// auth.php
// Small helper functions used by every protected page.
// =========================================================

require_once __DIR__ . '/config.php';

function require_login() {
    if (!isset($_SESSION['user_id'])) {
        header("Location: login.php");
        exit;
    }
}

function current_user_id() {
    return $_SESSION['user_id'] ?? null;
}
