<?php
function redirect($url) {
    header("Location: $url");
    exit;
}

function requireRole($role) {
    if (!isset($_SESSION)) session_start();

    if ($role === 'student') {
        if (!isset($_SESSION['oauth_user_id'])) {
            redirect(BASE_URL . '/index.php');
        }
    } else {
        // admin or staff
        if (!isset($_SESSION['role']) || $_SESSION['role'] !== $role) {
            redirect(BASE_URL . '/index.php');
        }
    }
}

function currentUser() {
    return [
        'first_name' => $_SESSION['first_name'] ?? '',
        'last_name'  => $_SESSION['last_name']  ?? '',
        'role'       => $_SESSION['role']        ?? '',
        'email'      => $_SESSION['email']       ?? $_SESSION['oauth_email'] ?? '',
    ];
}

function sanitize($val) {
    return htmlspecialchars(trim($val), ENT_QUOTES, 'UTF-8');
}

function logActivity($pdo, $userId = null, $action = '', $description = '') {
    $ip = $_SERVER['HTTP_X_FORWARDED_FOR'] 
       ?? $_SERVER['REMOTE_ADDR'] 
       ?? '0.0.0.0';
    if ($ip === '::1') $ip = '127.0.0.1';

    // Guard against FK violation if the user_id doesn't exist in users table
    if ($userId !== null) {
        $check = $pdo->prepare("SELECT 1 FROM users WHERE user_id = ?");
        $check->execute([$userId]);
        if (!$check->fetchColumn()) {
            $userId = null;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO activity_logs (user_id, action, description, ip_address) VALUES (?,?,?,?)");
    $stmt->execute([$userId, $action, $description, $ip]);
}

function generateAnonymousId() {
    return 'anon_' . substr(md5(uniqid(rand(), true)), 0, 12);
}

function timeAgo($datetime) {
    $now  = new DateTime();
    $past = new DateTime($datetime);
    $diff = $now->getTimestamp() - $past->getTimestamp();

    if ($diff < 60)                         return 'Just now';
    if ($diff < 3600)   { $m = floor($diff/60);    return $m . ' minute' . ($m!=1?'s':'') . ' ago'; }
    if ($diff < 86400)  { $h = floor($diff/3600);  return $h . ' hour'   . ($h!=1?'s':'') . ' ago'; }
    if ($diff < 604800) { $d = floor($diff/86400); return $d . ' day'    . ($d!=1?'s':'') . ' ago'; }
    if ($diff < 2592000){ $w = floor($diff/604800);return $w . ' week'   . ($w!=1?'s':'') . ' ago'; }

    return $past->format('M j, Y');
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

function categoryLabel($c) {
    return ucfirst(str_replace('_', ' ', $c));
}

function getUnreadNotifCount($pdo, $userId) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$userId]);
    return $stmt->fetchColumn();
}

function categoryIcon($cat) {
    $icons = [
        'academic'       => '📚',
        'facilities'     => '🏫',
        'services'       => '🛎️',
        'faculty'        => '👨‍🏫',
        'administration' => '🏛️',
        'suggestion'     => '💡',
        'complaint'      => '⚠️',
        'general'        => '💬',
        'other'          => '📝',
    ];
    return $icons[$cat] ?? '💬';
}