<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('student');

// Feedback visible to student = only those submitted in this session (anonymous system)
$myIds = $_SESSION['submitted_ids'] ?? [];
$feedbacks = [];

if (!empty($myIds)) {
    $placeholders = implode(',', array_fill(0, count($myIds), '?'));
    $stmt = $pdo->prepare("
        SELECT f.*, c.category_name,
               r.review_notes, r.reviewed_at,
               CONCAT(u.first_name,' ',u.last_name) AS reviewed_by
        FROM feedback f
        LEFT JOIN categories c ON f.category_id=c.category_id
        LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id
        LEFT JOIN users u ON r.reviewed_by=u.user_id
        WHERE f.feedback_id IN ($placeholders)
        ORDER BY f.submitted_at DESC
    ");
    $stmt->execute($myIds);
    $feedbacks = $stmt->fetchAll();
}

renderHeader('My Feedback');
renderSidebar('student', 'My Feedback');
?>
<div class="topbar">
  <span class="topbar-title">My Feedback</span>
</div>

<div class="content">
  <div class="page-header">
    <h1>My Submissions</h1>
    <p>Track the status of feedback you submitted in this session.</p>
  </div>

  <?php if (empty($feedbacks)): ?>
    <div class="card">
      <div class="empty-state">
        <?= svgIcon('message-square') ?>
        <p>No feedback submitted yet in this session.</p>
        <a href="submit.php" class="btn btn-primary" style="margin-top:12px;">Submit Feedback</a>
      </div>
    </div>
  <?php else: ?>
    <div class="card">
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Category</th>
              <th>Message</th>
              <th>Priority</th>
              <th>Status</th>
              <th>Review Notes</th>
              <th>Submitted</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($feedbacks as $fb): ?>
              <tr>
                <td><?= sanitize($fb['category_name'] ?? '—') ?></td>
                <td style="max-width:240px;"><?= sanitize($fb['message']) ?></td>
                <td><?= priorityBadge($fb['priority']) ?></td>
                <td><?= statusBadge($fb['status']) ?></td>
                <td style="font-size:12.5px;color:var(--text-muted);">
                  <?= $fb['review_notes'] ? sanitize($fb['review_notes']) : '<em>Awaiting review</em>' ?>
                </td>
                <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    </div>
  <?php endif; ?>

  <div class="alert alert-info mt-3" style="font-size:12.5px;">
    <strong>Note:</strong> For privacy, your feedback history is only visible during your current session. Once you log out, this list clears — your submissions remain in the system but are fully anonymous.
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
