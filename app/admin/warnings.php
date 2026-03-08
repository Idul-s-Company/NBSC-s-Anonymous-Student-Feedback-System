<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_warning'])) {
    $uid     = (int)$_POST['user_id'];
    $reason  = trim($_POST['reason']);
    $content = trim($_POST['content']);
    if ($uid && $reason) {
        $pdo->prepare("INSERT INTO user_warnings (user_id, reason, content) VALUES (?,?,?)")
            ->execute([$uid, $reason, $content]);
        logActivity($pdo, $_SESSION['user_id'], 'WARNING_ISSUED', "Warning issued to user #$uid: $reason");
        $msg = 'Warning issued.';
    } else { $err = 'Fill in required fields.'; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_warning'])) {
    $wid = (int)$_POST['warning_id'];
    $pdo->prepare("DELETE FROM user_warnings WHERE user_warnings_id=?")->execute([$wid]);
    $msg = 'Warning deleted.';
}

$warnings = $pdo->query("
    SELECT w.*, CONCAT(u.first_name,' ',u.last_name) AS full_name, u.role, u.email
    FROM user_warnings w JOIN users u ON w.user_id=u.user_id
    ORDER BY w.created_at DESC
")->fetchAll();

$students = $pdo->query("SELECT user_id, CONCAT(first_name,' ',last_name) AS full_name, school_id FROM users WHERE role='student' AND status='active' ORDER BY last_name")->fetchAll();

renderHeader('Warnings');
renderSidebar('admin', 'Warnings');
?>
<div class="topbar">
  <span class="topbar-title">User Warnings</span>
  <div class="topbar-actions">
    <button class="btn btn-primary" onclick="document.getElementById('warnModal').style.display='flex'">
      <?= svgIcon('flag') ?> Issue Warning
    </button>
  </div>
</div>
<div class="content">
  <div class="page-header"><h1>User Warnings</h1><p>Track violations and warnings issued to users.</p></div>
  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead><tr><th>#</th><th>User</th><th>Role</th><th>Reason</th><th>Details</th><th>Issued</th><th>Action</th></tr></thead>
        <tbody>
          <?php if (empty($warnings)): ?>
            <tr><td colspan="7" class="text-center text-muted" style="padding:32px;">No warnings issued.</td></tr>
          <?php else: foreach ($warnings as $i => $w): ?>
          <tr>
            <td class="text-muted"><?= $i+1 ?></td>
            <td>
              <div style="font-weight:500;"><?= sanitize($w['full_name']) ?></div>
              <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($w['email']) ?></div>
            </td>
            <td><?= roleBadge($w['role']) ?></td>
            <td><span class="badge badge-high"><?= sanitize($w['reason']) ?></span></td>
            <td style="max-width:220px;font-size:12.5px;"><?= sanitize($w['content'] ?? '—') ?></td>
            <td class="text-muted"><?= timeAgo($w['created_at']) ?></td>
            <td>
              <form method="POST" onsubmit="return confirm('Delete this warning?')">
                <input type="hidden" name="warning_id" value="<?= $w['user_warnings_id'] ?>">
                <button type="submit" name="delete_warning" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Issue Warning Modal -->
<div id="warnModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;width:480px;max-width:90vw;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-weight:600;font-size:15px;">Issue Warning</span>
      <button onclick="document.getElementById('warnModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">×</button>
    </div>
    <div style="padding:20px 24px;">
      <form method="POST">
        <div class="form-group">
          <label class="form-label">User *</label>
          <select name="user_id" class="form-control" required>
            <option value="">— Select user —</option>
            <?php foreach ($students as $u): ?>
              <option value="<?= $u['user_id'] ?>"><?= sanitize($u['school_id'] . ' — ' . $u['full_name']) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Reason *</label>
          <input type="text" name="reason" class="form-control" placeholder="e.g. Offensive language" required>
        </div>
        <div class="form-group">
          <label class="form-label">Details</label>
          <textarea name="content" class="form-control" rows="3" placeholder="Describe the violation..."></textarea>
        </div>
        <div class="flex gap-2" style="justify-content:flex-end;">
          <button type="button" onclick="document.getElementById('warnModal').style.display='none'" class="btn btn-outline">Cancel</button>
          <button type="submit" name="add_warning" class="btn btn-danger">Issue Warning</button>
        </div>
      </form>
    </div>
  </div>
</div>
<?php renderSidebarClose(); renderFooter(); ?>
