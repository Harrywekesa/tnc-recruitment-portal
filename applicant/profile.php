<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';

require_applicant();

$user = auth_user();
$pdo  = db();

// Fresh fetch to ensure we have the very latest data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user['id']]);
$profile = $stmt->fetch();

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors['general'] = "Invalid form submission. Please try again.";
    } else {
        $full_name  = trim($_POST['full_name'] ?? '');
        $dob        = trim($_POST['dob'] ?? '');
        $gender     = trim($_POST['gender'] ?? '');
        $phone      = trim($_POST['phone'] ?? '');
        $email      = trim($_POST['email'] ?? '');
        $sub_county = trim($_POST['sub_county'] ?? '');
        $ward       = trim($_POST['ward'] ?? '');
        
        $new_pass   = $_POST['new_password'] ?? '';
        
        if (empty($full_name)) $errors['full_name'] = "Full name is required.";
        if (empty($phone)) $errors['phone'] = "Phone number is required.";
        if (empty($email)) {
            $errors['email'] = "Email is required.";
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = "Enter a valid email address.";
        }
        if (empty($sub_county) || !array_key_exists($sub_county, SUB_COUNTIES)) $errors['sub_county'] = "Valid Sub-County is required.";
        if (empty($ward) || !in_array($ward, SUB_COUNTIES[$sub_county] ?? [], true)) $errors['ward'] = "Valid Ward is required.";

        if (!empty($new_pass) && strlen($new_pass) < 8) {
            $errors['new_password'] = "New password must be at least 8 characters.";
        }

        // Check Email uniqueness
        if (empty($errors['email']) && $email !== $profile['email']) {
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $profile['id']]);
            if ($chk->fetch()) $errors['email'] = "This email is already in use by another account.";
        }

        if (empty($errors)) {
            $sql = "UPDATE users SET full_name=?, dob=?, gender=?, phone=?, email=?, sub_county=?, ward=?";
            $params = [$full_name, $dob, $gender, $phone, $email, $sub_county, $ward];
            
            if (!empty($new_pass)) {
                $sql .= ", password_hash=?";
                $params[] = password_hash($new_pass, PASSWORD_BCRYPT, ['cost' => 12]);
            }
            
            $sql .= " WHERE id = ?";
            $params[] = $profile['id'];

            try {
                $upd = $pdo->prepare($sql);
                $upd->execute($params);
                $success = "Profile updated successfully.";
                
                // Refresh local profile variable
                $stmt->execute([$user['id']]);
                $profile = $stmt->fetch();
                
                // Keep session updated just in case
                login_user($profile);
                
            } catch (Exception $e) {
                $errors['general'] = "System error saving profile: " . $e->getMessage();
            }
        }
    }
}

$page_title = 'My Profile';
$active_nav = '';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-hdr">
  <div class="container">
    <div>
      <div class="breadcrumb">
        <a href="dashboard.php">My Dashboard</a> <span>›</span> <span>Edit Profile</span>
      </div>
      <h1>Account Settings</h1>
      <div class="page-hdr-sub">National ID: <?= h($profile['username']) ?> (Locked)</div>
    </div>
  </div>
