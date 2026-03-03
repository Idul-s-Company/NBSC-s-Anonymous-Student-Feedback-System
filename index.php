<?php
require_once 'config/config.php';
require_once 'config/functions.php';
require_once 'includes/activity-logger.php';

// If already logged in, send to their dashboard
if (isLoggedIn()) {
    switch ($_SESSION['role']) {
        case 'admin':   redirect(BASE_URL . '/app/admin/dashboard.php');   break;
        case 'staff':   redirect(BASE_URL . '/app/staff/dashboard.php');   break;
        case 'student': redirect(BASE_URL . '/app/student/dashboard.php'); break;
    }
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? AND status = 'active'");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        // Successful login
        $_SESSION['user_id']    = $user['user_id'];
        $_SESSION['email']      = $user['email'];
        $_SESSION['role']       = $user['role'];
        $_SESSION['first_name'] = $user['first_name'];
        $_SESSION['last_name']  = $user['last_name'];

        logActivity($pdo, $user['user_id'], 'LOGIN', $user['email'] . ' logged in');

        switch ($user['role']) {
            case 'admin':   redirect(BASE_URL . '/app/admin/dashboard.php');   break;
            case 'staff':   redirect(BASE_URL . '/app/staff/dashboard.php');   break;
            case 'student': redirect(BASE_URL . '/app/student/dashboard.php'); break;
        }
    } else {
        // Failed login
        $error = "Invalid credentials or account is inactive.";
        logActivity($pdo, null, 'LOGIN_FAILED', 'Failed attempt for: ' . $email);
    }
}

renderHeader('Login');
?>

<div class="card">
    <h2>Login</h2>

    <?php if ($error): ?>
        <div class="error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email"
                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                   required autofocus>
        </div>
        <div class="form-group">
            <label for="password">Password</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>

    <div class="info-box">
        <strong>Test Accounts</strong> (password: <code>password</code>)<br>
        admin@nbsc.edu.ph &nbsp;·&nbsp; r.villanueva@nbsc.edu.ph &nbsp;·&nbsp; r.geonzon@nbsc.edu.ph
    </div>
</div>

<?php renderFooter(); ?>