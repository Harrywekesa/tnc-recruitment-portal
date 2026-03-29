<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';

$pdo  = db();
$jobs_with_shortlist = $pdo->query("
    SELECT DISTINCT j.id, j.job_code, j.title, j.department
    FROM applications a JOIN jobs j ON a.job_id = j.id
    WHERE a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed', 'Hired')
    ORDER BY j.id
")->fetchAll();

$filter_job = (int)($_GET['job'] ?? 0);
$shortlisted = [];
if ($filter_job || !empty($jobs_with_shortlist)) {
    $job_id = $filter_job ?: ($jobs_with_shortlist[0]['id'] ?? 0);
    if ($job_id) {
        $stmt = $pdo->prepare("
            SELECT a.id_no, 
                   COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS full_name,
                   COALESCE(u.username, a.id_no) AS display_id,
                   COALESCE(u.sub_county, a.sub_county) AS sub_county,
                   COALESCE(u.ward, a.ward) AS ward
            FROM applications a 
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.job_id = ? AND a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed', 'Hired')
            ORDER BY full_name
        ");
        $stmt->execute([$job_id]);
        $shortlisted = $stmt->fetchAll();
    }
}

$page_title = 'Shortlists';
$active_nav = 'shortlist';
$root = '';
require_once __DIR__ . '/includes/partials/header.php';
?>
<div class="page-hdr">
  <div class="container">
    <div>
      <div class="breadcrumb"><a href="index.php">Home</a> <span>›</span> <span>Shortlists</span></div>
      <h1>Shortlisted Candidates</h1>
      <div class="page-hdr-sub">Official shortlists published by the County Government of Trans Nzoia</div>
    </div>
  </div>
</div>
<div class="page-wrap">
  <div class="container">
    <?php if (empty($jobs_with_shortlist)): ?>
      <div class="card card-pad" style="text-align:center;padding:60px;">
        <div style="font-size:40px;margin-bottom:14px;">📋</div>
        <div style="font-size:18px;font-weight:700;color:var(--green-800);margin-bottom:8px;">No shortlists published yet</div>
        <div style="color:var(--text-muted);">Shortlists will appear here once published by the Recruitment Office.</div>
      </div>
    <?php else: ?>
      <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:28px;">
        <?php foreach ($jobs_with_shortlist as $j): ?>
          <a href="shortlist.php?job=<?= $j['id'] ?>" class="btn <?= (!$filter_job&&$j===reset($jobs_with_shortlist))||$filter_job===$j['id']?'btn-green':'btn-outline-green' ?>">
            <?= h($j['title']) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <?php if (!empty($shortlisted)): ?>
      <div class="card fade-up">
        <div style="padding:20px 24px;border-bottom:1px solid var(--border);display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:12px;">
          <div style="font-family:var(--font-display);font-size:18px;font-weight:700;color:var(--green-900);">Official Shortlisted Candidates</div>
          <div style="display:flex; align-items:center; gap:12px;">
            <span class="badge badge-success"><?= count($shortlisted) ?> candidates</span>
            <a href="public_shortlist_print.php?job=<?= $job_id ?>" target="_blank" class="btn btn-outline-dark btn-sm" style="font-size:12px; padding:6px 14px;">📄 Download PDF Form</a>
          </div>
        </div>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th style="width:5%;">#</th>
                <th style="width:35%;">Applicant Name</th>
                <th style="width:20%;">National ID</th>
                <th style="width:20%;">Sub-County</th>
                <th style="width:20%;">Ward</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($shortlisted as $i => $a): 
                $id_display = (strlen((string)($a['display_id'] ?? '')) > 4) ? substr((string)$a['display_id'], 0, -4) . '****' : ($a['display_id'] ?? '—');
              ?>
              <tr>
                <td class="muted"><?= $i+1 ?></td>
                <td><strong style="color:var(--text-dark);"><?= h($a['full_name']) ?></strong></td>
                <td class="muted" style="font-family:var(--font-mono); letter-spacing:0.04em;"><?= h($id_display) ?></td>
                <td class="muted"><?= h($a['sub_county'] ?? '—') ?></td>
                <td class="muted"><?= h($a['ward'] ?? '—') ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>
</div>
<?php require_once __DIR__ . '/includes/partials/footer.php'; ?>
