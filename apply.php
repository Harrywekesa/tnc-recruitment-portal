<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';

if (!is_logged_in() || !has_role('applicant')) {
    $next = urlencode($_SERVER['REQUEST_URI']);
    header("Location: /tnc-portal/applicant/login.php?next=$next");
    exit;
}

$job_id = (int)($_GET['job'] ?? 0);
$pdo    = db();
$job    = null;
$user   = auth_user();

if ($job_id) {
    $stmt = $pdo->prepare("SELECT * FROM jobs WHERE id = ? AND status = 'Open'");
    $stmt->execute([$job_id]);
    $job = $stmt->fetch();
}

if (!$job) {
    header('Location: /tnc-portal/jobs.php');
    exit;
}

$profile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$profile->execute([$user['id']]);
$profile = $profile->fetch();

if ($job['scope'] === 'ward_specific') {
    if ($profile['ward'] !== $job['target_ward'] || $profile['sub_county'] !== $job['target_sub_county']) {
        $page_title = 'Not Eligible';
        $root = '';
        require_once __DIR__ . '/includes/partials/header.php';
        echo '<div class="page-wrap"><div class="container" style="max-width:600px;">';
        echo '<div class="alert alert-danger"><span class="alert-icon">⛔</span><div>';
        echo '<strong>You are not eligible for this position.</strong><br>';
        echo 'This position is reserved for residents of <strong>'.h($job['target_ward']).' Ward</strong> in '.h($job['target_sub_county']).' Sub-County.<br>';
        echo 'Your registered ward is <strong>'.h($profile['ward'] ?? '—').'</strong>.';
        echo '</div></div><a href="jobs.php" class="btn btn-outline-green">Back to Vacancies</a></div></div>';
        require_once __DIR__ . '/includes/partials/footer.php';
        exit;
    }
}

$dup = $pdo->prepare("SELECT id FROM applications WHERE job_id = ? AND user_id = ?");
$dup->execute([$job_id, $user['id']]);
if ($dup->fetch()) {
    header('Location: /tnc-portal/applicant/dashboard.php?already_applied=1');
    exit;
}

