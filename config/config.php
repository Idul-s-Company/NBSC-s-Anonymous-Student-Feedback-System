<?php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'working_schema');

define('GOOGLE_CLIENT_ID',     '248717550278-8t7887a72ldoipenmov3o4na56p5j70k.apps.googleusercontent.com');
define('GOOGLE_CLIENT_SECRET', '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');


define('BASE_URL', 'http://localhost/NBSC-s-Anonymous-Student-Feedback-System');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    die(json_encode(['error' => 'Database connection failed: ' . $e->getMessage()]));
}
