<?php
function redirect($url) {
    header("Location: $url");
    exit;
}

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect(BASE_URL . '/app/auth/login.php');
    }
}

function requireRole($roles) {
    requireLogin();
    if (!in_array($_SESSION['role'], (array)$roles)) {
        redirect(BASE_URL . '/app/auth/login.php');
    }
}

function currentUser() {
    return $_SESSION ?? [];
}

function sanitize($val) {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function logActivity($pdo, $userId, $action, $description) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $action, $description, $ip]);
}

function generateFeedbackCode() {
    return 'NBSC-' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 6));
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $then = new DateTime($datetime);
    $diff = $now->diff($then);
    if ($diff->days == 0) {
        if ($diff->h == 0) return $diff->i . ' min ago';
        return $diff->h . ' hr ago';
    }
    if ($diff->days == 1) return 'Yesterday';
    if ($diff->days < 30) return $diff->days . ' days ago';
    return $then->format('M d, Y');
}

function priorityBadge($p) {
    $map = ['Low'=>'badge-low','Medium'=>'badge-medium','High'=>'badge-high','Urgent'=>'badge-urgent'];
    $cls = $map[$p] ?? 'badge-low';
    return "<span class='badge $cls'>$p</span>";
}

function statusBadge($s) {
    $map = ['pending'=>'badge-pending','reviewed'=>'badge-reviewed','resolved'=>'badge-resolved'];
    $cls = $map[$s] ?? 'badge-pending';
    return "<span class='badge $cls'>" . ucfirst($s) . "</span>";
}

function roleBadge($r) {
    $map = ['admin'=>'badge-admin','staff'=>'badge-staff','student'=>'badge-student'];
    $cls = $map[$r] ?? 'badge-student';
    return "<span class='badge $cls'>" . ucfirst($r) . "</span>";
}

function getUnreadNotifCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}
