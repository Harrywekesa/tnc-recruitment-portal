<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (verify_csrf($_POST['csrf_token'] ?? '')) {
        $app_id = (int)($_POST['app_id'] ?? 0);
        $job_id = (int)($_POST['job_id'] ?? 0);
        
        $mode = $_POST['mode'] ?? 'In-Person';
        $iv_date = $_POST['interview_date'] ?: null; // 'YYYY-MM-DD'
        $iv_time = $_POST['interview_time'] ?: null; // 'HH:MM'
        
        $venue_raw = trim($_POST['venue'] ?? '');
        $venue_other = trim($_POST['venue_other'] ?? '');
        $venue = ($venue_raw === 'Other') ? $venue_other : $venue_raw;
        
        $req_raw = trim($_POST['requirements'] ?? '');
        $req_other = trim($_POST['requirements_other'] ?? '');
        $reqs = ($req_raw === 'Other') ? $req_other : $req_raw;

        $panel = trim($_POST['panel_members'] ?? '');
        
        // Structural safety wall natively bounding SQL integrity
        if (!$iv_date || !$iv_time || !$venue) {
             header('Location: interviews.php?error=missing_fields');
             exit;
        }

        $chk = $pdo->prepare("SELECT id FROM interviews WHERE application_id = ?");
        $chk->execute([$app_id]);
        
        if ($chk->fetch()) {
            $upd = $pdo->prepare("UPDATE interviews SET interview_date=?, interview_time=?, venue=?, mode=?, requirements=?, panel_members=?, status='Scheduled' WHERE application_id=?");
            $upd->execute([$iv_date, $iv_time, $venue, $mode, $reqs, $panel, $app_id]);
        } else {
            $ins = $pdo->prepare("INSERT INTO interviews (application_id, job_id, interview_date, interview_time, venue, mode, requirements, panel_members, status, created_at) VALUES (?,?,?,?,?,?,?,?,'Scheduled',NOW())");
            $ins->execute([$app_id, $job_id, $iv_date, $iv_time, $venue, $mode, $reqs, $panel]);
        }
        
        // Progress status natively triggering Dashboard visibility block
        $uStatus = $pdo->prepare("UPDATE applications SET status='Interview Scheduled' WHERE id=? AND status='Shortlisted'");
        $uStatus->execute([$app_id]);

        log_activity("Scheduled Candidate Interview", "Interview", (string)$app_id);
        header('Location: interviews.php?msg=scheduled');
        exit;
    }
}

// Fetch candidate stack cleanly enforcing native framework sorting
$sql = "SELECT a.id AS app_id, a.job_id, a.status AS app_status, u.full_name, u.phone, u.email, j.title AS job_title, 
        i.interview_date, i.interview_time, i.venue, i.mode AS interview_mode, i.requirements, i.panel_members, i.status AS int_status 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN jobs j ON a.job_id = j.id
        LEFT JOIN interviews i ON a.id = i.application_id
        WHERE a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed')
        ORDER BY i.interview_date IS NULL ASC, i.interview_date ASC, a.submitted_at DESC";

$cands = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// Formal Dropdown Preset Options
$venuePresets = [
    'Trans Nzoia County Headquarters Boardroom',
    'Department of Health Services Complex',
    'County Public Service Board Offices',
    'Virtual Link Options Below',
    'Other'
];

$reqPresets = [
    'Original National ID, Academic Certificates & valid KRA PIN',
    'Original Testimonials & Clearance Certificates (EACC, KRA, DCI)',
    'Stable Internet Connection & Digital Portfolio (For Online Modalities)',
    'Other'
];

