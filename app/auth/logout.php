<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

if (isLoggedIn()) {
    logActivity($pdo, $_SESSION['user_id'], 'LOGOUT', ($_SESSION['first_name'] ?? 'User') . ' logged out');
}

session_destroy();
redirect(BASE_URL . '/app/auth/login.php');
