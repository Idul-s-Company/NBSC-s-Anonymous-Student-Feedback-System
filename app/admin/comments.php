<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('admin');
$msg = ''; $err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['category_name']);
        $desc = trim($_POST['description']);
        if ($name && $desc) {
            $pdo->prepare("INSERT INTO categories (category_name, description) VALUES (?,?)")->execute([$name,$desc]);
            $msg = 'Category added.';
        } else { $err = 'Fill in all fields.'; }
    }
    if (isset($_POST['delete_category'])) {
        $cid = (int)$_POST['category_id'];
        // Check if used
        $used = $pdo->prepare("SELECT COUNT(*) FROM feedback WHERE category_id=?");
        $used->execute([$cid]);
        if ($used->fetchColumn() > 0) {
            $err = 'Cannot delete: category has associated feedback.';
        } else {
            $pdo->prepare("DELETE FROM categories WHERE category_id=?")->execute([$cid]);
            $msg = 'Category deleted.';
        }
    }
}

$categories = $pdo->query("
    SELECT c.*, COUNT(f.feedback_id) AS feedback_count
    FROM categories c
    LEFT JOIN feedback f ON c.category_id = f.category_id
    GROUP BY c.category_id ORDER BY c.category_name
")->fetchAll();

renderHeader('Categories');
renderSidebar('admin', 'Categories');
?>
<div class="topbar">
  <span class="topbar-title">Categories</span>
</div>

<div class="content">
  <div class="page-header">
    <h1>Feedback Categories</h1>
    <p>Manage feedback categories shown to users when submitting.</p>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="table-wrap">
          <table>
            <thead>
              <tr><th>#</th><th>Category</th><th>Description</th><th>Feedback Count</th><th>Action</th></tr>
            </thead>
            <tbody>
              <?php foreach ($categories as $i => $cat): ?>
                <tr>
                  <td class="text-muted"><?= $i+1 ?></td>
                  <td style="font-weight:500;"><?= sanitize($cat['category_name']) ?></td>
                  <td class="text-muted"><?= sanitize($cat['description']) ?></td>
                  <td><span class="badge badge-reviewed"><?= $cat['feedback_count'] ?></span></td>
                  <td>
                    <form method="POST" onsubmit="return confirm('Delete this category?')">
                      <input type="hidden" name="category_id" value="<?= $cat['category_id'] ?>">
                      <button type="submit" name="delete_category" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <div class="col-4">
      <div class="card">
        <div class="card-header"><span class="card-title">Add Category</span></div>
        <div class="card-body">
          <form method="POST">
            <div class="form-group">
              <label class="form-label">Category Name *</label>
              <input type="text" name="category_name" class="form-control" placeholder="e.g. Academic" required>
            </div>
            <div class="form-group">
              <label class="form-label">Description *</label>
              <textarea name="description" class="form-control" rows="3" placeholder="Brief description..." required></textarea>
            </div>
            <button type="submit" name="add_category" class="btn btn-primary" style="width:100%;justify-content:center;">
              Add Category
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
