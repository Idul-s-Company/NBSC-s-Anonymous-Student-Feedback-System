<?php
session_start();
require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/function.php';

// ── Google OAuth callback handling ──────────────────────────────────────────
if (isset($_GET['code']) && isset($_GET['state']) && $_GET['state'] === ($_SESSION['oauth_state'] ?? '')) {
    $clientId     = GOOGLE_CLIENT_ID;
    $clientSecret = GOOGLE_CLIENT_SECRET;
    $redirectUri  = BASE_URL . '/index.php';

    // Exchange code for token
    $tokenRes = file_get_contents('https://oauth2.googleapis.com/token', false, stream_context_create([
        'http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/x-www-form-urlencoded',
            'content' => http_build_query([
                'code'          => $_GET['code'],
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'redirect_uri'  => $redirectUri,
                'grant_type'    => 'authorization_code',
            ])
        ]
    ]));
    $token = json_decode($tokenRes, true);

    if (isset($token['access_token'])) {
        // Get user info from Google
        $userInfo = json_decode(file_get_contents('https://www.googleapis.com/oauth2/v2/userinfo', false,
            stream_context_create(['http' => ['header' => 'Authorization: Bearer ' . $token['access_token']]])
        ), true);

        $email = $userInfo['email'] ?? '';

        // ── Only accept @nbsc.edu.ph emails ──
        if (!str_ends_with($email, '@nbsc.edu.ph')) {
            $_SESSION['oauth_error'] = 'Access denied. Only @nbsc.edu.ph accounts are allowed.';
            unset($_SESSION['oauth_state']);
            redirect(BASE_URL . '/index.php');
        }

        // ── Check if email exists in the database ──
       // ── Check if email exists in the database ──
$stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
$stmt->execute([$email]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION['oauth_error'] = 'Your account (' . htmlspecialchars($email) . ') was not found in the system. Please contact your administrator.';
    unset($_SESSION['oauth_state']);
    redirect(BASE_URL . '/index.php');
}

// ── Route by role ──
if ($user['role'] === 'admin') {
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['email']      = $email;
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['role']       = 'admin';
    $_SESSION['avatar']     = $userInfo['picture'] ?? '';
    unset($_SESSION['oauth_state']);
    redirect(BASE_URL . '/app/admin/dashboard.php');

} elseif ($user['role'] === 'staff') {
    $_SESSION['user_id']    = $user['user_id'];
    $_SESSION['email']      = $email;
    $_SESSION['first_name'] = $user['first_name'];
    $_SESSION['last_name']  = $user['last_name'];
    $_SESSION['role']       = 'staff';
    $_SESSION['avatar']     = $userInfo['picture'] ?? '';
    unset($_SESSION['oauth_state']);
    redirect(BASE_URL . '/app/manager/dashboard.php');

} elseif ($user['role'] === 'student') {
    $_SESSION['oauth_user_id'] = $user['user_id'];
    $_SESSION['oauth_email']   = $email;
    $_SESSION['oauth_name']    = $user['first_name'] . ' ' . $user['last_name'];
    $_SESSION['oauth_avatar']  = $userInfo['picture'] ?? '';
    unset($_SESSION['oauth_state']);
    redirect(BASE_URL . '/index.php');

} else {
    $_SESSION['oauth_error'] = 'Your account role is not recognized.';
    unset($_SESSION['oauth_state']);
    redirect(BASE_URL . '/index.php');
}
    }
}
// ── Google OAuth logout ──────────────────────────────────────────────────────
if (isset($_GET['logout'])) {
    unset($_SESSION['oauth_user_id'], $_SESSION['oauth_email'], $_SESSION['oauth_name'], $_SESSION['oauth_avatar']);
    redirect(BASE_URL . '/index.php');
}

$isAuthed   = isset($_SESSION['oauth_user_id']);
$authUserId = $_SESSION['oauth_user_id'] ?? null;
$authName   = $_SESSION['oauth_name']    ?? '';
$authEmail  = $_SESSION['oauth_email']   ?? '';
$authAvatar = $_SESSION['oauth_avatar']  ?? '';

