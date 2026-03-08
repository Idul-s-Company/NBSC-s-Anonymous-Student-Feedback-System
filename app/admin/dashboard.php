<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';
require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../includes/sidebar.php';
require_once __DIR__ . '/../../includes/footer.php';

requireRole('admin');

$totalUsers    = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$totalAdmins   = $pdo->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn();
$totalStaff    = $pdo->query("SELECT COUNT(*) FROM users WHERE role='staff'")->fetchColumn();
$totalStudents = $pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();

$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingCount  = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='pending'")->fetchColumn();
$reviewedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='reviewed'")->fetchColumn();
$resolvedCount = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status='resolved'")->fetchColumn();
$urgentCount   = $pdo->query("SELECT COUNT(*) FROM feedback WHERE priority='Urgent'")->fetchColumn();
$totalWarnings = $pdo->query("SELECT COUNT(*) FROM user_warnings")->fetchColumn();
$totalComments = $pdo->query("SELECT COUNT(*) FROM comments WHERE status='active'")->fetchColumn();

// Chart data: feedback by category
$catData = $pdo->query("SELECT category, COUNT(*) AS cnt FROM feedback GROUP BY category")->fetchAll();
$catLabels = array_column($catData, 'category');
$catCounts = array_column($catData, 'cnt');

// Chart data: feedback by status
$statusData = [$pendingCount, $reviewedCount, $resolvedCount];

// Chart data: feedback by priority
$priData = $pdo->query("SELECT priority, COUNT(*) AS cnt FROM feedback GROUP BY priority")->fetchAll();
$priLabels = array_column($priData, 'priority');
$priCounts = array_column($priData, 'cnt');

