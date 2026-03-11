<?php
session_start();
require_once __DIR__ . '/../../config/config.php';
require_once __DIR__ . '/../../config/function.php';

requireRole('student');

$msg = ''; $err = ''; $submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
    $category = $_POST['category'];
    $priority = $_POST['priority'];
    $message  = trim($_POST['message']);
    $allowed_cats = ['general','academic','facilities','services','faculty','administration','suggestion','complaint','other'];
    $allowed_pri  = ['Low','Medium','High','Urgent'];

    if (in_array($category,$allowed_cats) && in_array($priority,$allowed_pri) && strlen($message)>=10 && strlen($message)<=200) {
       $pdo->prepare("INSERT INTO feedback (user_id, category, priority, message, status) VALUES (?,?,?,?,'pending')")
    ->execute([$_SESSION['user_id'], $category, $priority, $message]);
        $fid = $pdo->lastInsertId();
        $_SESSION['submitted_ids'][] = $fid;

        // Notify admins and staff
        $notifyUsers = $pdo->query("SELECT user_id FROM users WHERE role IN ('admin','staff') AND status='active'")->fetchAll();
        foreach ($notifyUsers as $nu) {
            $pdo->prepare("INSERT INTO notifications (user_id,title,message) VALUES (?,?,?)")
                ->execute([$nu['user_id'], "New $priority Feedback", "A new $priority priority $category feedback was submitted."]);
        }

        logActivity($pdo,$_SESSION['user_id'],'FEEDBACK_SUBMITTED',"Student submitted feedback #$fid ($category)");
        $submitted = true;
    } else {
        $err = 'Please fill all fields. Message must be 10–200 characters.';
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_comment'])) {
    $fid     = (int)$_POST['feedback_id'];
    $content = trim($_POST['comment_content']);
    if ($fid && strlen($content) >= 2) {
        $encId   = encryptUserId($_SESSION['user_id']);
        $anonId  = generateAnonymousId();
        $pdo->prepare("INSERT INTO comments (feedback_id,encrypted_user_id,anonymous_id,content,status) VALUES (?,?,?,?,'active')")
            ->execute([$fid,$encId,$anonId,$content]);
        $msg = 'Comment posted anonymously.';
    }
}

// Public feed — all feedback with comments
$feedPage = max(1,(int)($_GET['page']??1));
$perPage  = 8;
$offset   = ($feedPage-1)*$perPage;
$total    = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pages    = ceil($total/$perPage);