// ── Pick up and clear any OAuth error ───────────────────────────────────────
$msg = ''; $submitted = false;
$err = $_SESSION['oauth_error'] ?? '';
unset($_SESSION['oauth_error']);

// ── Submit feedback (requires auth) ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    if (!$isAuthed) {
        $err = 'Please sign in with Google to submit feedback.';
    } else {
        $category     = $_POST['category'] ?? 'general';
        $priority     = $_POST['priority'] ?? 'Low';
        $message      = trim($_POST['message'] ?? '');
        $allowed_cats = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];
        $allowed_pri  = ['Low','Medium','High','Urgent'];

        if (in_array($category, $allowed_cats) && in_array($priority, $allowed_pri) && strlen($message) >= 10 && strlen($message) <= 200) {
            $pdo->prepare("INSERT INTO feedback (user_id, category, priority, message, status) VALUES (?,?,?,?,'pending')")
                ->execute([$authUserId, $category, $priority, $message]);
            $fid = $pdo->lastInsertId();
            $_SESSION['submitted_ids'][] = $fid;

            // Notify admins and staff
            $notifyUsers = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','staff') AND status='active'")->fetchAll();
            foreach ($notifyUsers as $nu) {
                $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                    ->execute([$nu['user_id'], "New $priority Feedback", "A new $priority priority $category feedback was submitted."]);
            }

         logActivity($pdo, 'FEEDBACK_SUBMITTED', "User #$authUserId submitted feedback #$fid ($category)", $authUserId);
            $submitted = true;
        } else {
            $err = 'Please fill all fields. Message must be 10–200 characters.';
        }
    }
}

// ── Post comment (requires auth) ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    if (!$isAuthed) {
        $err = 'Please sign in with Google to comment.';
    } else {
        $fid     = (int)($_POST['feedback_id'] ?? 0);
        $content = trim($_POST['comment_content'] ?? '');
        if ($fid && strlen($content) >= 2) {
            $anonId = generateAnonymousId();
            $pdo->prepare("INSERT INTO comments (feedback_id, encrypted_user_id, anonymous_id, content, status) VALUES (?,?,?,?,'active')")
                ->execute([$fid, 'oauth_' . $authUserId, $anonId, $content]);
            $msg = 'Comment posted anonymously.';
        }
    }
}

// ── Public feed ───────────────────────────────────────────────────────────────
$feedPage = max(1, (int)($_GET['page'] ?? 1));
$perPage  = 8;
$offset   = ($feedPage - 1) * $perPage;
$total    = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pages    = ceil($total / $perPage);

$feedbacks = $pdo->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM comments c WHERE c.feedback_id=f.feedback_id AND c.status='active') AS comment_count,
           r.review_notes, r.reviewed_at, CONCAT(u.first_name,' ',u.last_name) AS reviewed_by
    FROM feedback f
    LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id
    LEFT JOIN users u ON r.reviewed_by=u.user_id
    ORDER BY f.submitted_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();

