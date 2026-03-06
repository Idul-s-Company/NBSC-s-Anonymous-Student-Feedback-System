<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';

requireRole('admin');

$msg = ''; $err = '';

// Create user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_user'])) {
    $school_id  = trim($_POST['school_id']);
    $first_name = trim($_POST['first_name']);
    $last_name  = trim($_POST['last_name']);
    $email      = trim($_POST['email']);
    $password   = $_POST['password'];
    $role       = $_POST['role'];
    $department = trim($_POST['department']);
    $status     = $_POST['status'];

    if ($school_id && $first_name && $last_name && $email && $password && $role && $department) {
        // check duplicate email
        $check = $pdo->prepare("SELECT user_id FROM users WHERE email=?");
        $check->execute([$email]);
        if ($check->fetch()) {
            $err = 'Email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_BCRYPT);
            $pdo->prepare("INSERT INTO users (school_id,first_name,last_name,email,password,role,department,status) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$school_id,$first_name,$last_name,$email,$hash,$role,$department,$status]);
            logActivity($pdo, $_SESSION['user_id'], 'USER_CREATED', "Created user account for $first_name $last_name");
            $msg = 'User created successfully.';
        }
    } else {
        $err = 'Please fill in all required fields.';
    }
}

// Delete user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $uid = (int)$_POST['user_id'];
    if ($uid !== $_SESSION['user_id']) {
        $pdo->prepare("DELETE FROM users WHERE user_id=?")->execute([$uid]);
        logActivity($pdo, $_SESSION['user_id'], 'USER_DELETED', "Deleted user #$uid");
        $msg = 'User deleted.';
    } else {
        $err = 'You cannot delete your own account.';
    }
}

// Toggle status
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_status'])) {
    $uid = (int)$_POST['user_id'];
    $newStatus = $_POST['current_status'] === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE users SET status=? WHERE user_id=?")->execute([$newStatus, $uid]);
    $msg = 'User status updated.';
}

$users = $pdo->query("SELECT * FROM users ORDER BY role, last_name")->fetchAll();

renderHeader('User Management');
renderSidebar('admin', 'Users');
?>
<div class="topbar">
  <span class="topbar-title">User Management</span>
  <div class="topbar-actions">
    <button class="btn btn-primary" onclick="document.getElementById('createModal').style.display='flex'">
      <?= svgIcon('plus') ?> Add User
    </button>
  </div>
</div>

<div class="content">
  <div class="page-header">
    <h1>Users</h1>
    <p>Manage all system users — students, staff, and admins.</p>
  </div>

  <?php if ($msg): ?><div class="alert alert-success"><?= sanitize($msg) ?></div><?php endif; ?>
  <?php if ($err): ?><div class="alert alert-danger"><?= sanitize($err) ?></div><?php endif; ?>

  <div class="card">
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>School ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Role</th>
            <th>Department</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($users as $u): ?>
            <tr>
              <td style="font-family:'DM Mono',monospace;font-size:12px;"><?= sanitize($u['school_id']) ?></td>
              <td style="font-weight:500;"><?= sanitize($u['first_name'] . ' ' . $u['last_name']) ?></td>
              <td class="text-muted"><?= sanitize($u['email']) ?></td>
              <td><?= roleBadge($u['role']) ?></td>
              <td><?= sanitize($u['department']) ?></td>
              <td>
                <span class="badge <?= $u['status']==='active' ? 'badge-active' : 'badge-inactive' ?>">
                  <?= ucfirst($u['status']) ?>
                </span>
              </td>
              <td class="text-muted"><?= date('M d, Y', strtotime($u['created_at'])) ?></td>
              <td>
                <div class="flex gap-2">
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                    <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
                    <button type="submit" name="toggle_status" class="btn btn-outline btn-sm">
                      <?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?>
                    </button>
                  </form>
                  <?php if ($u['user_id'] !== $_SESSION['user_id']): ?>
                    <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this user?')">
                      <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
                      <button type="submit" name="delete_user" class="btn btn-danger btn-sm">Delete</button>
                    </form>
                  <?php endif; ?>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<!-- Create User Modal -->
<div id="createModal" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.4);z-index:200;align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:12px;width:560px;max-width:90vw;max-height:90vh;overflow-y:auto;box-shadow:0 20px 60px rgba(0,0,0,0.15);">
    <div style="padding:20px 24px;border-bottom:1px solid #e5e7eb;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-weight:600;font-size:15px;">Add New User</span>
      <button onclick="document.getElementById('createModal').style.display='none'" style="background:none;border:none;cursor:pointer;font-size:20px;color:#9ca3af;">×</button>
    </div>
    <div style="padding:20px 24px;">
      <form method="POST">
        <div class="row">
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">School ID *</label>
              <input type="text" name="school_id" class="form-control" placeholder="2024-00001" required>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">Role *</label>
              <select name="role" class="form-control" required>
                <option value="student">Student</option>
                <option value="staff">Staff</option>
                <option value="admin">Admin</option>
              </select>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">First Name *</label>
              <input type="text" name="first_name" class="form-control" required>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">Last Name *</label>
              <input type="text" name="last_name" class="form-control" required>
            </div>
          </div>
          <div class="col-12">
            <div class="form-group">
              <label class="form-label">Email *</label>
              <input type="email" name="email" class="form-control" placeholder="user@nbsc.edu.ph" required>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">Password *</label>
              <input type="password" name="password" class="form-control" required>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">Department *</label>
              <input type="text" name="department" class="form-control" placeholder="IT, Business..." required>
            </div>
          </div>
          <div class="col-6">
            <div class="form-group">
              <label class="form-label">Status</label>
              <select name="status" class="form-control">
                <option value="active">Active</option>
                <option value="inactive">Inactive</option>
              </select>
            </div>
          </div>
        </div>
        <div class="flex gap-2" style="justify-content:flex-end;margin-top:4px;">
          <button type="button" onclick="document.getElementById('createModal').style.display='none'" class="btn btn-outline">Cancel</button>
          <button type="submit" name="create_user" class="btn btn-primary">Create User</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php renderSidebarClose(); renderFooter(); ?>
