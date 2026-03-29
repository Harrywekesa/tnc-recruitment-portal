<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_superadmin();

$pdo = db();
$errors = [];
$msg = $_GET['msg'] ?? '';

// Handle actions (Create Admin, Suspend User, etc)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $errors[] = "Security Token Validation Failed. Action aborted.";
    } else if (isset($_POST['action'])) {
        $action = $_POST['action'];
        
        // 1. CREATE STAFF
        if ($action === 'create_staff') {
            $name = trim($_POST['full_name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $role = $_POST['role'] ?? 'hr';
            $pwd = $_POST['password'] ?? '';
            
            if (!$name || !$email || !$pwd || !in_array($role, ['hr','admin','superadmin'])) {
                $errors[] = "Invalid parameters provided for new staff account.";
            } else {
                $chk = $pdo->prepare("SELECT id FROM users WHERE email=? OR username=?");
                $chk->execute([$email, $email]);
                if ($chk->fetch()) {
                    $errors[] = "A user natively tracking this exact email address already exists.";
                } else {
                    $hash = password_hash($pwd, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("INSERT INTO users (full_name, email, username, password_hash, role) VALUES (?,?,?,?,?)");
                    $ins->execute([$name, $email, $email, $hash, $role]);
                    $new_id = $pdo->lastInsertId();
                    log_activity('System Staff Account Provisioned', 'User', (string)$new_id);
                    header("Location: users.php?msg=staff_created");
                    exit;
                }
            }
        }
        
        // 2. DELETE USER
        else if ($action === 'delete_user') {
            $uid = (int)($_POST['user_id'] ?? 0);
            if ($uid === auth_user()['id']) {
                $errors[] = "You maliciously attempted to irrevocably delete your own root account. Operation halted.";
            } else {
                $del = $pdo->prepare("DELETE FROM users WHERE id=?");
                $del->execute([$uid]);
                log_activity('User Account Physically Terminated', 'User', (string)$uid);
                header("Location: users.php?msg=deleted");
                exit;
            }
        }
        
        // 3. FORCE PASSWORD RESET
        else if ($action === 'reset_password') {
            $uid = (int)($_POST['user_id'] ?? 0);
            $new_pwd = trim($_POST['new_password'] ?? '');
            if (!$new_pwd) {
                $errors[] = "Password payload cannot be explicitly blank.";
            } else {
                $hash = password_hash($new_pwd, PASSWORD_DEFAULT);
                $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?")->execute([$hash, $uid]);
                log_activity('Force Password Reset Executed', 'User', (string)$uid);
                header("Location: users.php?msg=password_reset");
                exit;
            }
        }
    }
}

// Fetch users
$filter_role = $_GET['role'] ?? 'internal';
if ($filter_role === 'applicant') {
    $sql = "SELECT * FROM users WHERE role = 'applicant' ORDER BY created_at DESC LIMIT 500";
} else {
    $sql = "SELECT * FROM users WHERE role IN ('superadmin','admin','hr') ORDER BY role DESC, full_name ASC LIMIT 500";
}
$users_list = $pdo->query($sql)->fetchAll();

$page_title = 'User Accounts Management';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'users';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f4f5f5; padding-bottom:100px;">
  <div class="container" style="max-width:1200px;">
    
    <!-- MESSAGES -->
    <?php if (!empty($errors)): ?>
      <div class="alert alert-danger" style="margin-bottom:24px;">
        <span class="alert-icon">⚠</span><div><?= implode('<br>', array_map('h', $errors)) ?></div>
      </div>
    <?php endif; ?>

    <?php if ($msg === 'staff_created'): ?>
        <div class="alert alert-success" style="margin-bottom:24px;">New Administrative Staff account created successfully.</div>
    <?php elseif ($msg === 'deleted'): ?>
        <div class="alert alert-warning" style="margin-bottom:24px;">The specified user account has been permanently deleted.</div>
    <?php elseif ($msg === 'password_reset'): ?>
        <div class="alert alert-success" style="margin-bottom:24px;">The password for the user was successfully reset.</div>
    <?php endif; ?>

    <!-- TWO COLUMN LAYOUT -->
    <div style="display:grid; grid-template-columns:320px 1fr; gap:32px; align-items:start;">
        
        <!-- COLUMN 1: NEW STAFF FORM -->
        <div class="card card-pad fade-up" style="border:2px solid var(--gold); position:sticky; top:40px;">
            <h3 style="font-size:16px; margin-bottom:6px; color:var(--green-900);">Create Staff Account</h3>
            <p style="font-size:13px; color:var(--text-light); margin-bottom:16px; line-height:1.4;">Add new HR and administrative accounts to the system without requiring public registration.</p>
            
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="create_staff">
                
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Full Name</label>
                    <input type="text" name="full_name" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Official County Email</label>
                    <input type="email" name="email" class="form-input" required>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Generated Temporary Password</label>
                    <input type="text" name="password" class="form-input" value="<?= bin2hex(random_bytes(4)) ?>" required>
                    <span style="font-size:11px; color:var(--text-muted); display:inline-block; margin-top:4px;">Securely provide this temporary password to the new staff member.</span>
                </div>
                
                <div class="form-group">
                    <label class="form-label" style="font-size:12px;">Account Role / Access Level</label>
                    <select name="role" class="form-select" required>
                        <option value="hr">General HR Officer (Reporting & Applicants Only)</option>
                        <option value="admin">Site Administrator (Jobs & Application Triaging)</option>
                        <option value="superadmin">SuperAdmin (Full System + Logs + Users)</option>
                    </select>
                </div>
                
                <button type="submit" class="btn btn-gold btn-sm" style="width:100%; font-weight:700;">Create Staff Account ✓</button>
            </form>
        </div>

        <!-- COLUMN 2: USERS INDEX -->
        <div class="card fade-up" style="overflow:-moz-hidden-unscrollable; overflow:hidden;">
            
            <div style="background:#f9fafa; border-bottom:1px solid var(--border); padding:16px 20px; display:flex; justify-content:space-between; align-items:center;">
                <h3 style="font-size:16px; margin:0;">Active Personnel Directory</h3>
                
                <!-- Filter Tabs -->
                <div style="display:flex; gap:8px;">
                    <a href="?role=internal" class="btn btn-sm <?= $filter_role==='internal' ? 'btn-outline-dark' : '' ?>" style="<?= $filter_role==='internal' ? 'background:var(--green-900);color:#fff;' : 'background:#fff;' ?>">Internal HR Staff</a>
                    <a href="?role=applicant" class="btn btn-sm <?= $filter_role==='applicant' ? 'btn-outline-dark' : '' ?>" style="<?= $filter_role==='applicant' ? 'background:var(--green-900);color:#fff;' : 'background:#fff;' ?>">Public Applicants Database</a>
                </div>
            </div>

            <div class="table-responsive" style="max-height:600px; overflow-y:auto;">
                <table class="table" style="font-size:13px; margin:0;">
                    <colgroup>
                        <col style="width:30%;">
                        <col style="width:20%;">
                        <col style="width:50%;">
                    </colgroup>
                    <thead style="position:sticky; top:0; background:#fff; z-index:10; box-shadow:0 1px 3px rgba(0,0,0,0.1);">
                        <tr>
                            <th>Name & Identity</th>
                            <th>System Role</th>
                            <th>Administrative Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users_list)): ?>
                            <tr><td colspan="3" style="padding:40px; text-align:center; color:var(--text-muted);">No accounts found matching this filter.</td></tr>
                        <?php else: ?>
                            <?php foreach ($users_list as $u): ?>
                                <tr>
                                    <td>
                                        <strong style="color:var(--green-900);"><?= h($u['full_name']) ?></strong><br>
                                        <span style="font-size:12px; color:var(--text-light);"><?= h($u['username']) ?></span>
                                    </td>
                                    <td>
                                        <?php if($u['role']==='superadmin'): ?>
                                            <span class="badge" style="background:var(--gold); color:#000;">SuperAdmin</span>
                                        <?php elseif($u['role']==='admin'): ?>
                                            <span class="badge" style="background:var(--green-600); color:#fff;">Administrator</span>
                                        <?php elseif($u['role']==='hr'): ?>
                                            <span class="badge badge-neutral">HR Officer</span>
                                        <?php else: ?>
                                            <span class="badge badge-info" style="background:#eef0f2; color:var(--text-dark);">Applicant</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if($u['id'] !== auth_user()['id']): ?>
                                            <!-- INLINE OPERATIONS -->
                                            <div style="display:flex; gap:16px; align-items:center;">
                                                
                                                <!-- Delete Hook -->
                                                <form method="POST" style="margin:0;" onsubmit="return confirm('Are you sure you want to permanently delete this user account?')">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="delete_user">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <button type="submit" style="background:none; border:none; padding:0; color:#d9534f; cursor:pointer; font-weight:600; text-decoration:underline;">Delete Account</button>
                                                </form>

                                                <!-- Password Override -->
                                                <form method="POST" style="margin:0; background:#f9fafa; padding:6px 12px; border:1px solid var(--border); border-radius:4px; display:flex; gap:8px;">
                                                    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                                                    <input type="hidden" name="action" value="reset_password">
                                                    <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                                    <input type="text" name="new_password" placeholder="New Password..." style="font-size:11px; padding:4px 6px; border:1px solid #ccc; width:100px;">
                                                    <button type="submit" class="btn btn-outline-dark" style="font-size:11px; padding:2px 8px;">Reset Password</button>
                                                </form>

                                            </div>
                                        <?php else: ?>
                                            <span style="color:var(--text-light); font-style:italic;">You cannot remove your own account layer while logged in.</span>
                                        <?php endif; ?>
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
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
