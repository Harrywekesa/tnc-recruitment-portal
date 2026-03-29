<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();
$errors = [];
$success = 0;

// Handle actual database commit from the Preview phase
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_upload'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security failure: Invalid CSRF payload on final commit.";
    } else if (empty($_SESSION['csv_preview_data'])) {
        $errors[] = "Session payload expired or cleared. Please comprehensively re-upload the file.";
    } else {
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare("INSERT INTO jobs (job_code, title, department, vacancies, job_group, terms, description, requirements, duties, scope, target_sub_county, target_ward, status, deadline, created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,'Open',?,NOW())");
            
            $dataset = $_SESSION['csv_preview_data'];
            foreach ($dataset as $row) {
                // row array structurally matching bindings exactly
                $stmt->execute($row);
                $success++;
            }
            $pdo->commit();
            log_activity("Committed Extensive CSV Matrix identical to {$success} provision lines", "Job", "BULK");
            unset($_SESSION['csv_preview_data']); // clear isolated payload
        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = "Fatal Rollback Error during SQL transaction runtime. Trace: " . $e->getMessage();
        }
    }
}
// Handle initial File Upload & generate structural Snapshot
else if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security failure: Invalid CSRF form payload.";
    } else if ($_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['csv_file']['tmp_name'];
        if (($handle = fopen($file, "r")) !== FALSE) {
            $header = fgetcsv($handle, 1000, ",");
            if (!$header || count($header) < 13) {
                $errors[] = "Invalid CSV structure matrix. Explicitly ensure you are utilizing the active strict template below possessing 13 mapped columns.";
            } else {
                $parsed_rows = [];
                while (($data = fgetcsv($handle, 8000, ",")) !== FALSE) {
                    if (count($data) < 13) continue;
                    $job_code = trim($data[0]);
                    $title = trim($data[1]);
                    $dept = trim($data[2]);
                    
                    // Skip completely empty baseline rows or raw headers
                    if (!$job_code || !$title || $job_code === 'Job_Code') continue; 

                    $vacs = (int)$data[3] ?: 1;
                    $group = trim($data[4]);
                    $terms = trim($data[5]);
                    $desc = trim($data[6]);
                    $req = trim($data[7]);
                    $duties = trim($data[8]);
                    $scope = strtolower(trim($data[9])) === 'ward' ? 'ward_specific' : 'county_wide';
                    $sub = trim($data[10]);
                    $ward = trim($data[11]);
                    
                    // Robust Date Parsing explicitly preventing PHP TypeError
                    $raw_date = trim($data[12]);
                    $stamp = strtotime($raw_date);
                    $deadline = ($raw_date && $stamp !== false) ? date('Y-m-d', $stamp) : date('Y-m-d', strtotime('+30 days'));

                    $parsed_rows[] = [$job_code, $title, $dept, $vacs, $group, $terms, $desc, $req, $duties, $scope, $sub, $ward, $deadline];
                }
                
                if (empty($parsed_rows)) {
                    $errors[] = "The CSV processed securely but zero valid structural rows were mapped inside it.";
                } else {
                    // Safely store in state securely avoiding temp file clashes
                    $_SESSION['csv_preview_data'] = $parsed_rows;
                }
            }
            fclose($handle);
        }
    } else {
        $errors[] = "Physical CSV payload structurally missing or critically corrupted.";
    }
} else if (isset($_GET['cancel'])) {
    unset($_SESSION['csv_preview_data']);
    header('Location: jobs_bulk.php');
    exit;
}

if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="TNC_Jobs_Template.csv"');
    echo "Job_Code,Title,Department,Vacancies,Job_Group,Terms,Description,Requirements,Duties,Scope(County/Ward),SubCounty,Ward,Deadline(YYYY-MM-DD)\n";
    echo "TNC-001,Senior Civil Engineer,Public Works,1,CPSB 10,Permanent,Build roads.,Civil Degree,Construction planning,County,,,2026-12-31\n";
    echo "TNC-002,Ward Administrator,Administration,1,CPSB 11,Contract,Manage ward affairs.,Diploma Admin,Oversee development,Ward,Saboti,Matisi,2026-12-31\n";
    exit;
}

