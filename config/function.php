<?php

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function redirect($url) {
    header("Location: $url");
    exit;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/index.php');
    }
}

function requireRole($role) {
    requireLogin();
    if ($_SESSION['role'] !== $role) {
        redirect(BASE_URL . '/index.php');
    }
}

function renderHeader($pageTitle = '') {
    $title = $pageTitle ? $pageTitle . ' | ' . APP_NAME : APP_NAME;
    include __DIR__ . '/../includes/header.php';
}

function renderFooter() {
    include __DIR__ . '/../includes/footer.php';
}