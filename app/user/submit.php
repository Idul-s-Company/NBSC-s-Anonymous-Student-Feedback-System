<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('student');

$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $category_id = (int)$_POST['category_id'];
    $priority    = $_POST['priority'];
    $message     = trim($_POST['message']);

    $allowedPriority = ['Low','Medium','High','Urgent'];
    if ($category_id && in_array($priority, $allowedPriority) && strlen($message) >= 10) {
        $pdo->prepare("INSERT INTO feedback (category_id, priority, message, status) VALUES (?,?,?,'pending')")
            ->execute([$category_id, $priority, $message]);

        $newFeedbackId = $pdo->lastInsertId();

        // Notify admins & staff
        $admins = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','staff') AND status='active'")->fetchAll();
        foreach ($admins as $a) {
            $pdo->prepare("INSERT INTO notifications (user_id, title, message) VALUES (?,?,?)")
                ->execute([$a['user_id'], "New $priority Feedback", "A new $priority feedback was submitted under category #$category_id."]);
        }

        logActivity($pdo, $_SESSION['user_id'], 'FEEDBACK_SUBMITTED', "Student submitted feedback #$newFeedbackId");
        $msg = 'Your feedback has been submitted anonymously. Thank you!';

        // Store in session for "my feedback" tracking (optional, anonymous)
        $_SESSION['submitted_ids'][] = $newFeedbackId;

    } else {
        $err = 'Please fill all fields. Message must be at least 10 characters.';
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY category_name")->fetchAll();

renderHeader('Submit Feedback');
renderSidebar('student', 'Submit Feedback');
?>
<div class="topbar">
  <span class="topbar-title">Submit Feedback</span>
</div>

<div class="content">
  <div class="page-header">
    <h1>Submit Anonymous Feedback</h1>
    <p>Your identity is fully protected. We never store any personal link to your submission.</p>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="card-header"><span class="card-title">Feedback Form</span></div>
        <div class="card-body">
          <form method="POST">
            <div class="row">
              <div class="col-6">
                <div class="form-group">
                  <label class="form-label">Category *</label>
                  <select name="category_id" class="form-control" required>
                    <option value="">— Select a category —</option>
                    <?php foreach ($categories as $cat): ?>
                      <option value="<?= $cat['category_id'] ?>"><?= sanitize($cat['category_name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-6">
                <div class="form-group">
                  <label class="form-label">Priority *</label>
                  <select name="priority" class="form-control" required>
                    <option value="Low">Low</option>
                    <option value="Medium">Medium</option>
                    <option value="High">High</option>
                    <option value="Urgent">Urgent</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Message *</label>
              <textarea name="message" class="form-control" rows="5"
                placeholder="Describe your concern clearly. Be specific but avoid including personal identifiers."
                maxlength="200" required></textarea>
              <div style="font-size:12px;color:var(--text-muted);margin-top:4px;">Max 200 characters.</div>
            </div>
            <button type="submit" name="submit_feedback" class="btn btn-primary" style="padding:9px 24px;">
              <?= svgIcon('check-circle') ?> Submit Anonymously
            </button>
          </form>
        </div>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <div class="card-header"><span class="card-title">Categories Guide</span></div>
        <div class="card-body" style="padding:0;">
          <?php foreach ($categories as $cat): ?>
            <div style="padding:10px 20px;border-bottom:1px solid #f3f4f6;">
              <div style="font-weight:600;font-size:13px;"><?= sanitize($cat['category_name']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);margin-top:2px;"><?= sanitize($cat['description']) ?></div>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