$page_title = 'CSV Bulk Upload';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'jobs';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container" style="max-width:1100px;">
    
    <div style="margin-bottom:20px;">
      <a href="jobs.php" style="color:var(--text-muted); text-decoration:none;">← Back to Active Vacancies</a>
    </div>

    <!-- 1. ERRORS AND SUCCESS ALERTS -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" style="margin-bottom:24px;">
        <span class="alert-icon">⚠</span><div><?= implode('<br>', array_map('h', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($success > 0): ?>
      <div class="alert alert-success" style="margin-bottom:24px;">
        <span class="alert-icon">✓</span><div><strong>Database Commit Successful</strong><br>Successfully ingested precisely <?= $success ?> mapped vacancies directly into the live schema!</div>
      </div>
    <?php endif; ?>

    <!-- 2. SNAPSHOT PREVIEW STATE -->
    <?php if (!empty($_SESSION['csv_preview_data']) && $success === 0): ?>
      <?php $preview = $_SESSION['csv_preview_data']; ?>
      
      <div class="card card-pad fade-up" style="border:2px solid var(--gold);">
        <h2 style="font-size:20px; font-weight:700; color:var(--green-900); margin-bottom:6px;">Data Snapshot Intercepted</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">Please critically review the snapshot natively extracted from your uploaded file before explicitly committing it to the database.</p>

        <div style="margin-bottom:20px; font-weight:600; color:var(--green-800);">
          Total Natively Parsed Valid Vacancies: <?= count($preview) ?>
        </div>
        
        <div class="table-responsive" style="max-height:400px; overflow-y:auto; border:1px solid var(--border); border-radius:6px; margin-bottom:24px; background:#fff;">
          <table class="table" style="font-size:13px; margin:0; white-space:nowrap;">
            <thead style="position:sticky; top:0; background:#f9fafa; z-index:10; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
              <tr>
                <th>Job Code</th>
                <th>Title</th>
                <th>Department</th>
                <th>Vacs.</th>
                <th>Scope</th>
                <th>Ward/Sub</th>
                <th>Deadline</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach (array_slice($preview, 0, 50) as $i => $r): ?>
                <tr>
                  <td style="font-family:monospace;"><?= h($r[0]) ?></td>
                  <td style="font-weight:600; color:var(--green-900);"><?= h($r[1]) ?></td>
                  <td><?= h($r[2]) ?></td>
                  <td><?= h((string)$r[3]) ?></td>
                  <td><?= h($r[9]) ?></td>
                  <td><?= $r[9]==='ward_specific'? h($r[10]).' - '.h($r[11]) : '—' ?></td>
                  <td style="color:var(--text-muted);"><?= h($r[12]) ?></td>
                </tr>
              <?php endforeach; ?>
              <?php if(count($preview) > 50): ?>
                <tr><td colspan="7" style="text-align:center; padding:16px; background:#f9fafa; color:var(--text-muted);">... plus <?= count($preview)-50 ?> more rows truncated from preview.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <div style="display:flex; justify-content:space-between; align-items:center; background:#f9fafa; padding:20px; border-radius:8px; border:1px solid var(--border);">
          <a href="?cancel=1" class="btn btn-outline-dark">Cancel Upload</a>
          <form method="POST" style="margin:0;">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            <input type="hidden" name="confirm_upload" value="1">
            <button type="submit" class="btn btn-gold btn-lg" style="font-weight:700;">Confirm & Bulk Insert <?= count($preview) ?> Roles ✓</button>
          </form>
        </div>
      </div>


    <!-- 3. DEFAULT UPLOAD STATE -->
    <?php else: ?>
      
      <div class="card card-pad fade-up" style="max-width:800px; margin:0 auto;">
        <h2 style="font-size:20px; font-weight:700; color:var(--green-900); margin-bottom:6px;">CSV Multi-Vacancy Engine</h2>
        <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">Automatically ingest massive HR blocks identically parsing strictly to our master constraints natively executing within a secure payload snapshot loop.</p>

        <div style="background:#f9fafa; border:1px solid var(--border); padding:24px; border-radius:8px; margin-bottom:32px;">
          <h3 style="font-size:16px; margin-bottom:12px;">Step 1: Download Explicit Template</h3>
          <p style="font-size:13px; color:var(--text-muted); margin-bottom:16px;">The transaction processor strictly expects precisely 13 header columns spanning from `Job_Code` to `Deadline`. Please seamlessly download the exact native template before submitting.</p>
          <a href="?download_template=1" class="btn btn-outline-dark btn-sm">Download Blank Native Template (.csv)</a>
        </div>

        <div style="border:1px solid var(--border); padding:24px; border-radius:8px;">
          <h3 style="font-size:16px; margin-bottom:12px;">Step 2: Database Payload Upload</h3>
          <p style="font-size:13px; color:var(--text-muted); margin-bottom:24px;">We will cleanly intercept the provided `.csv` dropping it natively to our Preview Table. You can extensively review the parameters directly before finalizing the SQL commit locally!</p>
          
          <form method="POST" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
            
            <div class="form-group" style="padding:40px; border:2px dashed var(--border); border-radius:8px; text-align:center; background:#fdfdfd; margin-bottom:24px;">
              <div style="font-size:32px; margin-bottom:12px;">📁</div>
              <input type="file" name="csv_file" accept=".csv" required style="margin-bottom:12px; font-weight:600;"><br>
              <span style="font-size:12px; color:var(--text-light);">Maximum dynamically parsed chunk size array: 8MB.</span>
            </div>

            <button type="submit" class="btn btn-green btn-lg" style="width:100%;">Upload Secure Matrix payload ✓</button>
          </form>
        </div>
      </div>
    
    <?php endif; ?>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