// Chart data: feedback submitted per day (last 7 days)
$dailyData = $pdo->query("
    SELECT DATE(submitted_at) AS day, COUNT(*) AS cnt
    FROM feedback
    WHERE submitted_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(submitted_at)
    ORDER BY day ASC
")->fetchAll();
$dailyLabels = array_column($dailyData, 'day');
$dailyCounts = array_column($dailyData, 'cnt');

$recentFeedback = $pdo->query("SELECT * FROM feedback ORDER BY submitted_at DESC LIMIT 5")->fetchAll();
$recentLogs = $pdo->query("
    SELECT a.*, CONCAT(u.first_name,' ',u.last_name) AS full_name
    FROM activity_logs a JOIN users u ON a.user_id=u.user_id
    ORDER BY a.created_at DESC LIMIT 6
")->fetchAll();

renderHeader('Admin Dashboard');
renderSidebar('admin', 'Dashboard');
?>

<!-- Bootstrap CSS -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">

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
    <p>Welcome back, <?= sanitize($_SESSION['first_name']) ?>. System overview.</p>
  </div>

  <!-- User Stats -->
  <div class="stats-grid">
    <div class="stat-card purple"><div class="stat-label">Total Users</div><div class="stat-value"><?= $totalUsers ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Admins</div><div class="stat-value"><?= $totalAdmins ?></div></div>
    <div class="stat-card green"><div class="stat-label">Staff</div><div class="stat-value"><?= $totalStaff ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Students</div><div class="stat-value"><?= $totalStudents ?></div></div>
  </div>

  <!-- Charts Row -->
  <div class="row g-3 mb-4">

    <!-- Donut: Users by Role -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><span class="card-title">Users by Role</span></div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="chartUserRole" height="220"></canvas>
        </div>
      </div>
    </div>

    <!-- Bar: Feedback by Category -->
    <div class="col-md-8">
      <div class="card h-100">
        <div class="card-header"><span class="card-title">Feedback by Category</span></div>
        <div class="card-body">
          <canvas id="chartCategory" height="220"></canvas>
        </div>
      </div>
    </div>

  </div>

  <!-- Feedback Stats -->
  <div class="stats-grid">
    <div class="stat-card blue"><div class="stat-label">Total Feedback</div><div class="stat-value"><?= $totalFeedback ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Pending</div><div class="stat-value"><?= $pendingCount ?></div></div>
    <div class="stat-card purple"><div class="stat-label">Reviewed</div><div class="stat-value"><?= $reviewedCount ?></div></div>
    <div class="stat-card green"><div class="stat-label">Resolved</div><div class="stat-value"><?= $resolvedCount ?></div></div>
    <div class="stat-card red"><div class="stat-label">Urgent</div><div class="stat-value"><?= $urgentCount ?></div></div>
    <div class="stat-card orange"><div class="stat-label">Warnings</div><div class="stat-value"><?= $totalWarnings ?></div></div>
    <div class="stat-card blue"><div class="stat-label">Comments</div><div class="stat-value"><?= $totalComments ?></div></div>
  </div>

  <!-- Charts Row 2 -->
  <div class="row g-3 mb-4">

    <!-- Donut: Feedback by Status -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><span class="card-title">Feedback by Status</span></div>
        <div class="card-body d-flex align-items-center justify-content-center">
          <canvas id="chartStatus" height="220"></canvas>
        </div>
      </div>
    </div>

    <!-- Bar: Feedback by Priority -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><span class="card-title">Feedback by Priority</span></div>
        <div class="card-body">
          <canvas id="chartPriority" height="220"></canvas>
        </div>
      </div>
    </div>

    <!-- Line: Submissions (Last 7 Days) -->
    <div class="col-md-4">
      <div class="card h-100">
        <div class="card-header"><span class="card-title">Submissions (Last 7 Days)</span></div>
        <div class="card-body">
          <canvas id="chartDaily" height="220"></canvas>
        </div>
      </div>
    </div>

  </div>

  <!-- Recent Feedback + Activity -->
  <div class="row">
    <div class="col-8">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Feedback</span>
          <a href="<?= BASE_URL ?>/app/admin/feedback.php" class="btn btn-outline btn-sm">View All</a>
        </div>
        <div class="table-wrap">
          <table>
            <thead><tr><th>Category</th><th>Message</th><th>Priority</th><th>Status</th><th>Submitted</th></tr></thead>
            <tbody>
              <?php foreach ($recentFeedback as $fb): ?>
              <tr>
                <td><?= sanitize(categoryLabel($fb['category'])) ?></td>
                <td><span class="msg-truncate"><?= sanitize($fb['message']) ?></span></td>
                <td><?= priorityBadge($fb['priority']) ?></td>
                <td><?= statusBadge($fb['status']) ?></td>
                <td class="text-muted"><?= timeAgo($fb['submitted_at']) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
    <div class="col-4">
      <div class="card">
        <div class="card-header">
          <span class="card-title">Recent Activity</span>
          <a href="<?= BASE_URL ?>/app/admin/activity-logs.php" class="btn btn-outline btn-sm">All</a>
        </div>
        <div class="card-body" style="padding:0;">
          <?php foreach ($recentLogs as $log): ?>
          <div style="padding:11px 20px;border-bottom:1px solid #f3f4f6;">
            <div style="font-size:13px;font-weight:500;"><?= sanitize($log['full_name']) ?></div>
            <div style="font-size:12px;color:var(--text-muted);"><?= sanitize($log['action']) ?> · <?= timeAgo($log['created_at']) ?></div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
  </div>

</div>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
const catLabels  = <?= json_encode(array_map('ucfirst', $catLabels)) ?>;
const catCounts  = <?= json_encode(array_map('intval', $catCounts)) ?>;
const priLabels  = <?= json_encode($priLabels) ?>;
const priCounts  = <?= json_encode(array_map('intval', $priCounts)) ?>;
const dailyLabels = <?= json_encode($dailyLabels) ?>;
const dailyCounts = <?= json_encode(array_map('intval', $dailyCounts)) ?>;

// Donut: Users by Role
new Chart(document.getElementById('chartUserRole'), {
  type: 'doughnut',
  data: {
    labels: ['Admins', 'Staff', 'Students'],
    datasets: [{
      data: [<?= $totalAdmins ?>, <?= $totalStaff ?>, <?= $totalStudents ?>],
      backgroundColor: ['#7e3af2','#1a56db','#0e9f6e'],
      borderWidth: 2, borderColor: '#fff'
    }]
  },
  options: { plugins: { legend: { position: 'bottom' } }, cutout: '65%', responsive: true }
});

// Bar: Feedback by Category
new Chart(document.getElementById('chartCategory'), {
  type: 'bar',
  data: {
    labels: catLabels,
    datasets: [{
      label: 'Feedback Count',
      data: catCounts,
      backgroundColor: ['#1a56db','#7e3af2','#0e9f6e','#e3a008','#e02424','#6366f1','#f59e0b','#10b981','#ef4444'],
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    responsive: true
  }
});

// Donut: Feedback by Status
new Chart(document.getElementById('chartStatus'), {
  type: 'doughnut',
  data: {
    labels: ['Pending', 'Reviewed', 'Resolved'],
    datasets: [{
      data: <?= json_encode($statusData) ?>,
      backgroundColor: ['#e3a008','#1a56db','#0e9f6e'],
      borderWidth: 2, borderColor: '#fff'
    }]
  },
  options: { plugins: { legend: { position: 'bottom' } }, cutout: '65%', responsive: true }
});

// Bar: Feedback by Priority
new Chart(document.getElementById('chartPriority'), {
  type: 'bar',
  data: {
    labels: priLabels,
    datasets: [{
      label: 'Count',
      data: priCounts,
      backgroundColor: ['#0e9f6e','#e3a008','#f97316','#e02424'],
      borderRadius: 6, borderSkipped: false
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    responsive: true
  }
});

// Line: Daily submissions
new Chart(document.getElementById('chartDaily'), {
  type: 'line',
  data: {
    labels: dailyLabels.length ? dailyLabels : ['No data'],
    datasets: [{
      label: 'Submissions',
      data: dailyCounts.length ? dailyCounts : [0],
      borderColor: '#1a56db',
      backgroundColor: 'rgba(26,86,219,0.08)',
      pointBackgroundColor: '#1a56db',
      fill: true, tension: 0.4, borderWidth: 2
    }]
  },
  options: {
    plugins: { legend: { display: false } },
    scales: { y: { beginAtZero: true, ticks: { stepSize: 1 } } },
    responsive: true
  }
});
</script>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<?php renderSidebarClose(); renderFooter(); ?>