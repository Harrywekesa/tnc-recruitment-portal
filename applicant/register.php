<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';

// Already logged in — redirect
if (is_logged_in()) {
    header('Location: /tnc-portal/applicant/dashboard.php');
    exit;
}

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid form submission. Please try again.';
    } else {
        // Collect + sanitise
        $old = [
            'first_name' => trim($_POST['first_name'] ?? ''),
            'last_name'  => trim($_POST['last_name']  ?? ''),
            'id_no'      => trim($_POST['id_no']      ?? ''),
            'dob'        => trim($_POST['dob']         ?? ''),
            'gender'     => trim($_POST['gender']      ?? ''),
            'phone'      => trim($_POST['phone']       ?? ''),
            'email'      => trim($_POST['email']       ?? ''),
            'sub_county' => trim($_POST['sub_county']  ?? ''),
            'ward'       => trim($_POST['ward']        ?? ''),
            'password'   => $_POST['password']         ?? '',
            'password2'  => $_POST['password2']        ?? '',
        ];

        // Validate
        if (empty($old['first_name'])) $errors['first_name'] = 'First name is required.';
        if (empty($old['last_name']))  $errors['last_name']  = 'Last name is required.';

        if (empty($old['id_no'])) {
            $errors['id_no'] = 'National ID number is required.';
        } elseif (!preg_match('/^\d{7,9}$/', $old['id_no'])) {
            $errors['id_no'] = 'Enter a valid 7–9 digit ID number.';
        }

        if (empty($old['dob'])) {
            $errors['dob'] = 'Date of birth is required.';
        } else {
            $age = (int) date_diff(date_create($old['dob']), date_create())->y;
            if ($age < 18) $errors['dob'] = 'You must be at least 18 years old.';
            if ($age > 60) $errors['dob'] = 'Age must not exceed 60 years.';
        }

        if (empty($old['gender'])) $errors['gender'] = 'Please select your gender.';

        if (empty($old['phone'])) {
            $errors['phone'] = 'Phone number is required.';
        } elseif (!preg_match('/^(07|01|\+2547|\+2541)\d{8}$/', $old['phone'])) {
            $errors['phone'] = 'Enter a valid Kenyan phone number (e.g. 0712345678).';
        }

        if (empty($old['email'])) {
            $errors['email'] = 'Email address is required.';
        } elseif (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'Enter a valid email address.';
        }

        if (empty($old['sub_county'])) {
            $errors['sub_county'] = 'Please select your sub-county.';
        } elseif (!array_key_exists($old['sub_county'], SUB_COUNTIES)) {
            $errors['sub_county'] = 'Invalid sub-county selected.';
        }

        if (empty($old['ward'])) {
            $errors['ward'] = 'Please select your ward.';
        } elseif (!empty($old['sub_county']) && array_key_exists($old['sub_county'], SUB_COUNTIES)) {
            if (!in_array($old['ward'], SUB_COUNTIES[$old['sub_county']], true)) {
                $errors['ward'] = 'Selected ward does not belong to your sub-county.';
            }
        }

        if (strlen($old['password']) < 8) {
            $errors['password'] = 'Password must be at least 8 characters.';
        } elseif (!preg_match('/[A-Z]/', $old['password']) || !preg_match('/[0-9]/', $old['password'])) {
            $errors['password'] = 'Password must include at least one uppercase letter and one number.';
        }
        if ($old['password'] !== $old['password2']) {
            $errors['password2'] = 'Passwords do not match.';
        }

        // ID doc upload — front (required)
        $front_path = null;
        $back_path  = null;

        if (empty($_FILES['id_front']['name'])) {
            $errors['id_front'] = 'ID document front is required.';
        } else {
            $result = _save_id_doc($_FILES['id_front'], 'front');
            if ($result['error']) $errors['id_front'] = $result['error'];
            else $front_path = $result['path'];
        }

        if (empty($_FILES['id_back']['name'])) {
            $errors['id_back'] = 'ID document back is required.';
        } else {
            $result = _save_id_doc($_FILES['id_back'], 'back');
            if ($result['error']) $errors['id_back'] = $result['error'];
            else $back_path = $result['path'];
        }

        // DB uniqueness checks
        if (empty($errors)) {
            $pdo = db();
            $exists_email = $pdo->prepare("SELECT id FROM users WHERE email = ?");
            $exists_email->execute([$old['email']]);
            if ($exists_email->fetch()) $errors['email'] = 'An account with this email already exists.';

            $exists_id = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $exists_id->execute([$old['id_no']]);
            if ($exists_id->fetch()) $errors['id_no'] = 'An account with this ID number already exists.';
        }

        // Insert
        if (empty($errors)) {
            $pdo = db();
            $full_name = $old['first_name'] . ' ' . $old['last_name'];
            $hash      = password_hash($old['password'], PASSWORD_BCRYPT, ['cost' => 12]);

            $stmt = $pdo->prepare("
                INSERT INTO users
                  (username, email, password_hash, role, full_name, sub_county, ward, id_doc_front, id_doc_back, phone, dob, gender)
                VALUES (?, ?, ?, 'applicant', ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $old['id_no'], $old['email'], $hash,
                $full_name, $old['sub_county'], $old['ward'],
                $front_path, $back_path,
                $old['phone'], $old['dob'], $old['gender']
            ]);
            $user_id = (int) $pdo->lastInsertId();

            // Also store personal details in session after login
            $user = $pdo->prepare("SELECT * FROM users WHERE id = ?");
            $user->execute([$user_id]);
            login_user($user->fetch());

            $next = $_GET['next'] ?? 'applicant/dashboard.php';
            $next = ltrim($next, '/');
            header('Location: /tnc-portal/' . $next);
            exit;
        }
    }
}

