<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/function.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'admin')  redirect(BASE_URL . '/app/admin/dashboard.php');
    if ($role === 'staff')  redirect(BASE_URL . '/app/manager/dashboard.php');
    redirect(BASE_URL . '/app/user/index.php');
}
redirect(BASE_URL . '/app/auth/login.php');