// ── My submissions ────────────────────────────────────────────────────────────
$mineList = [];
if ($isAuthed) {
    $mineList = $pdo->prepare("
        SELECT f.*,
               (SELECT COUNT(*) FROM comments c WHERE c.feedback_id=f.feedback_id AND c.status='active') AS comment_count,
               r.review_notes
        FROM feedback f
        LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id
        WHERE f.user_id = ?
        ORDER BY f.submitted_at DESC
    ");
    $mineList->execute([$authUserId]);
    $mineList = $mineList->fetchAll();
}

// ── Build Google OAuth URL ────────────────────────────────────────────────────
$state = bin2hex(random_bytes(16));
$_SESSION['oauth_state'] = $state;
$googleAuthUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
    'client_id'     => GOOGLE_CLIENT_ID,
    'redirect_uri'  => BASE_URL . '/index.php',
    'response_type' => 'code',
    'scope'         => 'email profile',
    'state'         => $state,
    'prompt'        => 'select_account',
]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBSC Anonymous Feedback</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    .user-app { min-height:100vh; background:#f0f2f5; }

    /* Nav */
    .user-nav {
      background:#fff; border-bottom:1px solid #e5e7eb;
      padding:0 20px; height:54px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:100;
    }
    .user-nav-brand { display:flex; align-items:center; gap:10px; }
    .nav-logo {
      width:34px; height:34px; border-radius:10px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center; font-size:16px;
    }
    .nav-brand-text { font-size:15px; font-weight:700; letter-spacing:-0.3px; }
    .nav-brand-sub  { font-size:11px; color:var(--text-muted); margin-top:-2px; }
    .nav-user { display:flex; align-items:center; gap:10px; }
    .nav-avatar {
      width:32px; height:32px; border-radius:50%; overflow:hidden;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:#fff;
    }
    .nav-avatar img { width:100%; height:100%; object-fit:cover; }
    .nav-name { font-size:13px; font-weight:600; }
    .nav-role { font-size:11px; color:var(--text-muted); }

    /* Google sign-in btn */
    .btn-google {
      display:inline-flex; align-items:center; gap:8px;
      background:#fff; border:1.5px solid #dadce0; border-radius:8px;
      padding:7px 14px; font-size:13px; font-weight:600; color:#3c4043;
      cursor:pointer; text-decoration:none; transition:box-shadow 0.15s;
      font-family:inherit;
    }
    .btn-google:hover { box-shadow:0 2px 8px rgba(0,0,0,0.12); }
    .btn-google svg { width:16px; height:16px; flex-shrink:0; }

    /* Layout */
    .app-body { max-width:680px; margin:0 auto; padding:20px 16px 60px; }

    /* Submit box */
    .submit-box {
      background:#fff; border-radius:16px;
      box-shadow:0 2px 12px rgba(0,0,0,0.07);
      overflow:hidden; margin-bottom:20px;
    }
    .submit-box-header {
      background:linear-gradient(135deg,#1a56db 0%,#7e3af2 100%);
      padding:20px 24px 16px; color:#fff;
    }
    .submit-box-header h2 { font-size:17px; font-weight:700; margin-bottom:3px; }
    .submit-box-header p  { font-size:12.5px; opacity:0.85; }
    .submit-box-body { padding:20px 24px; }

    /* Auth wall */
    .auth-wall {
      text-align:center; padding:30px 20px;
    }
    .auth-wall .auth-icon { font-size:42px; margin-bottom:12px; }
    .auth-wall h3 { font-size:16px; font-weight:700; margin-bottom:6px; }
    .auth-wall p  { font-size:13px; color:var(--text-muted); margin-bottom:18px; }

    /* Category grid */
    .category-grid {
      display:grid; grid-template-columns:repeat(3,1fr); gap:8px; margin-bottom:16px;
    }
    .cat-btn {
      border:2px solid #e5e7eb; border-radius:10px; padding:10px 8px;
      text-align:center; cursor:pointer; background:#fafafa;
      transition:all 0.15s; font-size:12px; font-weight:500; color:var(--text-muted);
    }
    .cat-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
    .cat-btn.selected { border-color:var(--primary); background:var(--primary-light); color:var(--primary); font-weight:600; }
    .cat-btn .cat-icon { font-size:20px; display:block; margin-bottom:4px; }

    .priority-row { display:flex; gap:8px; margin-bottom:14px; }
    .pri-btn {
      flex:1; padding:8px; border-radius:8px; border:2px solid #e5e7eb;
      background:#fafafa; font-size:12px; font-weight:600; cursor:pointer;
      text-align:center; transition:all 0.15s; color:var(--text-muted);
    }
    .pri-btn.sel-Low    { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
    .pri-btn.sel-Medium { border-color:#d97706; background:#fffbeb; color:#d97706; }
    .pri-btn.sel-High   { border-color:#ea580c; background:#fff7ed; color:#ea580c; }
    .pri-btn.sel-Urgent { border-color:#dc2626; background:#fef2f2; color:#dc2626; }

    .msg-area {
      width:100%; border:2px solid #e5e7eb; border-radius:10px;
      padding:12px; font-family:inherit; font-size:13.5px;
      resize:none; outline:none; transition:border-color 0.15s; min-height:90px;
    }
    .msg-area:focus { border-color:var(--primary); }
    .char-count { font-size:11.5px; color:var(--text-muted); text-align:right; margin-top:4px; }

    .submit-btn {
      width:100%; padding:12px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      color:#fff; border:none; border-radius:10px;
      font-size:14px; font-weight:700; cursor:pointer;
      margin-top:14px; font-family:inherit; transition:opacity 0.15s;
    }
    .submit-btn:hover { opacity:0.92; }

    /* Success */
    .success-box { text-align:center; padding:28px 20px; }
    .success-icon { font-size:48px; margin-bottom:12px; }
    .success-box h3 { font-size:17px; font-weight:700; margin-bottom:6px; }
    .success-box p  { font-size:13px; color:var(--text-muted); }

    /* Feed tabs */
    .feed-tabs { display:flex; gap:4px; margin-bottom:14px; }
    .feed-tab {
      flex:1; padding:9px; border-radius:8px; border:none;
      background:#fff; font-family:inherit; font-size:13px;
      font-weight:500; cursor:pointer; color:var(--text-muted); transition:all 0.15s;
    }
    .feed-tab.active { background:var(--primary); color:#fff; font-weight:600; }

    /* Feedback card */
    .fb-card {
      background:#fff; border-radius:14px;
      box-shadow:0 1px 4px rgba(0,0,0,0.07);
      margin-bottom:12px; overflow:hidden;
    }
    .fb-card-top { padding:16px 18px 12px; }
    .fb-meta { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
    .fb-cat {
      display:flex; align-items:center; gap:5px;
      background:#f3f4f6; padding:3px 10px; border-radius:99px;
      font-size:12px; font-weight:600; color:var(--text-muted);
    }
    .fb-message { font-size:14px; line-height:1.65; color:var(--text); }
    .fb-footer {
      padding:10px 18px; border-top:1px solid #f3f4f6;
      display:flex; align-items:center; justify-content:space-between;
    }
    .fb-time { font-size:12px; color:var(--text-muted); }
    .comment-toggle {
      background:none; border:none; font-size:12.5px; font-weight:600;
      color:var(--primary); cursor:pointer; display:flex; align-items:center; gap:5px;
      font-family:inherit;
    }
    .review-note {
      margin:0 18px 12px;
      background:#f0fdf4; border-left:3px solid #16a34a;
      padding:10px 14px; border-radius:0 8px 8px 0;
      font-size:12.5px; color:#166534;
    }
    .review-note strong { font-size:12px; display:block; margin-bottom:2px; }

    /* Comments */
    .comments-section { padding:0 18px 16px; border-top:1px solid #f3f4f6; display:none; }
    .comments-section.open { display:block; }
    .comment-item { padding:10px 0; border-bottom:1px solid #f3f4f6; display:flex; gap:10px; }
    .comment-item:last-child { border-bottom:none; }
    .comment-avatar {
      width:28px; height:28px; border-radius:50%; flex-shrink:0;
      background:linear-gradient(135deg,#6366f1,#a855f7);
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:700; color:#fff;
    }
    .comment-body { flex:1; }
    .comment-anon  { font-size:11px; color:var(--text-muted); margin-bottom:3px; }
    .comment-text  { font-size:13px; color:var(--text); line-height:1.5; }
    .comment-time  { font-size:11px; color:var(--text-light); margin-top:3px; }

    .comment-form  { display:flex; gap:8px; margin-top:12px; align-items:center; }
    .comment-input {
      flex:1; border:1px solid #e5e7eb; border-radius:20px;
      padding:8px 14px; font-size:13px; font-family:inherit;
      outline:none; transition:border-color 0.15s;
    }
    .comment-input:focus { border-color:var(--primary); }
    .comment-submit {
      background:var(--primary); color:#fff; border:none;
      border-radius:20px; padding:8px 16px; font-size:13px;
      font-weight:600; cursor:pointer; font-family:inherit;
    }

    /* Auth prompt inline */
    .inline-auth {
      display:flex; align-items:center; gap:10px;
      padding:12px 0; font-size:13px; color:var(--text-muted);
    }

    /* My badge */
    .my-badge {
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      color:#fff; font-size:10px; font-weight:700;
      padding:2px 7px; border-radius:99px; letter-spacing:0.05em;
    }

    /* Google OAuth modal */
    .oauth-modal-overlay {
      display:none; position:fixed; inset:0;
      background:rgba(0,0,0,0.45); z-index:999;
      align-items:center; justify-content:center;
    }
    .oauth-modal-overlay.open { display:flex; }
    .oauth-modal {
      background:#fff; border-radius:16px; padding:32px 28px;
      max-width:380px; width:90%; text-align:center;
      box-shadow:0 8px 40px rgba(0,0,0,0.18);
    }
    .oauth-modal h3 { font-size:18px; font-weight:700; margin-bottom:8px; }
    .oauth-modal p  { font-size:13px; color:var(--text-muted); margin-bottom:24px; line-height:1.6; }
    .oauth-modal-close {
      position:absolute; top:14px; right:16px;
      background:none; border:none; font-size:20px;
      cursor:pointer; color:var(--text-muted);
    }

    .feed-empty { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:14px; }
  </style>
</head>
<body>
<div class="user-app">

  <!-- Top Nav -->
  <nav class="user-nav">
    <div class="user-nav-brand">
      <div class="nav-logo">💬</div>
      <div>
        <div class="nav-brand-text">NBSC Feedback</div>
        <div class="nav-brand-sub">Anonymous · Safe · Heard</div>
      </div>
    </div>
    <div class="nav-user">
      <?php if ($isAuthed): ?>
        <div>
          <div class="nav-name"><?= sanitize($authName) ?></div>
          <div class="nav-role"><?= sanitize($authEmail) ?></div>
        </div>
        <div class="nav-avatar">
          <?php if ($authAvatar): ?>
            <img src="<?= sanitize($authAvatar) ?>" alt="avatar">
          <?php else: ?>
            <?= strtoupper(substr($authName, 0, 1)) ?>
          <?php endif; ?>
        </div>
        <a href="?logout=1" class="btn btn-outline btn-sm" style="margin-left:4px;">Logout</a>
      <?php else: ?>
      
      <?php endif; ?>
    </div>
  </nav>

  <div class="app-body">

    <?php if ($err): ?>
      <div class="alert alert-danger" style="border-radius:12px;margin-bottom:16px;"><?= sanitize($err) ?></div>
    <?php endif; ?>
    <?php if ($msg): ?>
      <div class="alert alert-success" style="border-radius:12px;margin-bottom:16px;"><?= sanitize($msg) ?></div>
    <?php endif; ?>

    <!-- Submit Box -->
    <div class="submit-box">
      <div class="submit-box-header">
        <h2>Send Anonymous Feedback 💬</h2>
        <p>Your identity is fully protected. No personal data is linked to your submission.</p>
      </div>
      <div class="submit-box-body">
        <?php if ($submitted): ?>
          <div class="success-box">
            <div class="success-icon">✅</div>
            <h3>Feedback Sent!</h3>
            <p>Your anonymous feedback has been submitted successfully.<br>The admin team will review it shortly.</p>
            <button onclick="resetForm()" class="submit-btn" style="margin-top:16px;max-width:220px;">Send Another</button>
          </div>
        <?php elseif (!$isAuthed): ?>
          <div class="auth-wall">
            <div class="auth-icon">🔒</div>
            <h3>Sign in to Submit Feedback</h3>
            <p>You need to sign in with your Google account to submit feedback. Your submission will remain completely anonymous.</p>
            <a href="<?= $googleAuthUrl ?>" class="btn-google" style="display:inline-flex;">
              <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;">
                <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
              </svg>
              Sign in with Google
            </a>
          </div>
        <?php else: ?>
          <form method="POST" id="feedbackForm">
            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Category</div>
            <div class="category-grid">
              <?php foreach (['academic','facilities','services','faculty','administration','suggestion','complaint','general','other'] as $cat): ?>
                <div class="cat-btn" onclick="selectCat('<?= $cat ?>')" id="cat-<?= $cat ?>">
                  <span class="cat-icon"><?= categoryIcon($cat) ?></span>
                  <?= categoryLabel($cat) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="category" id="category-input" value="general">

            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Priority</div>
            <div class="priority-row">
              <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                <div class="pri-btn" onclick="selectPri('<?= $p ?>')" id="pri-<?= $p ?>"><?= $p ?></div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="priority" id="priority-input" value="Low">

            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Your Message</div>
            <textarea name="message" id="msg-input" class="msg-area" maxlength="200"
              placeholder="Describe your concern clearly. Avoid sharing personal details about yourself or others..."
              required oninput="updateCount()"></textarea>
            <div class="char-count"><span id="char-count">0</span>/200</div>

            <button type="submit" name="submit_feedback" class="submit-btn">Send Anonymously 🔒</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Feed Tabs -->
    <div class="feed-tabs">
      <button class="feed-tab active" onclick="showTab('all',this)">All Feedback</button>
      <button class="feed-tab" onclick="showTab('mine',this)">My Submissions</button>
    </div>

    <!-- All Feedback -->
    <div id="tab-all">
      <?php if (empty($feedbacks)): ?>
        <div class="feed-empty">No feedback yet. Be the first to submit! 🚀</div>
      <?php else: foreach ($feedbacks as $fb): ?>
        <div class="fb-card">
          <div class="fb-card-top">
            <div class="fb-meta">
              <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
              <?= priorityBadge($fb['priority']) ?>
              <?= statusBadge($fb['status']) ?>
              <?php if ($isAuthed && $fb['user_id'] == $authUserId): ?>
                <span class="my-badge">MINE</span>
              <?php endif; ?>
            </div>
            <div class="fb-message"><?= sanitize($fb['message']) ?></div>
          </div>

          <?php if ($fb['status'] === 'resolved' && $fb['review_notes']): ?>
            <div class="review-note">
              <strong>✅ Admin Response</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php elseif ($fb['status'] === 'reviewed' && $fb['review_notes']): ?>
            <div class="review-note" style="background:#eff6ff;border-color:#3b82f6;color:#1e40af;">
              <strong>👀 Under Review</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php endif; ?>

          <div class="fb-footer">
            <span class="fb-time"><?= timeAgo($fb['submitted_at']) ?></span>
            <button class="comment-toggle" onclick="toggleComments(<?= $fb['feedback_id'] ?>)">
              💬 <?= $fb['comment_count'] ?> comment<?= $fb['comment_count'] != 1 ? 's' : '' ?>
            </button>
          </div>

          <!-- Comments -->
          <div class="comments-section" id="comments-<?= $fb['feedback_id'] ?>">
            <?php
              $coms = $pdo->prepare("SELECT * FROM comments WHERE feedback_id=? AND status='active' ORDER BY created_at ASC");
              $coms->execute([$fb['feedback_id']]);
              foreach ($coms->fetchAll() as $c):
                $initials = strtoupper(substr($c['anonymous_id'], 5, 2));
            ?>
              <div class="comment-item">
                <div class="comment-avatar"><?= $initials ?></div>
                <div class="comment-body">
                  <div class="comment-anon"><?= sanitize($c['anonymous_id']) ?></div>
                  <div class="comment-text"><?= sanitize($c['content']) ?></div>
                  <div class="comment-time"><?= timeAgo($c['created_at']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>

            <?php if ($isAuthed): ?>
              <form method="POST" class="comment-form">
                <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
                <input type="text" name="comment_content" class="comment-input" placeholder="Add anonymous comment..." required>
                <button type="submit" name="post_comment" class="comment-submit">Post</button>
              </form>
            <?php else: ?>
              <div class="inline-auth">
                <span>Want to comment?</span>
                <a href="<?= $googleAuthUrl ?>" class="btn-google" style="padding:5px 12px;font-size:12px;">
                  <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:13px;height:13px;">
                    <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                    <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                    <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
                    <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                  </svg>
                  Sign in with Google
                </a>
              </div>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>

      <?php if ($pages > 1): ?>
        <div class="pagination" style="justify-content:center;">
          <?php for ($p = 1; $p <= $pages; $p++): ?>
            <a href="?page=<?= $p ?>" class="page-btn <?= $p == $feedPage ? 'active' : '' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>

    <!-- My Submissions -->
    <div id="tab-mine" style="display:none;">
      <?php if (!$isAuthed): ?>
        <div class="feed-empty">
          <div style="font-size:36px;margin-bottom:10px;">🔒</div>
          <p style="margin-bottom:14px;">Sign in to see your submissions.</p>
          <a href="<?= $googleAuthUrl ?>" class="btn-google" style="display:inline-flex;">
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" style="width:16px;height:16px;">
              <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
              <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
              <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l3.66-2.84z" fill="#FBBC05"/>
              <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
            </svg>
            Sign in with Google
          </a>
        </div>
      <?php elseif (empty($mineList)): ?>
        <div class="feed-empty">
          <div style="font-size:36px;margin-bottom:10px;">📭</div>
          No submissions yet. Submit something above!
        </div>
      <?php else: foreach ($mineList as $fb): ?>
        <div class="fb-card">
          <div class="fb-card-top">
            <div class="fb-meta">
              <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
              <?= priorityBadge($fb['priority']) ?>
              <?= statusBadge($fb['status']) ?>
              <span class="my-badge">MINE</span>
            </div>
            <div class="fb-message"><?= sanitize($fb['message']) ?></div>
          </div>
          <?php if ($fb['review_notes']): ?>
            <div class="review-note">
              <strong>Admin Response</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php endif; ?>
          <div class="fb-footer">
            <span class="fb-time"><?= timeAgo($fb['submitted_at']) ?></span>
            <span class="fb-time">💬 <?= $fb['comment_count'] ?> comment<?= $fb['comment_count'] != 1 ? 's' : '' ?></span>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

  </div>
</div>

<script>
let selectedCat = 'general';
document.getElementById('cat-general').classList.add('selected');
function selectCat(cat) {
  document.querySelectorAll('.cat-btn').forEach(b => b.classList.remove('selected'));
  document.getElementById('cat-' + cat).classList.add('selected');
  document.getElementById('category-input').value = cat;
}

let selectedPri = 'Low';
document.getElementById('pri-Low') && document.getElementById('pri-Low').classList.add('sel-Low');
function selectPri(pri) {
  document.querySelectorAll('.pri-btn').forEach(b => { b.className = 'pri-btn'; });
  document.getElementById('pri-' + pri).classList.add('sel-' + pri);
  document.getElementById('priority-input').value = pri;
}

function updateCount() {
  document.getElementById('char-count').textContent = document.getElementById('msg-input').value.length;
}

function resetForm() {
  window.location.href = '<?= BASE_URL ?>/index.php';
}

function showTab(tab, btn) {
  document.getElementById('tab-all').style.display  = tab === 'all'  ? 'block' : 'none';
  document.getElementById('tab-mine').style.display = tab === 'mine' ? 'block' : 'none';
  document.querySelectorAll('.feed-tab').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
}

function toggleComments(id) {
  document.getElementById('comments-' + id).classList.toggle('open');
}
</script>
</body>
</html>