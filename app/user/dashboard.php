<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('student');

// Student sees only anonymized counts; no personal feedback tied to user
$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='pending'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='resolved'")->fetchColumn();

// Recent resolved (for transparency)
$recentResolved = $pdo->query("
    SELECT f.message, f.priority, c.category_name, f.submitted_at,
           r.review_notes, CONCAT(u.first_name,' ',u.last_name) AS reviewed_by
    FROM feedback f
    LEFT JOIN categories c ON f.category_id=c.category_id
    LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id
    LEFT JOIN users u ON r.reviewed_by=u.user_id
    WHERE f.status='resolved'
    ORDER BY f.submitted_at DESC LIMIT 5
")->fetchAll();

renderHeader('Student Dashboard');
renderSidebar('student', 'Dashboard');
?>
<div class="topbar">
  <span class="topbar-title">Dashboard</span>
  <div class="topbar-actions">
    <a href="<?= BASE_URL ?>/app/user/notifications.php" class="notif-btn">
      <?= svgIcon('bell') ?>
      <?php if (getUnreadNotifCount($pdo, $_SESSION['user_id']) > 0): ?>
        <span class="notif-dot"></span>
      <?php endif; ?>
    </a>
  </div>
</div>

<div class="content">
  <div class="page-header">
    <h1>Student Dashboard</h1>
    <p>Welcome, <?= sanitize($_SESSION['first_name']) ?>. Submit anonymous feedback and track resolutions.</p>
  </div>

  <div class="stats-grid">
    <div class="stat-card blue">
      <div class="stat-label">Total Feedback</div>
      <div class="stat-value"><?= $totalFeedback ?></div>
    </div>
    <div class="stat-card orange">
      <div class="stat-label">Pending</div>
      <div class="stat-value"><?= $pendingCount ?></div>
    </div>
    <div class="stat-card green">
      <div class="stat-label">Resolved</div>
      <div class="stat-value"><?= $resolvedCount ?></div>
    </div>
  </div>

  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recently Resolved</span>
          <span class="badge badge-resolved">Transparent</span>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>Category</th><th>Message</th><th>Priority</th><th>Review Notes</th><th>Date</th></tr>
            </thead>
            <tbody>
              <?php if (empty($recentResolved)): ?>
                <tr><td colspan="5" class="text-center text-muted" style="padding:32px;">No resolved feedback yet.</td></tr>
              <?php else: foreach ($recentResolved as $fb): ?>
                <tr>
                  <td><?= sanitize($fb['category_name'] ?? '—') ?></td>
                  <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
                  <td><?= priorityBadge($fb['priority']) ?></td>
                  <td style="font-size:12.5px;color:var(--text-muted);"><?= sanitize($fb['review_notes'] ?? '—') ?></td>
                  <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
                </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <div class="card-header"><span class="card-title">Quick Actions</span></div>
        <div class="card-body">
          <a href="<?= BASE_URL ?>/app/user/submit.php" class="btn btn-primary" style="width:100%;justify-content:center;margin-bottom:10px;">
            <?= svgIcon('plus-circle') ?> Submit Feedback
          </a>
          <a href="<?= BASE_URL ?>/app/user/my-feedback.php" class="btn btn-outline" style="width:100%;justify-content:center;">
            <?= svgIcon('message-square') ?> My Submissions
          </a>
          <div class="divider"></div>
          <div style="font-size:12.5px;color:var(--text-muted);line-height:1.7;">
            <strong>Privacy Notice</strong><br>
            Your feedback is submitted anonymously. Your personal identity is never linked to your submission.
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
