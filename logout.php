<?php
require_once __DIR__ . '/includes/config.php';
// Empty the session and destroy it, so the student is fully logged out.
$_SESSION = [];
session_destroy();
header("Location: login.php");
exit;
