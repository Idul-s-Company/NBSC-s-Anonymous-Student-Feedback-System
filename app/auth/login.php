<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';

if (isLoggedIn()) {
    $role = $_SESSION['role'];
    if ($role === 'admin')  redirect(BASE_URL . '/app/admin/dashboard.php');
    if ($role === 'staff')  redirect(BASE_URL . '/app/manager/dashboard.php');
    redirect(BASE_URL . '/app/user/index.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($email && $password) {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user && password_verify($password, trim($user['password']))) {
            $_SESSION['user_id']    = $user['user_id'];
            $_SESSION['school_id']  = $user['school_id'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['last_name']  = $user['last_name'];
            $_SESSION['email']      = $user['email'];
            $_SESSION['role']       = $user['role'];
            $_SESSION['department'] = $user['department'];

            logActivity($pdo, $user['user_id'], 'LOGIN', $user['first_name'] . ' logged into the system');

            if ($user['role'] === 'admin')  redirect(BASE_URL . '/app/admin/dashboard.php');
            if ($user['role'] === 'staff')  redirect(BASE_URL . '/app/manager/dashboard.php');
            redirect(BASE_URL . '/app/user/index.php');
        } else {
            $error = 'Invalid email or password, or your account is inactive.';
        }
    } else {
        $error = 'Please fill in all fields.';
    }
}

renderHeader('Login');
?>
<div class="auth-page">
  <div class="auth-box">
    <div class="auth-logo">
      <div class="logo-circle">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
        </svg>
      </div>
      <h1>NBSC Feedback</h1>
      <p>Anonymous Student Feedback System</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger"><?= sanitize($error) ?></div>
    <?php endif; ?>

    <form method="POST">
      <div class="form-group">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" placeholder="you@nbsc.edu.ph"
               value="<?= sanitize($_POST['email'] ?? '') ?>" required>
      </div>
      <div class="form-group">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" placeholder="••••••••" required>
      </div>
      <button type="submit" class="btn btn-primary" style="width:100%;justify-content:center;padding:10px;">
        Sign In
      </button>
    </form>

    <p class="text-muted text-center mt-3" style="font-size:12px;">
      Feedback is submitted anonymously. Your identity is protected.
    </p>
  </div>
</div>
<?php renderFooter(); ?>
