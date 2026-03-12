<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

// Log activity before destroying session
if (isset($_SESSION['user_id'])) {
    // Admin or staff logout
    $name = ($_SESSION['first_name'] ?? 'User') . ' ' . ($_SESSION['last_name'] ?? '');
    logActivity($pdo, 'LOGOUT', sanitize($name) . ' logged out', $_SESSION['user_id']);
} elseif (isset($_SESSION['oauth_user_id'])) {
    // Student OAuth logout
    logActivity($pdo, 'LOGOUT', ($_SESSION['oauth_name'] ?? 'Student') . ' logged out', $_SESSION['oauth_user_id']);
}

// Clear all session data
session_unset();
session_destroy();

redirect(BASE_URL . '/index.php');