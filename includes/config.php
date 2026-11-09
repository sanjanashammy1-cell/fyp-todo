<?php
// Database connection settings. These are the XAMPP defaults.
// On InfinityFree, the host's own database details go here.
$DB_HOST = "localhost";
$DB_NAME = "todolist";
$DB_USER = "root";
$DB_PASS = "";

// PDO is used for every database query because it supports
// prepared statements, which protect against SQL injection (NFR3).
try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS
    );
    // Turn database errors into exceptions so they are not silently ignored.
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Start the PHP session once. The session remembers who is logged in
// (user_id) from one page to the next.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