</div>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:80px;">
  <div class="container" style="max-width:700px;">
    
    <?php if ($success): ?>
    <div class="alert alert-success fade-up" style="margin-bottom:24px;">
      <span class="alert-icon">✓</span><div><?= h($success) ?></div>
    </div>
    <?php endif; ?>
    
    <?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger fade-up" style="margin-bottom:24px;">
      <span class="alert-icon">⚠</span><div><?= h($errors['general']) ?></div>
    </div>
    <?php endif; ?>

    <form method="POST" novalidate id="profile-form">
      <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      
      <!-- Personal Details -->
      <div class="card card-pad fade-up" style="margin-bottom:20px;">
        <h2 style="font-size:16px;font-weight:700;color:var(--green-900);padding-bottom:10px;border-bottom:1px solid var(--border);margin-bottom:20px;">Personal Details</h2>
        
        <div class="form-group">
          <label class="form-label">Full Name <span class="required">*</span></label>
          <input type="text" name="full_name" class="form-input <?= isset($errors['full_name'])?'error':'' ?>" value="<?= h($_POST['full_name'] ?? $profile['full_name']) ?>">
          <?php if (isset($errors['full_name'])): ?><div class="form-error"><?= h($errors['full_name']) ?></div><?php endif; ?>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Date of Birth</label>
            <input type="date" name="dob" class="form-input <?= isset($errors['dob'])?'error':'' ?>" value="<?= h($_POST['dob'] ?? $profile['dob']) ?>">
            <?php if (isset($errors['dob'])): ?><div class="form-error"><?= h($errors['dob']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Gender</label>
            <select name="gender" class="form-select <?= isset($errors['gender'])?'error':'' ?>">
              <?php $g = $_POST['gender'] ?? $profile['gender']; ?>
              <option value="" <?= $g===''?'selected':'' ?>>— Select —</option>
              <option value="Male" <?= $g==='Male'?'selected':'' ?>>Male</option>
              <option value="Female" <?= $g==='Female'?'selected':'' ?>>Female</option>
              <option value="Prefer not to say" <?= $g==='Prefer not to say'?'selected':'' ?>>Prefer not to say</option>
            </select>
            <?php if (isset($errors['gender'])): ?><div class="form-error"><?= h($errors['gender']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Contact Info -->
      <div class="card card-pad fade-up" style="margin-bottom:20px;">
        <h2 style="font-size:16px;font-weight:700;color:var(--green-900);padding-bottom:10px;border-bottom:1px solid var(--border);margin-bottom:20px;">Contact & Location</h2>
        
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Email Address <span class="required">*</span></label>
            <input type="email" name="email" class="form-input <?= isset($errors['email'])?'error':'' ?>" value="<?= h($_POST['email'] ?? $profile['email']) ?>">
            <?php if (isset($errors['email'])): ?><div class="form-error"><?= h($errors['email']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Phone Number <span class="required">*</span></label>
            <input type="tel" name="phone" class="form-input <?= isset($errors['phone'])?'error':'' ?>" value="<?= h($_POST['phone'] ?? $profile['phone']) ?>">
            <?php if (isset($errors['phone'])): ?><div class="form-error"><?= h($errors['phone']) ?></div><?php endif; ?>
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Sub-County <span class="required">*</span></label>
            <select name="sub_county" id="sub_county" class="form-select <?= isset($errors['sub_county'])?'error':'' ?>" onchange="populateWards(this.value)">
              <option value="">— Select —</option>
              <?php 
              $sc_val = $_POST['sub_county'] ?? $profile['sub_county'];
              foreach (SUB_COUNTIES as $sc => $wards): ?>
                <option value="<?= h($sc) ?>" <?= $sc_val===$sc?'selected':'' ?>><?= h($sc) ?></option>
              <?php endforeach; ?>
            </select>
            <?php if (isset($errors['sub_county'])): ?><div class="form-error"><?= h($errors['sub_county']) ?></div><?php endif; ?>
          </div>
          <div class="form-group">
            <label class="form-label">Ward <span class="required">*</span></label>
            <select name="ward" id="ward" class="form-select <?= isset($errors['ward'])?'error':'' ?>">
              <option value="">— Select —</option>
              <?php 
              $w_val = $_POST['ward'] ?? $profile['ward'];
              if ($sc_val && array_key_exists($sc_val, SUB_COUNTIES)):
                foreach (SUB_COUNTIES[$sc_val] as $w): ?>
                  <option value="<?= h($w) ?>" <?= $w_val===$w?'selected':'' ?>><?= h($w) ?></option>
                <?php endforeach;
              endif; ?>
            </select>
            <?php if (isset($errors['ward'])): ?><div class="form-error"><?= h($errors['ward']) ?></div><?php endif; ?>
          </div>
        </div>
      </div>

      <!-- Security -->
      <div class="card card-pad fade-up" style="margin-bottom:24px;">
        <h2 style="font-size:16px;font-weight:700;color:var(--green-900);padding-bottom:10px;border-bottom:1px solid var(--border);margin-bottom:20px;">Security</h2>
        <div class="form-group">
          <label class="form-label">Change Password (Leave blank to keep current)</label>
          <input type="password" name="new_password" placeholder="New absolute password..." class="form-input <?= isset($errors['new_password'])?'error':'' ?>">
          <?php if (isset($errors['new_password'])): ?><div class="form-error"><?= h($errors['new_password']) ?></div><?php endif; ?>
        </div>
      </div>

      <!-- Submit -->
      <div class="fade-up">
        <button type="submit" class="btn btn-green btn-lg btn-full" id="saveBtn">Save Profile Modifications</button>
      </div>

    </form>
  </div>
</div>

<script>
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
document.getElementById('profile-form').addEventListener('submit', function() {
  document.getElementById('saveBtn').disabled = true;
  document.getElementById('saveBtn').textContent = 'Saving modifications...';
});
</script>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