// --- POST HANDLING ---
$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Invalid form submission. Please restart the application.";
    } else {
        $kism_no          = trim($_POST['kism_no'] ?? '');
        $experience       = trim($_POST['experience'] ?? '');
        $current_employer = trim($_POST['current_employer'] ?? '');
        
        $ref_no = strtoupper('TNC' . substr(bin2hex(random_bytes(3)), 0, 5));
        
        $cv_file   = $_FILES['doc_cv'] ?? null;
        $test_file = $_FILES['doc_testimonial'] ?? null;

        if (!$cv_file || $cv_file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "A valid CV / Resume is rigidly required.";
        }

        $qualifications_json = $_POST['qualifications'] ?? '[]';
        $qual_array = json_decode($qualifications_json, true) ?: [];

        $referees_json = $_POST['referees'] ?? '[]';
        $ref_array = json_decode($referees_json, true) ?: [];

        $clearances_json = $_POST['clearances'] ?? '[]';
        $clear_array = json_decode($clearances_json, true) ?: [];

        if (count($qual_array) < 1) $errors[] = "You must provide at least one listed Academic or Professional qualification.";
        if (count($ref_array) < 2) $errors[] = "You must provide at least two (2) referees.";

        $legacy_degree = '';
        $legacy_inst = '';
        $rank = ['PhD'=>8, "Master's Degree"=>7, "Bachelor's Degree"=>6, 'Higher Diploma'=>5, 'Diploma'=>4, 'Certificate'=>3, 'KCSE'=>2, 'KCPE'=>1];
        $highest_rank = 0;
        foreach ($qual_array as $q) {
            if ($q['type'] === 'Academic') {
                $cur_rank = $rank[$q['level']] ?? 0;
                if ($cur_rank >= $highest_rank) {
                    $highest_rank = $cur_rank;
                    $legacy_degree = $q['level'] . ' - ' . $q['title'];
                    $legacy_inst = $q['institution'];
                }
            }
        }
        
        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                $stmt = $pdo->prepare("
                    INSERT INTO applications 
                    (ref_no, job_id, user_id, degree, institution, kism_no, experience, current_employer, status, submitted_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Received', NOW())
                ");
                $stmt->execute([
                    $ref_no, $job_id, $user['id'],
                    $legacy_degree, $legacy_inst, $kism_no, $experience, $current_employer
                ]);
                $app_id = $pdo->lastInsertId();

                function save_doc($file, $type, $app_id, $pdo) {
                    if ($file['error'] !== UPLOAD_ERR_OK) return;
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    $filename = "{$app_id}_".bin2hex(random_bytes(4)).".$ext";
                    $dir = __DIR__ . '/uploads/applications';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    $path = "$dir/$filename";
                    
                    if (move_uploaded_file($file['tmp_name'], $path)) {
                        $stored_path = "uploads/applications/$filename";
                        $mime = mime_content_type($path);
                        $sz = $file['size'];
                        $ds = $pdo->prepare("INSERT INTO documents (application_id, doc_type, original_name, stored_name, filepath, file_size, mime_type) VALUES (?,?,?,?,?,?,?)");
                        $ds->execute([$app_id, $type, $file['name'], $filename, $stored_path, $sz, $mime]);
                    }
                }

                // Insert Referees
                $r_stmt = $pdo->prepare("INSERT INTO application_referees (application_id, name, designation, organization, phone, email) VALUES (?,?,?,?,?,?)");
                foreach ($ref_array as $r) {
                    $r_stmt->execute([$app_id, $r['name'], $r['designation'], $r['org'], $r['phone'], $r['email']]);
                }

                // Insert Qualifications & mapped certs
                $q_stmt = $pdo->prepare("INSERT INTO application_qualifications (application_id, type, level, title, institution, year_completed) VALUES (?,?,?,?,?,?)");
                foreach ($qual_array as $idx => $q) {
                    $q_stmt->execute([$app_id, $q['type'], $q['level'], $q['title'], $q['institution'], $q['year_completed']]);
                    
                    $fileID = 'qual_file_' . $idx;
                    if (isset($_FILES[$fileID]) && $_FILES[$fileID]['error'] === UPLOAD_ERR_OK) {
                        $clean = preg_replace('/[^A-Za-z0-9 ]/', '', substr($q['title'],0,40));
                        save_doc($_FILES[$fileID], "Certificate - " . $clean, $app_id, $pdo);
                    }
                }

                // Insert dynamic Clearances
                foreach ($clear_array as $idx => $c) {
                    $fileID = 'clear_file_' . $idx;
                    if (isset($_FILES[$fileID]) && $_FILES[$fileID]['error'] === UPLOAD_ERR_OK) {
                        save_doc($_FILES[$fileID], $c['type'], $app_id, $pdo);
                    }
                }

                // Static
                save_doc($cv_file, 'CV', $app_id, $pdo);
                if ($test_file && $test_file['error'] === UPLOAD_ERR_OK) {
                    save_doc($test_file, 'Testimonial', $app_id, $pdo);
                }

                $pdo->commit();
                header("Location: /tnc-portal/applicant/dashboard.php?applied=1");
                exit;

            } catch (Exception $e) {
                $pdo->rollBack();
                $errors[] = "System processing error: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'Apply — ' . $job['title'];
$active_nav = 'apply';
$root = '';
require_once __DIR__ . '/includes/partials/header.php';
?>

<div class="page-hdr">
  <div class="container">
    <div>
      <div class="breadcrumb"><a href="index.php">Home</a> <span>›</span> <a href="jobs.php">Vacancies</a> <span>›</span> <span>Apply</span></div>
      <h1>Apply: <?= h($job['title']) ?></h1>
      <div class="page-hdr-sub"><?= h($job['department']) ?> &nbsp;·&nbsp; <?= h($job['job_code']) ?></div>
    </div>
  </div>
</div>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container" style="max-width:800px;">
    
    <?php if ($errors): ?>
      <div class="alert alert-danger" style="margin-bottom:24px;">
        <span class="alert-icon">⚠</span>
        <div><strong>Validation Error</strong><br><?= implode('<br>', array_map('h', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <div class="card card-pad" style="padding:40px;">
      
      <!-- Wizard Header Status -->
      <div class="wizard-header">
        <div class="wizard-step active" id="st-1" data-step="1">
          <div class="wizard-step-circle">1</div>
          <div class="wizard-step-label">Personal Profile</div>
        </div>
        <div class="wizard-step" id="st-2" data-step="2">
          <div class="wizard-step-circle">2</div>
          <div class="wizard-step-label">Qualifications</div>
        </div>
        <div class="wizard-step" id="st-3" data-step="3">
          <div class="wizard-step-circle">3</div>
          <div class="wizard-step-label">Referees</div>
        </div>
        <div class="wizard-step" id="st-4" data-step="4">
          <div class="wizard-step-circle">4</div>
          <div class="wizard-step-label">Clearances</div>
        </div>
        <div class="wizard-step" id="st-5" data-step="5">
          <div class="wizard-step-circle">5</div>
          <div class="wizard-step-label">Review</div>
        </div>
      </div>

      <form method="POST" enctype="multipart/form-data" id="applicationForm">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        
        <!-- HIDDEN DOM VAULTS FOR CLONED FILES -->
        <div id="vault_certs" style="display:none;"></div>
        <div id="vault_clearances" style="display:none;"></div>

        <!-- STEP 1: Personal Profile -->
        <div class="wizard-panel active" id="panel-1">
          <h2 style="font-size:20px; color:var(--green-900); margin-bottom:6px;">Personal Information</h2>
          <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">This data is securely pulled from your vault profile and locked.</p>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Full Name</label><input type="text" class="form-input" value="<?= h($profile['full_name']) ?>" disabled></div>
            <div class="form-group"><label class="form-label">National ID Number</label><input type="text" class="form-input" value="<?= h($profile['username']) ?>" disabled></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Email Address</label><input type="text" class="form-input" value="<?= h($profile['email']) ?>" disabled></div>
            <div class="form-group"><label class="form-label">Phone Number</label><input type="text" class="form-input" value="<?= h($profile['phone'] ?? '—') ?>" disabled></div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Sub-County</label><input type="text" class="form-input" value="<?= h($profile['sub_county'] ?? '—') ?>" disabled></div>
            <div class="form-group"><label class="form-label">Ward</label><input type="text" class="form-input" value="<?= h($profile['ward'] ?? '—') ?>" disabled></div>
          </div>
          <div class="wizard-actions" style="justify-content:flex-end">
            <button type="button" class="btn btn-green" onclick="nextStep(2)">Next: Qualifications →</button>
          </div>
        </div>

        <!-- STEP 2: Qualifications -->
        <div class="wizard-panel" id="panel-2">
          <h2 style="font-size:20px; color:var(--green-900); margin-bottom:6px;">Academic & Professional Qualifications</h2>
          <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">Add your qualifications and directly attach the physical certificate providing proof for each.</p>
          
          <div style="background:#fcfcfc; border:1px solid var(--border); padding:20px; border-radius:8px; margin-bottom:16px;">
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Category</label>
                <select id="q_type" class="form-select" onchange="toggleQType()">
                  <option value="Academic">Academic</option>
                  <option value="Professional">Professional</option>
                </select>
              </div>
              <div class="form-group">
                <label class="form-label">Level / Certification</label>
                <select id="q_level" class="form-select"></select>
              </div>
            </div>
            <div class="form-row">
              <div class="form-group" style="flex:2">
                <label class="form-label" id="lbl_title">Course / Title (e.g., BSc Computer Science)</label>
                <input type="text" id="q_title" class="form-input">
              </div>
            </div>
            <div class="form-row">
              <div class="form-group" style="flex:2">
                <label class="form-label" id="lbl_inst">Institution / Examining Body</label>
                <input type="text" id="q_inst" class="form-input">
              </div>
              <div class="form-group" style="flex:1">
                <label class="form-label">Year</label>
                <input type="number" id="q_year" class="form-input" min="1950" max="<?= date('Y') ?>" value="<?= date('Y') ?>">
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" style="color:var(--green-800);">Attach Scanned Certificate (.pdf, .jpg) <span class="required">*</span></label>
              <input type="file" id="q_cert" accept=".pdf,.png,.jpg,.jpeg">
            </div>
            <button type="button" class="btn btn-outline-green btn-sm" onclick="addQualification()">+ Bind & Add Entry</button>
          </div>

          <div id="qualificationsList" style="display:flex; flex-direction:column; gap:8px; margin-bottom:24px;"></div>
          <input type="hidden" name="qualifications" id="f_qualifications" value="[]">

          <!-- Legacy track -->
          <div class="form-row" style="margin-top:16px;">
            <div class="form-group"><label class="form-label">Total Years Experience</label><input type="text" class="form-input" id="f_experience" name="experience" placeholder="e.g. 5 Years"></div>
            <div class="form-group"><label class="form-label">Current/Last Employer</label><input type="text" class="form-input" id="f_employer" name="current_employer" placeholder="e.g. Safaricom PLC"></div>
          </div>
          <?php if (stripos($job['department'], 'Procurement') !== false || stripos($job['title'], 'Supply') !== false): ?>
          <div class="form-group"><label class="form-label">KISM Registration No.</label><input type="text" class="form-input" name="kism_no"></div>
          <?php endif; ?>

          <div class="wizard-actions">
            <button type="button" class="btn btn-outline-dark" onclick="nextStep(1)">← Back</button>
            <button type="button" class="btn btn-green" onclick="nextStep(3)">Next: Referees →</button>
          </div>
        </div>

        <!-- STEP 3: Referees & Testimonials -->
        <div class="wizard-panel" id="panel-3">
          <h2 style="font-size:20px; color:var(--green-900); margin-bottom:6px;">Referees & Testimonials</h2>
          <p style="color:var(--text-muted); font-size:14px; margin-bottom:12px;">The panel strictly requires at least Two (2) professional or academic referees who can vouch for your integrity.</p>
          
          <div style="background:#fcfcfc; border:1px solid var(--border); padding:20px; border-radius:8px; margin-bottom:16px;">
            <div class="form-row">
              <div class="form-group"><label class="form-label">Referee Full Name</label><input type="text" id="r_name" class="form-input"></div>
              <div class="form-group"><label class="form-label">Phone Number</label><input type="text" id="r_phone" class="form-input"></div>
            </div>
            <div class="form-row">
              <div class="form-group"><label class="form-label">Organization / Company</label><input type="text" id="r_org" class="form-input"></div>
              <div class="form-group"><label class="form-label">Designation / Title</label><input type="text" id="r_desig" class="form-input"></div>
            </div>
            <div class="form-group"><label class="form-label">Email Address</label><input type="email" id="r_email" class="form-input"></div>
            <button type="button" class="btn btn-outline-green btn-sm" onclick="addReferee()">+ Add Referee</button>
          </div>

          <div id="refereesList" style="display:grid; grid-template-columns:1fr 1fr; gap:12px; margin-bottom:24px;"></div>
          <input type="hidden" name="referees" id="f_referees" value="[]">

          <hr style="border:0;border-top:1px solid var(--border);margin:24px 0;">

          <h2 style="font-size:16px; color:var(--green-900); margin-bottom:6px;">Testimonials (Optional)</h2>
          <p style="color:var(--text-muted); font-size:13px; margin-bottom:12px;">Merge any Certificates of Service or Reference Letters into a single PDF if available.</p>
          <input type="file" name="doc_testimonial" accept=".pdf">

          <div class="wizard-actions">
            <button type="button" class="btn btn-outline-dark" onclick="nextStep(2)">← Back</button>
            <button type="button" class="btn btn-green" onclick="nextStep(4)">Next: Clearances →</button>
          </div>
        </div>

        <!-- STEP 4: Chapter Six Clearances -->
        <div class="wizard-panel" id="panel-4">
          <h2 style="font-size:20px; color:var(--green-900); margin-bottom:6px;">Mandatory Documents & Chapter Six Compliance</h2>
          <p style="color:var(--text-muted); font-size:14px; margin-bottom:16px;">You must natively attach your comprehensive CV. Adding the Kenyan statutory clearances (EACC, KRA, HELB, etc.) is highly recommended for vetting.</p>
          
          <div class="form-group" style="background:#f4f5f5; border:1px solid var(--border); padding:20px; border-radius:8px;">
            <label class="form-label" style="font-size:15px;">Curriculum Vitae (CV) <span class="required">*</span></label>
            <p style="color:var(--text-muted); font-size:13px; margin-top:-4px; margin-bottom:12px;">Detailed CV highlighting your skills and timeline.</p>
            <input type="file" name="doc_cv" id="f_doc_cv" accept=".pdf,.png,.jpg,.jpeg">
          </div>

          <div style="background:#fcfcfc; border:1px solid var(--border); padding:20px; border-radius:8px; margin-bottom:16px;">
            <h3 style="font-size:15px; margin-bottom:12px;">Chapter Six Document Dropzone</h3>
            <div class="form-row">
              <div class="form-group">
                <label class="form-label">Clearance Type</label>
                <select id="c_type" class="form-select">
                  <option value="EACC Clearance">EACC Clearance</option>
                  <option value="KRA PIN Certificate">KRA PIN Certificate</option>
                  <option value="HELB Clearance Certificate">HELB Clearance Certificate</option>
                  <option value="DCI Certificate of Good Conduct">DCI Certificate of Good Conduct</option>
                  <option value="CRB Clearance">CRB Clearance</option>
                  <option value="Other Clearance">Other Certificate/Clearance</option>
                </select>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label" style="color:var(--green-800);">Attach PDF/Scan <span class="required">*</span></label>
              <input type="file" id="c_cert" accept=".pdf,.png,.jpg,.jpeg">
            </div>
            <button type="button" class="btn btn-outline-green btn-sm" onclick="addClearance()">+ Bind Clearance</button>
          </div>

          <div id="clearancesList" style="display:grid; grid-template-columns:repeat(auto-fill, minmax(200px, 1fr)); gap:12px; margin-bottom:24px;"></div>
          <input type="hidden" name="clearances" id="f_clearances" value="[]">

          <div class="wizard-actions">
            <button type="button" class="btn btn-outline-dark" onclick="nextStep(3)">← Back</button>
            <button type="button" class="btn btn-green" onclick="goToReview()">Final Review →</button>
          </div>
        </div>

        <!-- STEP 5: Review -->
        <div class="wizard-panel" id="panel-5">
          <h2 style="font-size:20px; color:var(--green-900); margin-bottom:6px;">Final Review</h2>
          <p style="color:var(--text-muted); font-size:14px; margin-bottom:24px;">Please review the generated payload footprint before triggering the encrypted database push.</p>

          <div style="background:#f4f5f5; border:1px solid var(--border); border-radius:8px; padding:24px; margin-bottom:24px;">
            <div class="wizard-summary-grid">
              <div class="wizard-summary-item"><div class="wizard-summary-lbl">Applicant Name</div><div class="wizard-summary-val"><?= h($profile['full_name']) ?></div></div>
              <div class="wizard-summary-item"><div class="wizard-summary-lbl">Target Job</div><div class="wizard-summary-val"><?= h($job['title']) ?> (<?= h($job['job_code']) ?>)</div></div>
              <div class="wizard-summary-item"><div class="wizard-summary-lbl">Qualifications Linked</div><div class="wizard-summary-val" id="rev_degree">—</div></div>
              <div class="wizard-summary-item"><div class="wizard-summary-lbl">Referees Linked</div><div class="wizard-summary-val" id="rev_referees">—</div></div>
              <div class="wizard-summary-item" style="grid-column: span 2;"><div class="wizard-summary-lbl">Clearances & Files Bound</div><div class="wizard-summary-val" id="rev_files">—</div></div>
            </div>
          </div>

          <div class="alert alert-warning">
            <span class="alert-icon">⚠</span>
            <div><strong>Declaration</strong><br>By clicking "Submit Application", you guarantee authencity. Falsification equates to immediate permanent disqualification.</div>
          </div>

          <div class="wizard-actions">
            <button type="button" class="btn btn-outline-dark" onclick="nextStep(4)">← Back</button>
            <button type="submit" class="btn btn-gold btn-lg" id="submitBtn">Submit Application ✓</button>
          </div>
        </div>

      </form>
    </div>
  </div>
</div>

<script>
let currentStep = 1;

// Lists
let qualifications = [];
let referees = [];
let clearances = [];

const qLevels = {
    'Academic': ['PhD', "Master's Degree", "Bachelor's Degree", 'Higher Diploma', 'Diploma', 'Certificate', 'KCSE', 'KCPE'],
    'Professional': ['CPA / CPS', 'CPA-K', 'ACCA', 'CIFA', 'KISM / CIPS', 'CCNA / Tech Cert', 'Registration with Professional Body', 'Other']
};

function toggleQType() {
    const type = document.getElementById('q_type').value;
    const levelSel = document.getElementById('q_level');
    levelSel.innerHTML = '';
    document.getElementById('lbl_title').textContent = (type === 'Academic') ? 'Course / Degree Title' : 'Certification Name';
    document.getElementById('lbl_inst').textContent = (type === 'Academic') ? 'Institution' : 'Examining Body';
    qLevels[type].forEach(l => {
        let opt = document.createElement('option');
        opt.value = opt.textContent = l;
        levelSel.appendChild(opt);
    });
}
toggleQType(); 

// ADD QUALIFICATION
function addQualification() {
    let t = document.getElementById('q_type').value;
    let l = document.getElementById('q_level').value;
    let title = document.getElementById('q_title').value.trim();
    let inst = document.getElementById('q_inst').value.trim();
    let y = document.getElementById('q_year').value.trim();
    let fileInput = document.getElementById('q_cert');
    
    if (!title || !inst || !y) { alert("Provide Title, Institution, and Year."); return; }
    if (!fileInput.files.length) { alert("Please attach the certificate explicitly proving this entry."); return; }
    
    let idx = qualifications.length;
    let newFile = fileInput.cloneNode(true);
    newFile.name = `qual_file_${idx}`;
    document.getElementById('vault_certs').appendChild(newFile);
    fileInput.value = ''; // clear UI clone logic
    
    qualifications.push({ type: t, level: l, title: title, institution: inst, year_completed: parseInt(y) });
    document.getElementById('q_title').value = '';
    document.getElementById('q_inst').value = '';
    renderQualifications();
}

function removeQualification(idx) {
    qualifications.splice(idx, 1);
    let v = document.getElementById('vault_certs');
    if (v.children[idx]) v.removeChild(v.children[idx]); // drop blob
    renderQualifications();
}

function renderQualifications() {
    const container = document.getElementById('qualificationsList');
    container.innerHTML = '';
    qualifications.forEach((q, idx) => {
        let div = document.createElement('div');
        div.style.cssText = 'padding:14px; border:1px solid var(--border); border-radius:6px; background:#fff; display:flex; justify-content:space-between; align-items:flex-start;';
        div.innerHTML = `<div>
            <div style="font-size:11px;font-weight:700;color:var(--green-700);">📎 CERT BOUND · ${q.type}</div>
            <div style="font-size:15px;font-weight:600;color:var(--green-900);margin:4px 0;">${q.title} (${q.year_completed})</div>
            <div style="font-size:13px;color:var(--text-muted);">${q.institution}</div>
        </div>
        <button type="button" class="btn btn-outline-dark btn-sm" onclick="removeQualification(${idx})">✖</button>`;
        container.appendChild(div);
    });
    document.getElementById('f_qualifications').value = JSON.stringify(qualifications);
}

// ADD REFEREE
function addReferee() {
    let name = document.getElementById('r_name').value.trim();
    let phone = document.getElementById('r_phone').value.trim();
    let org = document.getElementById('r_org').value.trim();
    let desig = document.getElementById('r_desig').value.trim();
    let email = document.getElementById('r_email').value.trim();

    if (!name || !phone || !org) { alert("Name, Phone, and Organization are mandatory for referees."); return; }

    referees.push({ name: name, phone: phone, org: org, designation: desig, email: email });
    document.getElementById('r_name').value = '';
    document.getElementById('r_phone').value = '';
    document.getElementById('r_org').value = '';
    document.getElementById('r_desig').value = '';
    document.getElementById('r_email').value = '';
    renderReferees();
}

function removeReferee(idx) { referees.splice(idx, 1); renderReferees(); }

function renderReferees() {
    const container = document.getElementById('refereesList');
    container.innerHTML = '';
    referees.forEach((r, idx) => {
        let div = document.createElement('div');
        div.style.cssText = 'padding:14px; border:1px solid var(--border); border-radius:6px; background:#fff; position:relative;';
        div.innerHTML = `
            <button type="button" class="btn btn-sm" style="position:absolute;top:6px;right:6px;background:none;border:none;font-size:12px;cursor:pointer;" onclick="removeReferee(${idx})">✖</button>
            <div style="font-size:14px;font-weight:700;color:var(--green-900);">${r.name}</div>
            <div style="font-size:12px;color:var(--text-light);margin-bottom:6px;">${r.designation} · ${r.org}</div>
            <div style="font-size:12px;color:var(--text-muted);">📞 ${r.phone}<br>✉️ ${r.email || 'N/A'}</div>
        `;
        container.appendChild(div);
    });
    document.getElementById('f_referees').value = JSON.stringify(referees);
}

// ADD CLEARANCE
function addClearance() {
    let type = document.getElementById('c_type').value;
    let fileInput = document.getElementById('c_cert');
    if (!fileInput.files.length) { alert("Please attach the clearance file."); return; }

    let idx = clearances.length;
    let newFile = fileInput.cloneNode(true);
    newFile.name = `clear_file_${idx}`;
    document.getElementById('vault_clearances').appendChild(newFile);
    fileInput.value = ''; 
    
    clearances.push({ type: type });
    renderClearances();
}

function removeClearance(idx) {
    clearances.splice(idx, 1);
    let v = document.getElementById('vault_clearances');
    if (v.children[idx]) v.removeChild(v.children[idx]);
    renderClearances();
}

function renderClearances() {
    const container = document.getElementById('clearancesList');
    container.innerHTML = '';
    clearances.forEach((c, idx) => {
        let div = document.createElement('div');
        div.style.cssText = 'padding:14px; border:1px solid var(--border); border-radius:6px; background:#fff; display:flex; justify-content:space-between; align-items:center;';
        div.innerHTML = `<span style="font-size:13px;font-weight:600;color:var(--green-800);">📄 ${c.type}</span>
        <button type="button" class="btn btn-sm" style="background:none;border:none;cursor:pointer;" onclick="removeClearance(${idx})">✖</button>`;
        container.appendChild(div);
    });
    document.getElementById('f_clearances').value = JSON.stringify(clearances);
}


// WIZARD
function nextStep(target) {
    if (currentStep === 2 && target === 3) {
        if (qualifications.length < 1) { alert("You must list at least one qualification with its bound certificate attached to proceed."); return; }
    }
    if (currentStep === 3 && target === 4) {
        if (referees.length < 2) { alert("You must provide at least two (2) referees to proceed."); return; }
    }
    
    document.getElementById('st-'+currentStep).classList.remove('active');
    document.getElementById('st-'+currentStep).classList.add('completed');
    document.getElementById('panel-'+currentStep).classList.remove('active');
    
    currentStep = target;
    for (let i=1; i<=5; i++) {
        let st = document.getElementById('st-'+i);
        if (i < currentStep) { st.classList.add('completed'); st.classList.remove('active'); } 
        else if (i === currentStep) { st.classList.remove('completed'); st.classList.add('active'); } 
        else { st.classList.remove('completed', 'active'); }
    }
    document.getElementById('panel-'+target).classList.add('active');
    window.scrollTo({ top: 100, behavior: 'smooth' });
}

function goToReview() {
    let cv = document.getElementById('f_doc_cv');
    if (!cv.files.length) { alert('Your CV is strictly required in the dropzone above.'); return; }
    
    document.getElementById('rev_degree').innerHTML = '<strong style="color:var(--green-600)">' + qualifications.length + ' item(s) bound</strong>';
    document.getElementById('rev_referees').innerHTML = '<strong style="color:var(--green-600)">' + referees.length + ' referees backed</strong>';
    
    let fcount = 1 + qualifications.length + clearances.length; // CV + bound certs + clearances
    document.getElementById('rev_files').textContent = fcount + ' physical files mapped for secure payload transfer.';
    
    nextStep(5);
}

document.getElementById('applicationForm').addEventListener('submit', function() {
    let btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.textContent = 'Encrypting & Submitting...';
});
</script>

<?php require_once __DIR__ . '/includes/partials/footer.php'; ?>
