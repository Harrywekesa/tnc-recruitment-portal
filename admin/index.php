<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();
$pdo = db();
$stats = $pdo->query("SELECT
    (SELECT COUNT(*) FROM applications) AS total_apps,
    (SELECT COUNT(*) FROM applications WHERE status='Shortlisted') AS shortlisted,
    (SELECT COUNT(*) FROM jobs WHERE status='Open') AS open_jobs,
    (SELECT COUNT(*) FROM users WHERE role='applicant') AS applicants,
    (SELECT COUNT(*) FROM interviews WHERE status='Scheduled') AS interviews
")->fetch();
$page_title = 'HR Admin Dashboard';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>

<?php
$admin_page = 'index';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5;">
  <div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:24px;" class="fade-up">
      <h2 style="font-size:18px; font-weight:700; color:var(--green-900);">System Overview</h2>
      <div>
        <a href="jobs_bulk.php" class="btn btn-outline-dark btn-sm">Bulk Upload Jobs</a>
        <a href="create_job.php" class="btn btn-green btn-sm">+ Post New Job</a>
      </div>
    </div>

    <div class="stats-grid fade-up" style="margin-bottom:32px;">
      <div class="stat-card" onclick="window.location='applications.php'" style="cursor:pointer; transition:transform 0.2s;"><div class="stat-icon stat-icon-blue">📁</div><div><div class="stat-num"><?= $stats['total_apps'] ?></div><div class="stat-lbl">Total Applications</div></div></div>
      <div class="stat-card" onclick="window.location='applications.php?status=Shortlisted'" style="cursor:pointer; transition:transform 0.2s;"><div class="stat-icon stat-icon-green">⭐</div><div><div class="stat-num"><?= $stats['shortlisted'] ?></div><div class="stat-lbl">Shortlisted Candidates</div></div></div>
      <div class="stat-card" onclick="window.location='jobs.php'" style="cursor:pointer; transition:transform 0.2s;"><div class="stat-icon stat-icon-gold">💼</div><div><div class="stat-num"><?= $stats['open_jobs'] ?></div><div class="stat-lbl">Active Vacancies</div></div></div>
      <div class="stat-card"><div class="stat-icon stat-icon-purple">👥</div><div><div class="stat-num"><?= $stats['applicants'] ?></div><div class="stat-lbl">Registered Accounts</div></div></div>
    </div>
    
  </div>
</div>
<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