$page_title = 'Interview Coordination';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'interviews';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:24px; flex-wrap:wrap; gap:16px;">
      <h2 style="font-size:18px; font-weight:700; color:var(--green-900);">Interview Scheduling Hub</h2>
      <div style="font-size:13px; color:var(--text-muted); padding:8px 16px; background:#fff; border-radius:6px; border:1px solid var(--border);">
        Only formally <strong>Shortlisted</strong> candidates are explicitly routed into this pool.
      </div>
    </div>

    <?php if (isset($_GET['msg'])): ?>
      <div class="alert alert-success" style="margin-bottom:20px;"><span class="alert-icon">✓</span><div><strong>Logistics Synchronized</strong><br>The interview details were perfectly dispatched and will seamlessly appear directly on the candidate's personal dashboard immediately.</div></div>
    <?php endif; ?>

    <?php if (isset($_GET['error'])): ?>
      <div class="alert alert-danger" style="margin-bottom:20px;"><span class="alert-icon">⚠</span><div>Missing core scheduling logistics. Ensure Date, Time and Venue are absolutely populated structurally.</div></div>
    <?php endif; ?>

    <div class="card fade-up" style="overflow:hidden;">
      <div class="table-responsive" style="overflow-x:auto;">
        <table class="table" style="min-width:900px;">
          <colgroup>
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:20%;">
            <col style="width:40%;">
          </colgroup>
          <thead>
            <tr>
              <th>Shortlisted Candidate</th>
              <th>Target Vacancy</th>
              <th>Current Schedule Status</th>
              <th>Manage Dispatch Logistics</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($cands)): ?>
              <tr><td colspan="4" style="padding:40px; text-align:center; color:var(--text-muted);">No candidates possess a "Shortlisted" footprint in the primary queue yet.</td></tr>
            <?php else: ?>
              <?php foreach ($cands as $c): ?>
                <tr>
                  <!-- Column 1 -->
                  <td>
                    <div style="font-weight:600;color:var(--green-900);"><?= h($c['full_name']) ?></div>
                    <div style="font-size:12px;color:var(--text-light);"><?= h($c['phone'] ?: $c['email']) ?></div>
                    <a href="view_application.php?id=<?= $c['app_id'] ?>" style="font-size:11px; color:var(--gold); display:inline-block; margin-top:4px;">👁 View Profile</a>
                  </td>
                  
                  <!-- Column 2 -->
                  <td><div style="font-size:13px; color:var(--text-muted); line-height:1.4;"><?= h($c['job_title']) ?></div></td>
                  
                  <!-- Column 3 -->
                  <td>
                    <?php if($c['interview_date']): ?>
                      <span class="badge badge-success" style="margin-bottom:6px; display:inline-block;">Scheduled</span>
                      <div style="font-size:12px; color:var(--green-800); font-weight:600;"><?= date('M j, Y', strtotime($c['interview_date'])) ?> — <?= date('g:i A', strtotime($c['interview_time'])) ?></div>
                      <div style="font-size:11px; color:var(--text-light); margin-top:4px;"><strong><?= h($c['interview_mode']) ?></strong>: <?= h($c['venue']) ?></div>
                    <?php else: ?>
                      <span class="badge badge-warning">Pending Logistics Dispatch</span>
                    <?php endif; ?>
                  </td>
                  
                  <!-- Column 4 -->
                  <td>
                    <!-- Refactored Structural Sync Form block dynamically stacking elements strictly within table limits -->
                    <form method="POST" style="background:#f9fafa; padding:12px; border:1px solid var(--border); border-radius:6px; margin:0;">
                      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                      <input type="hidden" name="app_id" value="<?= $c['app_id'] ?>">
                      <input type="hidden" name="job_id" value="<?= $c['job_id'] ?>">
                      
                      <div style="display:grid; grid-template-columns:1fr 1fr; gap:8px; margin-bottom:8px;">
                          <!-- Date/Time Segment -->
                          <input type="date" class="form-input form-sm" name="interview_date" value="<?= h($c['interview_date']) ?>" required>
                          <input type="time" class="form-input form-sm" name="interview_time" value="<?= h($c['interview_time']) ?>" required>
                      </div>
                      
                      <div style="margin-bottom:8px;">
                          <!-- Mode Selection Core -->
                          <select name="mode" class="form-select form-sm" style="width:100%; margin-bottom:8px;">
                              <option value="In-Person" <?= $c['interview_mode']==='In-Person'?'selected':'' ?>>In-Person / Physical Location</option>
                              <option value="Virtual" <?= $c['interview_mode']==='Virtual'?'selected':'' ?>>Virtual / Online Video Link</option>
                          </select>
                          
                          <!-- Pre-selected Venue Map -->
                          <small style="color:var(--text-muted); font-size:11px; font-weight:600;">Venue / Meeting Link Dropdown:</small>
                          <select name="venue" class="form-select form-sm venue-drop" style="width:100%; margin-bottom:4px;" required onchange="vch(this)">
                              <option value="">-- Select Venue --</option>
                              <?php 
                                $v_match = false;
                                foreach($venuePresets as $v) {
                                    $sel = ($c['venue'] === $v) ? 'selected' : '';
                                    if ($sel) $v_match = true;
                                    echo "<option value=\"$v\" $sel>$v</option>";
                                }
                                if ($c['venue'] && !$v_match) {
                                    echo "<option value=\"Other\" selected>Other</option>";
                                }
                              ?>
                          </select>
                          <input type="text" name="venue_other" class="form-input form-sm venue-oth" placeholder="Paste custom link or address natively here..." value="<?= h($c['venue'] && !$v_match ? $c['venue'] : '') ?>" style="width:100%; display:<?= $c['venue'] && !$v_match ? 'block' : 'none' ?>;">
                      </div>

                      <div style="margin-bottom:8px;">
                          <!-- Pre-selected Applicant Requirements -->
                          <small style="color:var(--text-muted); font-size:11px; font-weight:600;">Requirements (What they must carry):</small>
                          <select name="requirements" class="form-select form-sm req-drop" style="width:100%; margin-bottom:4px;" onchange="rch(this)">
                              <option value="">-- Select Requirement Checklist --</option>
                              <?php 
                                $r_match = false;
                                foreach($reqPresets as $r) {
                                    $sel = ($c['requirements'] === $r) ? 'selected' : '';
                                    if ($sel) $r_match = true;
                                    echo "<option value=\"$r\" $sel>$r</option>";
                                }
                                if ($c['requirements'] && !$r_match) {
                                    echo "<option value=\"Other\" selected>Other</option>";
                                }
                              ?>
                          </select>
                          <input type="text" name="requirements_other" class="form-input form-sm req-oth" placeholder="Custom instructions structurally dictated..." value="<?= h($c['requirements'] && !$r_match ? $c['requirements'] : '') ?>" style="width:100%; display:<?= $c['requirements'] && !$r_match ? 'block' : 'none' ?>;">
                      </div>
                      
                      <div style="display:flex; gap:8px;">
                          <input type="text" name="panel_members" class="form-input form-sm" placeholder="Internal HR Panel (e.g. Panel A)" value="<?= h($c['panel_members']) ?>" style="flex:1;">
                          <button type="submit" class="btn btn-green btn-sm" style="flex:1; white-space:nowrap;">Sync to Dashboard ✓</button>
                      </div>
                    </form>
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

<script>
// Structural DOM logic dictating dynamic fallback native triggers
function vch(sel) {
    const oth = sel.nextElementSibling;
    if(sel.value === 'Other') {
        oth.style.display = 'block'; oth.required = true;
    } else {
        oth.style.display = 'none'; oth.required = false; oth.value = '';
    }
}
function rch(sel) {
    const oth = sel.nextElementSibling;
    if(sel.value === 'Other') {
        oth.style.display = 'block'; oth.required = true;
    } else {
        oth.style.display = 'none'; oth.required = false; oth.value = '';
    }
}
</script>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
