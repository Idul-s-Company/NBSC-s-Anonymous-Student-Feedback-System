<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('admin');

// Stats
$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalStaff    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

$totalFeedback  = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='pending'")->fetchColumn();
$reviewedCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='reviewed'")->fetchColumn();
$resolvedCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='resolved'")->fetchColumn();
$urgentCount    = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();

// Recent feedback
$recentFeedback = $pdo->query("
    SELECT f.*, c.category_name
    FROM feedback f
    LEFT JOIN categories c ON f.category_id = c.category_id
    ORDER BY f.submitted_at DESC LIMIT 5
")->fetchAll();

// Recent logs
$recentLogs = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS full_name
    FROM activity_logs a
    JOIN users u ON a.user_id = u.user_id
    ORDER BY a.created_at DESC LIMIT 5
")->fetchAll();

renderHeader('Admin Dashboard');
renderSidebar('admin', 'Dashboard');
?>
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <div class="topbar-actions">
    <a href="<?= BASE_URL ?>/app/admin/notifications.php" class="notif-btn">
      <?= svgIcon('bell') ?>
      <?php if (getUnreadNotifCount($pdo, $_SESSION['user_id']) > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<div class="content">
  <div class="page-header">
    <h1>Admin Dashboard</h1>
    <p>Welcome back, <?= sanitize($_SESSION['first_name']) ?>. Here's an overview of the system.</p>
  </div>

  <!-- Stats: Users -->
  <div class="stats-grid">
    <div class="stat-card purple">
      <div class="stat-label">Total Users</div>
      <div class="stat-value"><?= $totalUsers ?></div>
    </div>
    <div class="stat-card blue">
      <div class="stat-label">Admins</div>
      <div class="stat-value"><?= $totalAdmins ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Staff</div>
      <div class="stat-value"><?= $totalStaff ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Students</div>
      <div class="stat-value"><?= $totalStudents ?></div>
    </div>
  </div>

  <!-- Stats: Feedback -->
  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-label">Total Feedback</div>
      <div class="stat-value"><?= $totalFeedback ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card purple">
      <div class="stat-label">Reviewed</div>
      <div class="stat-value"><?= $reviewedCount ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Resolved</div>
      <div class="stat-value"><?= $resolvedCount ?></div>
    </div>
    <div class="stat-card red">
      <div class="stat-label">Urgent</div>
      <div class="stat-value"><?= $urgentCount ?></div>
    </div>
  </div>

  <div class="row">
    <!-- Recent Feedback -->
    <div class="col-8">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Feedback</span>
          <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Category</th>
                <th>Message</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Submitted</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($recentFeedback)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:24px;">No feedback yet.</td></tr>
              <?php else: foreach ($recentFeedback as $fb): ?>
                <tr>
                  <td><?= sanitize($fb['category_name'] ?? '—') ?></td>
                  <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
                  <td><?= priorityBadge($fb['priority']) ?></td>
                  <td><?= statusBadge($fb['status']) ?></td>
                  <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-4">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Activity</span>
          <a href="<?= BASE_URL ?>/app/admin/activity-logs.php" class="btn btn-outline btn-sm">All Logs</a>
        </div>
        <div class="card-body" style="padding:0;">
          <?php if (empty($recentLogs)): ?>
            <p class="text-muted text-center" style="padding:24px;">No activity yet.</p>
          <?php else: foreach ($recentLogs as $log): ?>
            <div style="padding:12px 20px; border-bottom:1px solid #f3f4f6;">
              <div style="font-size:13px;font-weight:500;"><?= sanitize($log['full_name']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($log['action']) ?> · <?= timeAgo($log['created_at']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= sanitize($log['description']) ?></div>
            </div>
          <?php endforeach; endif; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
