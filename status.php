<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';

$result = null;
$error  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' || (isset($_GET['ref']) && isset($_GET['id']))) {
    $ref   = strtoupper(trim($_POST['ref']   ?? $_GET['ref']   ?? ''));
    $id_no = trim($_POST['id_no'] ?? $_GET['id'] ?? '');
    if ($ref && $id_no) {
        $pdo  = db();
        $stmt = $pdo->prepare("
            SELECT a.*, j.title AS job_title, j.department, j.job_code,
                   i.interview_date, i.interview_time, i.venue, i.mode AS interview_mode, i.requirements
            FROM applications a
            JOIN jobs j ON a.job_id = j.id
            LEFT JOIN interviews i ON i.application_id = a.id
            WHERE a.ref_no = ? AND a.id_no = ?
        ");
        $stmt->execute([$ref, $id_no]);
        $result = $stmt->fetch();
        if (!$result) $error = 'No application found with those details. Check your reference number and ID number.';
    }
}

$page_title = 'Check Application Status';
$active_nav = 'status';
$root = '';
require_once __DIR__ . '/includes/partials/header.php';
?>
<div class="page-hdr">
  <div class="container">
    <div>
      <div class="breadcrumb"><a href="index.php">Home</a> <span>›</span> <span>Check Status</span></div>
      <h1>Application Status</h1>
      <div class="page-hdr-sub">Track your application using your reference number and National ID</div>
    </div>
  </div>
</div>
<div class="page-wrap">
  <div class="container" style="max-width:700px;">
    <div class="card card-pad fade-up" style="margin-bottom:24px;">
      <form method="POST">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Reference Number</label>
            <input class="form-input" type="text" name="ref" placeholder="e.g. APP-001"
                   value="<?= h($_POST['ref'] ?? $_GET['ref'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">National ID Number</label>
            <input class="form-input" type="text" name="id_no" placeholder="e.g. 12345678"
                   value="<?= h($_POST['id_no'] ?? $_GET['id'] ?? '') ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-green">Check Status</button>
      </form>
    </div>
    <?php if ($error): ?>
      <div class="alert alert-danger"><span class="alert-icon">⚠</span><div><?= h($error) ?></div></div>
    <?php endif; ?>
    <?php if ($result):
      $cfg = APP_STATUSES[$result['status']] ?? ['label'=>$result['status'],'class'=>'badge-neutral'];
    ?>
    <div class="card card-pad fade-up">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:20px;">
        <div>
          <div style="font-family:var(--font-mono);font-size:11px;color:var(--text-light);margin-bottom:4px;"><?= h($result['job_code']) ?></div>
          <div style="font-family:var(--font-display);font-size:20px;font-weight:700;color:var(--green-900);"><?= h($result['job_title']) ?></div>
          <div style="font-size:13px;color:var(--text-muted);"><?= h($result['department']) ?></div>
        </div>
        <span class="badge <?= $cfg['class'] ?>" style="font-size:13px;padding:6px 16px;"><?= h($cfg['label']) ?></span>
      </div>
      <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;font-size:13.5px;">
        <div><span style="color:var(--text-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px;">Reference No.</span><strong style="font-family:var(--font-mono)"><?= h($result['ref_no']) ?></strong></div>
        <div><span style="color:var(--text-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px;">Submitted</span><?= date('d M Y', strtotime($result['submitted_at'])) ?></div>
        <div><span style="color:var(--text-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px;">Applicant</span><?= h($result['first_name'] . ' ' . $result['last_name']) ?></div>
        <div><span style="color:var(--text-light);font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;display:block;margin-bottom:3px;">Sub-County</span><?= h($result['sub_county'] . ' / ' . $result['ward']) ?></div>
      </div>
      <?php if ($result['interview_date']): ?>
      <div style="background:#f4ebf9; border:1px solid #d8b4e2; padding:16px; border-radius:8px; margin-top:20px;">
        <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
          <span style="font-size:20px;">📅</span>
          <h4 style="margin:0; font-size:15px; color:#6b21a8; font-weight:700;">Official Interview Scheduled</h4>
        </div>
        
        <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13.5px; color:var(--text-dark);">
          <div>
            <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Date & Time</span>
            <strong><?= date('l, d F Y', strtotime($result['interview_date'])) ?></strong><br>
            <?= date('g:i A', strtotime($result['interview_time'])) ?>
          </div>
          <div>
            <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Format</span>
            <span class="badge <?= $result['interview_mode'] === 'Virtual' ? 'badge-info' : 'badge-gold' ?>"><?= h($result['interview_mode']) ?></span>
          </div>
          
          <div style="grid-column: span 2;">
            <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">
              <?= $result['interview_mode'] === 'Virtual' ? 'Video Meeting Link' : 'Physical Venue Location' ?>
            </span>
            <?php if ($result['interview_mode'] === 'Virtual' && filter_var($result['venue'], FILTER_VALIDATE_URL)): ?>
                <a href="<?= h($result['venue']) ?>" target="_blank" style="color:#6b21a8; font-weight:600; text-decoration:underline;">Click Here to Join Meeting</a>
            <?php else: ?>
                <strong><?= h($result['venue']) ?></strong>
            <?php endif; ?>
          </div>
          
          <?php if ($result['requirements']): ?>
          <div style="grid-column: span 2; background:#fff; padding:10px 12px; border-radius:4px; border-left:3px solid #6b21a8; margin-top:4px;">
            <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Important Instructions</span>
            <?= h($result['requirements']) ?>
          </div>
          <?php endif; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/partials/footer.php'; ?>
