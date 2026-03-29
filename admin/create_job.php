<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security failure: Invalid CSRF payload.";
    } else {
        $job_code = trim($_POST['job_code'] ?? '');
        $title = trim($_POST['title'] ?? '');
        $department = trim($_POST['department'] ?? '');
        $vacancies = (int)($_POST['vacancies'] ?? 1);
        $job_group = trim($_POST['job_group'] ?? '');
        $terms = trim($_POST['terms'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $requirements = trim($_POST['requirements'] ?? '');
        $duties = trim($_POST['duties'] ?? '');
        $scope = $_POST['scope'] ?? 'county_wide';
        $sub_county = trim($_POST['target_sub_county'] ?? '');
        $ward = trim($_POST['target_ward'] ?? '');
        $deadline = $_POST['deadline'] ?? '';

        if (!$title || !$job_code || !$department || !$deadline) {
            $errors[] = "Title, Job Code, Department, and explicitly set Deadline are rigorously required.";
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("INSERT INTO jobs (job_code, title, department, vacancies, job_group, terms, description, requirements, duties, target_sub_county, target_ward, scope, status, deadline, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Open',?,NOW())");
            try {
                $stmt->execute([$job_code, $title, $department, $vacancies, $job_group, $terms, $description, $requirements, $duties, $sub_county, $ward, $scope, $deadline]);
                $new_id = $pdo->lastInsertId();
                log_activity("Generated Single Specific Job Provision", "Job", (string)$new_id);
                header('Location: jobs.php?msg=job_created');
                exit;
            } catch (Exception $e) {
                $errors[] = "DB Persistence Error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Post New Job';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'jobs';
require_once __DIR__ . '/partials/admin_nav.php';
?>
<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container" style="max-width:800px;">
    
    <div style="margin-bottom:20px;">
      <a href="jobs.php" style="color:var(--text-muted); text-decoration:none;">← Back to Active Vacancies</a>
    </div>

    <div class="card card-pad fade-up">
      <h2 style="font-size:20px; font-weight:700; color:var(--green-900); margin-bottom:6px;">Post New Job Requisition</h2>
      <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">Deploy a new vacancy visibly reaching all resident candidate applicant portals instantly.</p>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" style="margin-bottom:24px;">
          <span class="alert-icon">⚠</span><div><?= implode('<br>', array_map('h', $errors)) ?></div>
        </div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <div class="form-row">
          <div class="form-group"><label class="form-label">Job Title *</label><input type="text" name="title" class="form-input" required></div>
          <div class="form-group"><label class="form-label">Job Code / Reference *</label><input type="text" name="job_code" class="form-input" required></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Target Department *</label>
            <select name="department" class="form-select" required>
              <option value="">-- Formal Division --</option>
              <?php foreach(TNC_DEPARTMENTS as $dept): ?>
                <option value="<?= h($dept) ?>"><?= h($dept) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Vacancies / Headcount</label><input type="number" name="vacancies" class="form-input" value="1" min="1"></div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Job Group / Grade</label>
            <select name="job_group" class="form-select">
              <option value="">-- Select Official Band --</option>
              <?php foreach(TNC_JOB_GROUPS as $jg): ?>
                <option value="<?= h($jg) ?>"><?= h($jg) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Terms of Service</label>
            <select name="terms" class="form-select">
              <option value="">-- Select Contract Term --</option>
              <?php foreach(TNC_TERMS as $term): ?>
                <option value="<?= h($term) ?>"><?= h($term) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group"><label class="form-label">Deadline *</label><input type="date" name="deadline" class="form-input" required></div>
        </div>

        <div style="background:#f9fafa; border:1px solid var(--border); padding:20px; border-radius:8px; margin-bottom:24px;">
          <h3 style="font-size:15px; margin-bottom:16px;">Target Accessibility Bounds</h3>
          <div class="form-group" style="margin-bottom:16px;">
            <label class="form-label">Selection Scope</label>
            <select name="scope" id="f_scope" class="form-select" onchange="toggleScope()">
              <option value="county_wide">County-Wide (Universal Access)</option>
              <option value="ward_specific">Ward Specific (Restricted Geographic Bound)</option>
            </select>
          </div>
          <div class="form-row" id="geo_bounds" style="display:none;">
            <div class="form-group"><label class="form-label">Target Sub-County</label><input type="text" name="target_sub_county" class="form-input" placeholder="e.g. Saboti"></div>
            <div class="form-group"><label class="form-label">Target Ward</label><input type="text" name="target_ward" class="form-input" placeholder="e.g. Kinyoro"></div>
          </div>
        </div>

        <div class="form-group"><label class="form-label">Job Description</label><textarea name="description" class="form-input" style="height:100px;"></textarea></div>
        <div class="form-group"><label class="form-label">Duties & Responsibilities</label><textarea name="duties" class="form-input" style="height:120px;" placeholder="Use HTML <ul> for bullets ideally if possible."></textarea></div>
        <div class="form-group" style="margin-bottom:32px;"><label class="form-label">Mandatory Job Requirements</label><textarea name="requirements" class="form-input" style="height:120px;"></textarea></div>

        <button type="submit" class="btn btn-green btn-lg" style="width:100%;">Create Live Job Post ✓</button>
      </form>
    </div>

  </div>
</div>

<script>
function toggleScope() {
    let s = document.getElementById('f_scope').value;
    document.getElementById('geo_bounds').style.display = (s === 'ward_specific') ? 'flex' : 'none';
}
toggleScope();
</script>
<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
