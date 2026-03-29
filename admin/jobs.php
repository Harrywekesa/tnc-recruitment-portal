<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $job_id = (int)($_POST['job_id'] ?? 0);
        $action = $_POST['action'];
        if ($action === 'toggle_status') {
            $stmt = $pdo->prepare("SELECT status FROM jobs WHERE id = ?");
            $stmt->execute([$job_id]);
            $current = $stmt->fetchColumn();
            if ($current) {
                $new_status = ($current === 'Open') ? 'Closed' : 'Open';
                $upd = $pdo->prepare("UPDATE jobs SET status = ? WHERE id = ?");
                $upd->execute([$new_status, $job_id]);
                log_activity("Toggled Master Job Status to [{$new_status}]", "Job", (string)$job_id);
                header('Location: jobs.php?msg=status_updated');
                exit;
            }
        } elseif ($action === 'delete') {
            // Check if there are applications
            $ck = $pdo->prepare("SELECT COUNT(*) FROM applications WHERE job_id = ?");
            $ck->execute([$job_id]);
            if ($ck->fetchColumn() > 0) {
                 header('Location: jobs.php?error=has_applications');
                 exit;
            }
            $del = $pdo->prepare("DELETE FROM jobs WHERE id = ?");
            $del->execute([$job_id]);
            log_activity("Permanently Terminated Vacancy", "Job", (string)$job_id);
            header('Location: jobs.php?msg=job_deleted');
            exit;
        }
    }
}

$search = $_GET['q'] ?? '';
$sql = "SELECT j.*, (SELECT COUNT(*) FROM applications WHERE job_id = j.id) AS app_count FROM jobs j WHERE 1=1";
$params = [];
if ($search) {
    $sql .= " AND (j.title LIKE ? OR j.job_code LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
$sql .= " ORDER BY j.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

$page_title = 'Jobs & Requisitions';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'jobs';
require_once __DIR__ . '/partials/admin_nav.php';
?>
<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container">
    
    <!-- Mobile-responsive wrapping header -->
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
      <h2 style="font-size:18px; font-weight:700; color:var(--green-900);">Active Job Vacancies</h2>
      
      <!-- Flex-wrap and gap enables native stacking on tiny mobile screens preventing right-side overflow -->
      <div style="display:flex; gap:12px; flex-wrap:wrap;">
        <form method="GET" style="display:flex; gap:8px;">
          <input type="text" name="q" placeholder="Search job title or code..." value="<?= h($search) ?>" class="form-input form-sm" style="width:250px; max-width: 100%;">
          <button type="submit" class="btn btn-outline-dark btn-sm">Search</button>
        </form>
        <a href="jobs_bulk.php" class="btn btn-outline-dark btn-sm">📥 CSV Bulk Upload</a>
        <a href="create_job.php" class="btn btn-green btn-sm">+ Post New Vacancy</a>
      </div>
    </div>

    <!-- Alert Messaging mapped to Standard Formal English -->
    <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success" style="margin-bottom:20px;"><span class="alert-icon">✓</span><div>Action completed successfully.</div></div>
    <?php endif; ?>
    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger" style="margin-bottom:20px;"><span class="alert-icon">⚠</span><div><strong>Action Prohibited</strong><br>Cannot delete this job because candidates have already applied! Consider closing the job instead.</div></div>
    <?php endif; ?>

    <!-- Wrapper strictly enforcing horizontal scroll safely keeping table entirely within viewport bounds -->
    <div class="card fade-up" style="overflow:hidden;">
      <div class="table-responsive" style="overflow-x:auto; -webkit-overflow-scrolling:touch;">
        <table class="table" style="min-width:800px;">
          <thead>
            <tr>
              <th>Job Code</th>
              <th>Position Title</th>
              <th>Department</th>
              <th>Applicants</th>
              <th>Scope</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($jobs)): ?>
              <tr><td colspan="7" style="padding:40px; text-align:center; color:var(--text-muted);">No job listings matched your search criteria.</td></tr>
            <?php else: ?>
              <?php foreach ($jobs as $j): ?>
                <tr>
                  <td style="font-family:monospace;font-weight:600;color:var(--text-muted);"><?= h($j['job_code']) ?></td>
                  <td style="font-weight:600;color:var(--green-900);"><?= h($j['title']) ?></td>
                  <td><?= h($j['department']) ?></td>
                  <td>
                    <?php if ($j['app_count'] > 0): ?>
                      <a href="applications.php?job_id=<?= $j['id'] ?>" class="badge badge-success" style="text-decoration:none;"><?= $j['app_count'] ?> Applications</a>
                    <?php else: ?>
                      <span class="badge badge-neutral">0 Applications</span>
                    <?php endif; ?>
                  </td>
                  <td style="font-size:12px;color:var(--text-light);">
                    <?= $j['scope']==='ward_specific' ? "Ward Bound<br><span style='color:var(--text-muted)'>".h($j['target_ward'])."</span>" : 'County-Wide' ?>
                  </td>
                  <td>
                    <?php if($j['status']==='Open'): ?>
                        <span class="badge badge-success" style="background:#def1d7;color:var(--green-800);">● Live</span>
                    <?php else: ?>
                        <span class="badge badge-warning">Closed</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <div style="display:flex;gap:8px;">
                      <form method="POST" style="margin:0;">
                         <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                         <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                         <input type="hidden" name="action" value="delete">
                         <button type="submit" class="btn btn-outline-dark btn-sm" style="color:#d9534f;border-color:transparent;" onclick="return confirm('Are you sure you want to delete this job posting? This cannot be undone.')">🗑</button>
                      </form>
                      <form method="POST" style="margin:0;">
                        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                        <input type="hidden" name="job_id" value="<?= $j['id'] ?>">
                        <input type="hidden" name="action" value="toggle_status">
                        <button type="submit" class="btn <?= $j['status']==='Open' ? 'btn-outline-danger' : 'btn-outline-green' ?> btn-sm" style="width:85px;">
                          <?= $j['status']==='Open' ? 'Close Job' : 'Re-open' ?>
                        </button>
                      </form>
                    </div>
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
