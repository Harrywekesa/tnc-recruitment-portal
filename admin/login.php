<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';

if (is_logged_in() && has_role('superadmin','admin','hr')) {
    header('Location: /tnc-portal/admin/index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission.';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $pdo = db();
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role IN ('superadmin','admin','hr') AND is_active = 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        if ($user && password_verify($password, $user['password_hash'])) {
            login_user($user);
            $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
            header('Location: /tnc-portal/admin/index.php');
            exit;
        }
        $error = 'Invalid credentials.';
    }
}
$page_title = 'Admin Login';
$active_nav = '';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>
<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card-hdr">
      <div class="auth-card-hdr-crest">TN</div>
      <h2>Staff Login</h2>
      <p>Recruitment Administration Panel</p>
    </div>
    <div class="auth-card-body">
      <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px;"><span class="alert-icon">⚠</span><div><?= h($error) ?></div></div>
      <?php endif; ?>
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <div class="form-group">
          <label class="form-label">Username</label>
          <input class="form-input" type="text" name="username" autocomplete="username" value="<?= h($_POST['username'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Password</label>
          <input class="form-input" type="password" name="password" autocomplete="current-password">
        </div>
        <button type="submit" class="btn btn-green btn-lg btn-full">Sign In</button>
      </form>
    </div>
    <div class="auth-card-footer">
      Applicant? <a href="<?= $root ?>applicant/login.php">Applicant login →</a>
    </div>
  </div>
</div>
<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
