<?php
// =========================================================
// config.php
// One shared database connection, used by every page.
// =========================================================

$DB_HOST = "localhost";
$DB_NAME = "todolist";
$DB_USER = "root";
$DB_PASS = "";   // default XAMPP has no password

try {
    $pdo = new PDO(
        "mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4",
        $DB_USER,
        $DB_PASS
    );
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