$feedbacks = $pdo->query("
    SELECT f.*,
           (SELECT COUNT(*) FROM comments c WHERE c.feedback_id=f.feedback_id AND c.status='active') AS comment_count,
           r.review_notes, r.reviewed_at, CONCAT(u.first_name,' ',u.last_name) AS reviewed_by
    FROM feedback f
    LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id
    LEFT JOIN users u ON r.reviewed_by=u.user_id
    ORDER BY f.submitted_at DESC
    LIMIT $perPage OFFSET $offset
")->fetchAll();

// My submitted IDs this session
$myIds = $_SESSION['submitted_ids'] ?? [];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>NBSC Anonymous Feedback</title>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
  <style>
    /* ── NGL-style user app ── */
    .user-app { min-height:100vh; background:#f0f2f5; }

    /* Top nav */
    .user-nav {
      background:#fff;
      border-bottom:1px solid #e5e7eb;
      padding:0 20px;
      height:54px;
      display:flex; align-items:center; justify-content:space-between;
      position:sticky; top:0; z-index:100;
    }
    .user-nav-brand { display:flex; align-items:center; gap:10px; }
    .nav-logo {
      width:34px; height:34px; border-radius:10px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center;
      font-size:16px;
    }
    .nav-brand-text { font-size:15px; font-weight:700; letter-spacing:-0.3px; }
    .nav-brand-sub  { font-size:11px; color:var(--text-muted); margin-top:-2px; }
    .nav-user { display:flex; align-items:center; gap:10px; }
    .nav-avatar {
      width:32px; height:32px; border-radius:50%;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      display:flex; align-items:center; justify-content:center;
      font-size:13px; font-weight:700; color:#fff;
    }
    .nav-name { font-size:13px; font-weight:600; }
    .nav-role { font-size:11px; color:var(--text-muted); }

    /* Layout */
    .app-body { max-width:680px; margin:0 auto; padding:20px 16px 60px; }

    /* Submit box — NGL style */
    .submit-box {
      background:#fff;
      border-radius:16px;
      box-shadow:0 2px 12px rgba(0,0,0,0.07);
      overflow:hidden;
      margin-bottom:20px;
    }
    .submit-box-header {
      background:linear-gradient(135deg,#1a56db 0%,#7e3af2 100%);
      padding:20px 24px 16px;
      color:#fff;
    }
    .submit-box-header h2 { font-size:17px; font-weight:700; margin-bottom:3px; }
    .submit-box-header p  { font-size:12.5px; opacity:0.85; }
    .submit-box-body { padding:20px 24px; }

    .category-grid {
      display:grid;
      grid-template-columns:repeat(3,1fr);
      gap:8px;
      margin-bottom:16px;
    }
    .cat-btn {
      border:2px solid #e5e7eb;
      border-radius:10px;
      padding:10px 8px;
      text-align:center;
      cursor:pointer;
      background:#fafafa;
      transition:all 0.15s;
      font-size:12px;
      font-weight:500;
      color:var(--text-muted);
    }
    .cat-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-light); }
    .cat-btn.selected { border-color:var(--primary); background:var(--primary-light); color:var(--primary); font-weight:600; }
    .cat-btn .cat-icon { font-size:20px; display:block; margin-bottom:4px; }

    .priority-row { display:flex; gap:8px; margin-bottom:14px; }
    .pri-btn {
      flex:1; padding:8px; border-radius:8px;
      border:2px solid #e5e7eb; background:#fafafa;
      font-size:12px; font-weight:600; cursor:pointer;
      text-align:center; transition:all 0.15s; color:var(--text-muted);
    }
    .pri-btn:hover, .pri-btn.selected { border-color:currentColor; }
    .pri-btn.sel-Low     { border-color:#16a34a; background:#f0fdf4; color:#16a34a; }
    .pri-btn.sel-Medium  { border-color:#d97706; background:#fffbeb; color:#d97706; }
    .pri-btn.sel-High    { border-color:#ea580c; background:#fff7ed; color:#ea580c; }
    .pri-btn.sel-Urgent  { border-color:#dc2626; background:#fef2f2; color:#dc2626; }

    .msg-area {
      width:100%; border:2px solid #e5e7eb; border-radius:10px;
      padding:12px; font-family:inherit; font-size:13.5px;
      resize:none; outline:none; transition:border-color 0.15s; min-height:90px;
    }
    .msg-area:focus { border-color:var(--primary); }
    .char-count { font-size:11.5px; color:var(--text-muted); text-align:right; margin-top:4px; }

    .submit-btn {
      width:100%; padding:12px;
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      color:#fff; border:none; border-radius:10px;
      font-size:14px; font-weight:700; cursor:pointer;
      margin-top:14px; font-family:inherit;
      transition:opacity 0.15s;
    }
    .submit-btn:hover { opacity:0.92; }

    /* Success state */
    .success-box {
      text-align:center; padding:28px 20px;
    }
    .success-icon { font-size:48px; margin-bottom:12px; }
    .success-box h3 { font-size:17px; font-weight:700; margin-bottom:6px; }
    .success-box p  { font-size:13px; color:var(--text-muted); }

    /* Feed tabs */
    .feed-tabs { display:flex; gap:4px; margin-bottom:14px; }
    .feed-tab {
      flex:1; padding:9px; border-radius:8px;
      border:none; background:#fff; font-family:inherit;
      font-size:13px; font-weight:500; cursor:pointer;
      color:var(--text-muted); transition:all 0.15s;
    }
    .feed-tab.active { background:var(--primary); color:#fff; font-weight:600; }

    /* Feedback card */
    .fb-card {
      background:#fff; border-radius:14px;
      box-shadow:0 1px 4px rgba(0,0,0,0.07);
      margin-bottom:12px; overflow:hidden;
    }
    .fb-card-top { padding:16px 18px 12px; }
    .fb-meta { display:flex; align-items:center; gap:8px; margin-bottom:10px; flex-wrap:wrap; }
    .fb-cat {
      display:flex; align-items:center; gap:5px;
      background:#f3f4f6; padding:3px 10px; border-radius:99px;
      font-size:12px; font-weight:600; color:var(--text-muted);
    }
    .fb-message { font-size:14px; line-height:1.65; color:var(--text); }
    .fb-footer {
      padding:10px 18px;
      border-top:1px solid #f3f4f6;
      display:flex; align-items:center; justify-content:space-between;
    }
    .fb-time { font-size:12px; color:var(--text-muted); }
    .comment-toggle {
      background:none; border:none; font-size:12.5px; font-weight:600;
      color:var(--primary); cursor:pointer; display:flex; align-items:center; gap:5px;
      font-family:inherit;
    }
    .review-note {
      margin:0 18px 12px;
      background:#f0fdf4; border-left:3px solid #16a34a;
      padding:10px 14px; border-radius:0 8px 8px 0;
      font-size:12.5px; color:#166534;
    }
    .review-note strong { font-size:12px; display:block; margin-bottom:2px; }

    /* Comments section */
    .comments-section { padding:0 18px 16px; border-top:1px solid #f3f4f6; display:none; }
    .comments-section.open { display:block; }
    .comment-item {
      padding:10px 0; border-bottom:1px solid #f3f4f6;
      display:flex; gap:10px;
    }
    .comment-item:last-child { border-bottom:none; }
    .comment-avatar {
      width:28px; height:28px; border-radius:50%; flex-shrink:0;
      background:linear-gradient(135deg,#6366f1,#a855f7);
      display:flex; align-items:center; justify-content:center;
      font-size:11px; font-weight:700; color:#fff;
    }
    .comment-body { flex:1; }
    .comment-anon { font-size:11px; color:var(--text-muted); margin-bottom:3px; }
    .comment-text { font-size:13px; color:var(--text); line-height:1.5; }
    .comment-time { font-size:11px; color:var(--text-light); margin-top:3px; }
    .comment-form { display:flex; gap:8px; margin-top:12px; }
    .comment-input {
      flex:1; border:1px solid #e5e7eb; border-radius:20px;
      padding:8px 14px; font-size:13px; font-family:inherit;
      outline:none; transition:border-color 0.15s;
    }
    .comment-input:focus { border-color:var(--primary); }
    .comment-submit {
      background:var(--primary); color:#fff; border:none;
      border-radius:20px; padding:8px 16px; font-size:13px;
      font-weight:600; cursor:pointer; font-family:inherit;
    }

    /* My submissions badge */
    .my-badge {
      background:linear-gradient(135deg,#1a56db,#7e3af2);
      color:#fff; font-size:10px; font-weight:700;
      padding:2px 7px; border-radius:99px; letter-spacing:0.05em;
    }

    /* Empty */
    .feed-empty { text-align:center; padding:40px 20px; color:var(--text-muted); font-size:14px; }
  </style>
</head>
<body>
<div class="user-app">
  <!-- Top Nav -->
  <nav class="user-nav">
    <div class="user-nav-brand">
      <div class="nav-logo">💬</div>
      <div>
        <div class="nav-brand-text">NBSC Feedback</div>
        <div class="nav-brand-sub">Anonymous · Safe · Heard</div>
      </div>
    </div>
    <div class="nav-user">
      <div>
        <div class="nav-name"><?= sanitize($_SESSION['first_name'] . ' ' . $_SESSION['last_name']) ?></div>
        <div class="nav-role"><?= sanitize($_SESSION['department']) ?></div>
      </div>
      <div class="nav-avatar"><?= strtoupper(substr($_SESSION['first_name'],0,1)) ?></div>
      <a href="<?= BASE_URL ?>/app/auth/logout.php" class="btn btn-outline btn-sm" style="margin-left:4px;">Logout</a>
    </div>
  </nav>

  <div class="app-body">

    <?php if ($err): ?>
      <div class="alert alert-danger" style="border-radius:12px;margin-bottom:16px;"><?= sanitize($err) ?></div>
    <?php endif; ?>

    <!-- Submit Box -->
    <div class="submit-box">
      <div class="submit-box-header">
        <h2>Send Anonymous Feedback 💬</h2>
        <p>Your identity is fully protected. No personal data is linked to your submission.</p>
      </div>
      <div class="submit-box-body">
        <?php if ($submitted): ?>
          <div class="success-box">
            <div class="success-icon">✅</div>
            <h3>Feedback Sent!</h3>
            <p>Your anonymous feedback has been submitted successfully.<br>The admin team will review it shortly.</p>
            <button onclick="resetForm()" class="submit-btn" style="margin-top:16px;max-width:220px;">Send Another</button>
          </div>
        <?php else: ?>
          <form method="POST" id="feedbackForm">

            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Category</div>
            <div class="category-grid">
              <?php
              $cats = ['academic','facilities','services','faculty','administration','suggestion','complaint','general','other'];
              foreach ($cats as $cat):
              ?>
                <div class="cat-btn" onclick="selectCat('<?= $cat ?>')" id="cat-<?= $cat ?>">
                  <span class="cat-icon"><?= categoryIcon($cat) ?></span>
                  <?= categoryLabel($cat) ?>
                </div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="category" id="category-input" value="general">

            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Priority</div>
            <div class="priority-row">
              <?php foreach (['Low','Medium','High','Urgent'] as $p): ?>
                <div class="pri-btn" onclick="selectPri('<?= $p ?>')" id="pri-<?= $p ?>"><?= $p ?></div>
              <?php endforeach; ?>
            </div>
            <input type="hidden" name="priority" id="priority-input" value="Low">

            <div style="font-size:12px;font-weight:600;color:var(--text-muted);margin-bottom:8px;text-transform:uppercase;letter-spacing:0.05em;">Your Message</div>
            <textarea name="message" id="msg-input" class="msg-area" maxlength="200"
              placeholder="Describe your concern clearly. Avoid sharing personal details about yourself or others..." required
              oninput="updateCount()"></textarea>
            <div class="char-count"><span id="char-count">0</span>/200</div>

            // 2. this might also have a code to add feature //

            <button type="submit" name="submit_feedback" class="submit-btn">Send Anonymously 🔒</button>
          </form>
        <?php endif; ?>
      </div>
    </div>

    <!-- Feed -->
    <div class="feed-tabs">
      <button class="feed-tab active" onclick="showTab('all',this)">All Feedback</button>
      <button class="feed-tab" onclick="showTab('mine',this)">My Submissions</button>
    </div>

    <div id="tab-all">
      <?php if (empty($feedbacks)): ?>
        <div class="feed-empty">No feedback yet. Be the first to submit! 🚀</div>
      <?php else: foreach ($feedbacks as $fb):
        $isMine = in_array($fb['feedback_id'], $myIds);
      ?>
        <div class="fb-card">
          <div class="fb-card-top">
            <div class="fb-meta">
              <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
              <?= priorityBadge($fb['priority']) ?>
              <?= statusBadge($fb['status']) ?>
              <?php if ($isMine): ?><span class="my-badge">MINE</span><?php endif; ?>
            </div>
            <div class="fb-message"><?= sanitize($fb['message']) ?></div>
          </div>

          <?php if ($fb['status'] === 'resolved' && $fb['review_notes']): ?>
            <div class="review-note">
              <strong>✅ Admin Response</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php elseif ($fb['status'] === 'reviewed' && $fb['review_notes']): ?>
            <div class="review-note" style="background:#eff6ff;border-color:#3b82f6;color:#1e40af;">
              <strong>👀 Under Review</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php endif; ?>

          <div class="fb-footer">
            <span class="fb-time"><?= timeAgo($fb['submitted_at']) ?></span>
            <button class="comment-toggle" onclick="toggleComments(<?= $fb['feedback_id'] ?>)">
              💬 <?= $fb['comment_count'] ?> comment<?= $fb['comment_count']!=1?'s':'' ?>
            </button>
          </div>

          <!-- Comments -->
          <div class="comments-section" id="comments-<?= $fb['feedback_id'] ?>">
            <?php
              $coms = $pdo->prepare("SELECT * FROM comments WHERE feedback_id=? AND status='active' ORDER BY created_at ASC");
              $coms->execute([$fb['feedback_id']]);
              $coms = $coms->fetchAll();
              foreach ($coms as $c):
                $initials = strtoupper(substr($c['anonymous_id'],5,2));
            ?>
              <div class="comment-item">
                <div class="comment-avatar"><?= $initials ?></div>
                <div class="comment-body">
                  <div class="comment-anon"><?= sanitize($c['anonymous_id']) ?></div>
                  <div class="comment-text"><?= sanitize($c['content']) ?></div>
                  <div class="comment-time"><?= timeAgo($c['created_at']) ?></div>
                </div>
              </div>
            <?php endforeach; ?>

            <form method="POST" class="comment-form">
              <input type="hidden" name="feedback_id" value="<?= $fb['feedback_id'] ?>">
              <input type="text" name="comment_content" class="comment-input" placeholder="Add anonymous comment..." required>
              <button type="submit" name="post_comment" class="comment-submit">Post</button>
            </form>
          </div>
        </div>
      <?php endforeach; endif; ?>

      <!-- Pagination -->
      <?php if ($pages > 1): ?>
        <div class="pagination" style="justify-content:center;">
          <?php for ($p=1;$p<=$pages;$p++): ?>
            <a href="?page=<?= $p ?>" class="page-btn <?= $p==$feedPage?'active':'' ?>"><?= $p ?></a>
          <?php endfor; ?>
        </div>
      <?php endif; ?>
    </div>

    // This part must be fixed because after the user log out then logged in, his submissions are removed

    <div id="tab-mine" style="display:none;">
      <?php
      $myIds = $pdo->prepare("SELECT feedback_id FROM feedback WHERE user_id = ?");
$myIds->execute([$_SESSION['user_id']]);
$myIds = array_column($myIds->fetchAll(), 'feedback_id');

$mineList = [];
if (!empty($myIds)) {
    $ph = implode(',', array_fill(0, count($myIds), '?'));
    $s  = $pdo->prepare("SELECT f.*, 
        (SELECT COUNT(*) FROM comments c WHERE c.feedback_id=f.feedback_id AND c.status='active') AS comment_count,
        r.review_notes 
        FROM feedback f 
        LEFT JOIN feedback_reviews r ON f.feedback_id=r.feedback_id 
        WHERE f.feedback_id IN ($ph) 
        ORDER BY f.submitted_at DESC");
    $s->execute($myIds);
    $mineList = $s->fetchAll();
}
      ?>
      <?php if (empty($mineList)): ?>
        <div class="feed-empty">
          <div style="font-size:36px;margin-bottom:10px;">📭</div>
          No submissions yet in this session. Submit something above!
        </div>
      <?php else: foreach ($mineList as $fb): ?>
        <div class="fb-card">
          <div class="fb-card-top">
            <div class="fb-meta">
              <span class="fb-cat"><?= categoryIcon($fb['category']) ?> <?= sanitize(categoryLabel($fb['category'])) ?></span>
              <?= priorityBadge($fb['priority']) ?>
              <?= statusBadge($fb['status']) ?>
              <span class="my-badge">MINE</span>
            </div>
            <div class="fb-message"><?= sanitize($fb['message']) ?></div>
          </div>
          <?php if ($fb['review_notes']): ?>
            <div class="review-note">
              <strong>Admin Response</strong>
              <?= sanitize($fb['review_notes']) ?>
            </div>
          <?php endif; ?>
          <div class="fb-footer">
            <span class="fb-time"><?= timeAgo($fb['submitted_at']) ?></span>
            <span class="fb-time">💬 <?= $fb['comment_count'] ?> comment<?= $fb['comment_count']!=1?'s':'' ?></span>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>

  </div><!-- /app-body -->
</div><!-- /user-app -->

<script>
// Category select
let selectedCat = 'general';
document.getElementById('cat-general').classList.add('selected');
function selectCat(cat) {
  document.querySelectorAll('.cat-btn').forEach(b=>b.classList.remove('selected'));
  document.getElementById('cat-'+cat).classList.add('selected');
  document.getElementById('category-input').value = cat;
  selectedCat = cat;
}

// Priority select
let selectedPri = 'Low';
document.getElementById('pri-Low').classList.add('sel-Low');
function selectPri(pri) {
  document.querySelectorAll('.pri-btn').forEach(b=>{b.className='pri-btn';});
  document.getElementById('pri-'+pri).classList.add('sel-'+pri);
  document.getElementById('priority-input').value = pri;
  selectedPri = pri;
}

// Char count
function updateCount() {
  const len = document.getElementById('msg-input').value.length;
  document.getElementById('char-count').textContent = len;
}

// Reset form after submit
function resetForm() {
  window.location.href = '<?= BASE_URL ?>/app/user/index.php';
}

// Tabs
function showTab(tab, btn) {
  document.getElementById('tab-all').style.display  = tab==='all'  ? 'block' : 'none';
  document.getElementById('tab-mine').style.display = tab==='mine' ? 'block' : 'none';
  document.querySelectorAll('.feed-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
}

// Toggle comments
function toggleComments(id) {
  const el = document.getElementById('comments-'+id);
  el.classList.toggle('open');
}

</script>
</body>
</html>
