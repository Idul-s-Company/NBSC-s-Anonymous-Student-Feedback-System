<?php
session_start();

define('DB_HOST', 'localhost');
define('DB_NAME', 'feedback_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('APP_NAME', 'NBSC FeedbackHub');
define('BASE_URL', 'http://localhost/nbsc/index.php');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}