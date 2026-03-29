<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();
$status_filter = $_GET['status'] ?? '';
$job_filter = $_GET['job_id'] ?? '';

$sql = "SELECT a.id, a.ref_no, a.status, a.submitted_at, 
               COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS full_name, 
               COALESCE(u.email, a.email) AS email, 
               COALESCE(u.phone, a.phone) AS phone, 
               j.title AS job_title
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        JOIN jobs j ON a.job_id = j.id
        WHERE 1=1";
$params = [];

if ($status_filter) {
    $sql .= " AND a.status = ?";
    $params[] = $status_filter;
}
if ($job_filter) {
    $sql .= " AND a.job_id = ?";
    $params[] = $job_filter;
}
$sql .= " ORDER BY a.submitted_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$apps = $stmt->fetchAll(PDO::FETCH_ASSOC);

$jobs = $pdo->query("SELECT id, title FROM jobs ORDER BY title ASC")->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Applications Triaging';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'applications';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5;">
  <div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px;">
      <h2 style="font-size:18px; font-weight:700; color:var(--green-900);">Applicant Routing</h2>
      <form method="GET" style="display:flex; gap:12px;">
        <select name="job_id" class="form-select form-sm" onchange="this.form.submit()">
          <option value="">All Filtered Jobs</option>
          <?php foreach ($jobs as $j): ?>
            <option value="<?= $j['id'] ?>" <?= $job_filter==$j['id']?'selected':'' ?>><?= h($j['title']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="status" class="form-select form-sm" onchange="this.form.submit()">
          <option value="">All Status Routes</option>
          <?php foreach (APP_STATUSES as $s => $cfg): ?>
            <option value="<?= $s ?>" <?= $status_filter===$s?'selected':'' ?>><?= h($cfg['label']) ?></option>
          <?php endforeach; ?>
        </select>
      </form>
    </div>

    <div class="card fade-up" style="overflow:hidden;">
      <div class="table-responsive">
        <table class="table">
          <thead>
            <tr>
              <th>Ref No.</th>
              <th>Applicant Name</th>
              <th>Target Position</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Action Router</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($apps)): ?>
              <tr><td colspan="6" style="padding:40px; text-align:center; color:var(--text-muted);">No applications strictly returned by active dataset filters.</td></tr>
            <?php else: ?>
              <?php foreach ($apps as $a): 
                  $lbl = APP_STATUSES[$a['status']] ?? ['label'=>$a['status'], 'class'=>'badge-neutral'];
              ?>
                <tr>
                  <td style="font-family:monospace;font-weight:600;color:var(--text-muted);"><?= h($a['ref_no']) ?></td>
                  <td>
                    <div style="font-weight:600;color:var(--green-900);"><?= h($a['full_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-light);"><?= h($a['phone'] ?: $a['email']) ?></div>
                  </td>
                  <td><?= h($a['job_title']) ?></td>
                  <td style="font-size:13px; color:var(--text-muted);"><?= date('M j, Y, g:i a', strtotime($a['submitted_at'])) ?></td>
                  <td><span class="badge <?= $lbl['class'] ?>"><?= h($lbl['label']) ?></span></td>
                  <td>
                    <a href="view_application.php?id=<?= $a['id'] ?>" class="btn btn-outline-dark btn-sm">Deep Audit File →</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