function _save_id_doc(array $file, string $side): array
{
    $allowed_mime = ['image/jpeg','image/png','image/webp','application/pdf'];
    $max_size     = 5 * 1024 * 1024;

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['error' => 'Upload failed. Please try again.', 'path' => null];
    }
    if ($file['size'] > $max_size) {
        return ['error' => 'File too large. Maximum is 5MB.', 'path' => null];
    }
    $mime = mime_content_type($file['tmp_name']);
    if (!in_array($mime, $allowed_mime, true)) {
        return ['error' => 'Only JPG, PNG, WebP or PDF files are accepted.', 'path' => null];
    }

    $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'id_' . $side . '_' . bin2hex(random_bytes(8)) . '.' . strtolower($ext);
    $dir      = __DIR__ . '/../uploads/id_docs/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $dest = $dir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $dest)) {
        return ['error' => 'Could not save file. Contact support.', 'path' => null];
    }
    return ['error' => null, 'path' => 'uploads/id_docs/' . $filename];
}

// ── View ─────────────────────────────────────────────────────
$page_title = 'Create Applicant Account';
$active_nav = '';
$root       = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>

<div class="auth-wrap" style="align-items:flex-start;padding-top:40px;padding-bottom:60px;">
  <div style="width:100%;max-width:680px;margin:0 auto;">

    <!-- Progress indicator -->
    <div style="text-align:center;margin-bottom:28px;">
      <div style="font-size:12px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:var(--green-500);margin-bottom:6px;">New Applicant Account</div>
      <h1 style="font-family:var(--font-display);font-size:28px;font-weight:700;color:var(--green-900);margin-bottom:6px;">Create Your Account</h1>
      <p style="font-size:14px;color:var(--text-muted);">Fill in your details carefully. Your ward determines which ward-based positions you can apply for.</p>
    </div>

    <?php if (!empty($errors['general'])): ?>
      <div class="alert alert-danger" style="margin-bottom:20px;">
        <span class="alert-icon">⚠</span>
        <div><?= h($errors['general']) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" novalidate id="reg-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

      <!-- ── Personal Details ─────────────────────────────── -->
      <div class="card card-pad fade-up" style="margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--green-100);">
          1 — Personal Details
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="first_name">First Name <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['first_name'])?'error':'' ?>"
                   type="text" id="first_name" name="first_name"
                   value="<?= h($old['first_name'] ?? '') ?>" placeholder="e.g. Jane" autocomplete="given-name">
            <?php if (isset($errors['first_name'])): ?><div class="form-error"><?= h($errors['first_name']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="last_name">Last Name <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['last_name'])?'error':'' ?>"
                   type="text" id="last_name" name="last_name"
                   value="<?= h($old['last_name'] ?? '') ?>" placeholder="e.g. Wanjiku" autocomplete="family-name">
            <?php if (isset($errors['last_name'])): ?><div class="form-error"><?= h($errors['last_name']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="id_no">National ID Number <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['id_no'])?'error':'' ?>"
                   type="text" id="id_no" name="id_no"
                   value="<?= h($old['id_no'] ?? '') ?>" placeholder="e.g. 12345678"
                   inputmode="numeric" maxlength="9">
            <div class="form-hint">Your National ID number is your login username.</div>
            <?php if (isset($errors['id_no'])): ?><div class="form-error"><?= h($errors['id_no']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="dob">Date of Birth <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['dob'])?'error':'' ?>"
                   type="date" id="dob" name="dob"
                   value="<?= h($old['dob'] ?? '') ?>"
                   max="<?= date('Y-m-d', strtotime('-18 years')) ?>">
            <?php if (isset($errors['dob'])): ?><div class="form-error"><?= h($errors['dob']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="gender">Gender <span class="required">*</span></label>
            <select class="form-select <?= isset($errors['gender'])?'error':'' ?>" id="gender" name="gender">
              <option value="">— Select —</option>
              <option value="Male"              <?= ($old['gender']??'')==='Male'              ?'selected':'' ?>>Male</option>
              <option value="Female"            <?= ($old['gender']??'')==='Female'            ?'selected':'' ?>>Female</option>
              <option value="Prefer not to say" <?= ($old['gender']??'')==='Prefer not to say' ?'selected':'' ?>>Prefer not to say</option>
            </select>
            <?php if (isset($errors['gender'])): ?><div class="form-error"><?= h($errors['gender']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="phone">Phone Number <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['phone'])?'error':'' ?>"
                   type="tel" id="phone" name="phone"
                   value="<?= h($old['phone'] ?? '') ?>" placeholder="e.g. 0712345678"
                   inputmode="numeric" autocomplete="tel">
            <?php if (isset($errors['phone'])): ?><div class="form-error"><?= h($errors['phone']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label" for="email">Email Address <span class="required">*</span></label>
          <input class="form-input <?= isset($errors['email'])?'error':'' ?>"
                 type="email" id="email" name="email"
                 value="<?= h($old['email'] ?? '') ?>" placeholder="you@email.com" autocomplete="email">
          <div class="form-hint">Application updates and your reference number will be sent here.</div>
          <?php if (isset($errors['email'])): ?><div class="form-error"><?= h($errors['email']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- ── Location / Ward ──────────────────────────────── -->
      <div class="card card-pad fade-up" style="margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:6px;padding-bottom:10px;border-bottom:1px solid var(--green-100);">
          2 — Sub-County & Ward
        </div>
        <div class="alert alert-warning" style="margin-bottom:20px;">
          <span class="alert-icon">📍</span>
          <div>
            <strong>This determines your eligibility for ward-based positions.</strong>
            Select the sub-county and ward where you currently reside.
            You can apply for county-wide positions regardless of ward.
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="sub_county">Sub-County <span class="required">*</span></label>
            <select class="form-select <?= isset($errors['sub_county'])?'error':'' ?>"
                    id="sub_county" name="sub_county" onchange="populateWards(this.value)">
              <option value="">— Select Sub-County —</option>
              <?php foreach (SUB_COUNTIES as $sc => $wards): ?>
                <option value="<?= h($sc) ?>" <?= ($old['sub_county']??'')===$sc?'selected':'' ?>><?= h($sc) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['sub_county'])): ?><div class="form-error"><?= h($errors['sub_county']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="ward">Ward <span class="required">*</span></label>
            <select class="form-select <?= isset($errors['ward'])?'error':'' ?>"
                    id="ward" name="ward">
              <option value="">— Select Ward —</option>
              <?php
              // Re-populate ward options if sub-county was previously selected
              $prev_sc = $old['sub_county'] ?? '';
              if ($prev_sc && array_key_exists($prev_sc, SUB_COUNTIES)):
                foreach (SUB_COUNTIES[$prev_sc] as $w):
              ?>
                <option value="<?= h($w) ?>" <?= ($old['ward']??'')===$w?'selected':'' ?>><?= h($w) ?></option>
              <?php
                endforeach;
              endif;
              ?>
            </select>
            <div class="form-hint">Select sub-county first to load wards.</div>
            <?php if (isset($errors['ward'])): ?><div class="form-error"><?= h($errors['ward']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── ID Document Upload ───────────────────────────── -->
      <div class="card card-pad fade-up" style="margin-bottom:20px;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:6px;padding-bottom:10px;border-bottom:1px solid var(--green-100);">
          3 — National ID Document
        </div>
        <div class="alert alert-info" style="margin-bottom:20px;">
          <span class="alert-icon">ℹ</span>
          <div>Upload both sides of your National ID card or Passport.
          Accepted: JPG, PNG, WebP, PDF. Maximum 5MB per file.
          Documents are used for identity verification only.</div>
        </div>

        <div class="id-upload-grid">
          <!-- Front -->
          <div class="form-group">
            <label class="form-label">ID Front <span class="required">*</span></label>
            <div class="upload-zone <?= isset($errors['id_front'])?'error-zone':'' ?>" id="front-zone">
              <input type="file" name="id_front" accept="image/*,.pdf"
                     onchange="previewFile(this,'front-preview','front-zone')">
              <div class="upload-zone-icon">🪪</div>
              <div class="upload-zone-title">Front of ID</div>
              <div class="upload-zone-hint">Click or drag file here</div>
            </div>
            <div id="front-preview" class="upload-preview" style="display:none">
              <span>📄</span>
              <span class="upload-preview-name" id="front-name"></span>
              <button type="button" class="upload-preview-remove"
                      onclick="clearFile('id_front','front-preview','front-zone')">✕</button>
            </div>
            <?php if (isset($errors['id_front'])): ?><div class="form-error"><?= h($errors['id_front']) ?></div><?php endif; ?>
          </div>

          <!-- Back -->
          <div class="form-group">
            <label class="form-label">ID Back <span class="required">*</span></label>
            <div class="upload-zone <?= isset($errors['id_back'])?'error-zone':'' ?>" id="back-zone">
              <input type="file" name="id_back" accept="image/*,.pdf"
                     onchange="previewFile(this,'back-preview','back-zone')">
              <div class="upload-zone-icon">🪪</div>
              <div class="upload-zone-title">Back of ID</div>
              <div class="upload-zone-hint">Click or drag file here</div>
            </div>
            <div id="back-preview" class="upload-preview" style="display:none">
              <span>📄</span>
              <span class="upload-preview-name" id="back-name"></span>
              <button type="button" class="upload-preview-remove"
                      onclick="clearFile('id_back','back-preview','back-zone')">✕</button>
            </div>
            <?php if (isset($errors['id_back'])): ?><div class="form-error"><?= h($errors['id_back']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- ── Password ─────────────────────────────────────── -->
      <div class="card card-pad fade-up" style="margin-bottom:24px;">
        <div style="font-size:12px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:18px;padding-bottom:10px;border-bottom:1px solid var(--green-100);">
          4 — Set Password
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label" for="password">Password <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['password'])?'error':'' ?>"
                   type="password" id="password" name="password"
                   placeholder="Min. 8 characters" autocomplete="new-password">
            <div class="form-hint">Must include at least one uppercase letter and one number.</div>
            <?php if (isset($errors['password'])): ?><div class="form-error"><?= h($errors['password']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label" for="password2">Confirm Password <span class="required">*</span></label>
            <input class="form-input <?= isset($errors['password2'])?'error':'' ?>"
                   type="password" id="password2" name="password2"
                   placeholder="Repeat password" autocomplete="new-password">
            <?php if (isset($errors['password2'])): ?><div class="form-error"><?= h($errors['password2']) ?></div><?php endif; ?>
          </div>
        </div>

        <!-- Password strength bar -->
        <div id="pw-strength-wrap" style="display:none;margin-top:-8px;margin-bottom:8px">
          <div style="height:4px;background:var(--warm-100);border-radius:4px;overflow:hidden">
            <div id="pw-bar" style="height:100%;width:0;border-radius:4px;transition:all .3s"></div>
          </div>
          <div id="pw-label" style="font-size:11.5px;margin-top:5px;color:var(--text-light)"></div>
        </div>
      </div>

      <!-- Submit -->
      <div class="fade-up" style="margin-bottom:20px;">
        <div class="alert alert-warning" style="margin-bottom:16px;">
          <span class="alert-icon">⚠</span>
          <div>By creating an account you confirm that the information provided is accurate and truthful.
          Providing false information will lead to <strong>disqualification</strong>.</div>
        </div>
        <button type="submit" class="btn btn-green btn-lg btn-full" id="submit-btn">
          Create Account &amp; Continue
        </button>
      </div>

      <div style="text-align:center;font-size:13.5px;color:var(--text-muted);">
        Already have an account?
        <a href="<?= $root ?>applicant/login.php" style="color:var(--green-600);font-weight:600;">Sign in here</a>
      </div>
    </form>
  </div>
</div>

<script>
// ── Ward cascade ──────────────────────────────────────────────
const WARDS = <?= json_encode(SUB_COUNTIES, JSON_HEX_TAG | JSON_HEX_AMP) ?>;

function populateWards(sc) {
  const sel = document.getElementById('ward');
  sel.innerHTML = '<option value="">— Select Ward —</option>';
  if (!sc || !WARDS[sc]) return;
  WARDS[sc].forEach(w => {
    const opt = document.createElement('option');
    opt.value = w;
    opt.textContent = w;
    sel.appendChild(opt);
  });
}

// ── File upload preview ───────────────────────────────────────
function previewFile(input, previewId, zoneId) {
  const preview = document.getElementById(previewId);
  const nameEl  = document.getElementById(previewId.replace('-preview','-name'));
  if (!input.files.length) return;
  const file = input.files[0];
  if (nameEl) nameEl.textContent = file.name + ' (' + (file.size / 1024).toFixed(0) + ' KB)';
  preview.style.display = 'flex';
  document.getElementById(zoneId).style.borderStyle = 'solid';
  document.getElementById(zoneId).style.borderColor = 'var(--green-400)';
}

function clearFile(inputName, previewId, zoneId) {
  // Create a new input to clear the file
  const zone  = document.getElementById(zoneId);
  const old   = zone.querySelector('input[type="file"]');
  const clone = old.cloneNode(true);
  clone.onchange = old.onchange;
  old.replaceWith(clone);
  document.getElementById(previewId).style.display = 'none';
  zone.style.borderStyle = '';
  zone.style.borderColor = '';
}

// ── Drag & drop ───────────────────────────────────────────────
document.querySelectorAll('.upload-zone').forEach(zone => {
  zone.addEventListener('dragover', e => { e.preventDefault(); zone.classList.add('dragover'); });
  zone.addEventListener('dragleave', ()  => zone.classList.remove('dragover'));
  zone.addEventListener('drop', e => {
    e.preventDefault(); zone.classList.remove('dragover');
    const input = zone.querySelector('input[type="file"]');
    if (e.dataTransfer.files.length) {
      input.files = e.dataTransfer.files;
      input.dispatchEvent(new Event('change'));
    }
  });
});

// ── Password strength ─────────────────────────────────────────
const pwInput = document.getElementById('password');
pwInput.addEventListener('input', function() {
  const v = this.value;
  const wrap = document.getElementById('pw-strength-wrap');
  const bar  = document.getElementById('pw-bar');
  const lbl  = document.getElementById('pw-label');
  if (!v) { wrap.style.display = 'none'; return; }
  wrap.style.display = 'block';

  let score = 0;
  if (v.length >= 8)  score++;
  if (v.length >= 12) score++;
  if (/[A-Z]/.test(v)) score++;
  if (/[0-9]/.test(v)) score++;
  if (/[^A-Za-z0-9]/.test(v)) score++;

  const levels = [
    { w:'20%', c:'#E24B4A', t:'Too weak' },
    { w:'40%', c:'#E0AC0C', t:'Weak' },
    { w:'60%', c:'#E0AC0C', t:'Fair' },
    { w:'80%', c:'#3DA06F', t:'Good' },
    { w:'100%',c:'#1A7048', t:'Strong' },
  ];
  const lvl = levels[Math.min(score, 4)];
  bar.style.width      = lvl.w;
  bar.style.background = lvl.c;
  lbl.textContent      = lvl.t;
  lbl.style.color      = lvl.c;
});

// ── Client-side submit guard ──────────────────────────────────
document.getElementById('reg-form').addEventListener('submit', function() {
  document.getElementById('submit-btn').disabled = true;
  document.getElementById('submit-btn').textContent = 'Creating account…';
});
</script>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
