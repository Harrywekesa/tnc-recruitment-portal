<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';

if (is_logged_in()) {
    header('Location: /tnc-portal' . (has_role('applicant') ? '/applicant/dashboard.php' : '/admin/index.php'));
    exit;
}

$error = '';
$next  = $_GET['next'] ?? 'applicant/dashboard.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error = 'Invalid form submission. Please try again.';
    } else {
        $id_no    = trim($_POST['id_no']   ?? '');
        $password = $_POST['password']      ?? '';

        if (empty($id_no) || empty($password)) {
            $error = 'Enter your National ID number and password.';
        } else {
            $pdo  = db();
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? AND role = 'applicant' AND is_active = 1");
            $stmt->execute([$id_no]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                login_user($user);
                // Update last login
                $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?")->execute([$user['id']]);
                $target = ltrim($next, '/');
                header('Location: /tnc-portal/' . $target);
                exit;
            }
            $error = 'Invalid ID number or password. Please try again.';
        }
    }
}

$page_title = 'Applicant Login';
$active_nav = '';
$root       = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="auth-card-hdr">
      <div class="auth-card-hdr-crest">TN</div>
      <h2>Applicant Portal</h2>
      <p>Sign in with your National ID number and password</p>
    </div>
    <div class="auth-card-body">
      <?php if ($error): ?>
        <div class="alert alert-danger" style="margin-bottom:20px;">
          <span class="alert-icon">⚠</span>
          <div><?= h($error) ?></div>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['registered'])): ?>
        <div class="alert alert-success" style="margin-bottom:20px;">
          <span class="alert-icon">✓</span>
          <div>Account created successfully! You can now sign in.</div>
        </div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="form-group">
          <label class="form-label" for="id_no">National ID Number</label>
          <input class="form-input" type="text" id="id_no" name="id_no"
                 placeholder="e.g. 12345678" inputmode="numeric"
                 value="<?= h($_POST['id_no'] ?? '') ?>" autocomplete="username" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="password">Password</label>
          <input class="form-input" type="password" id="password" name="password"
                 placeholder="Your password" autocomplete="current-password" required>
        </div>

        <button type="submit" class="btn btn-green btn-lg btn-full">Sign In</button>
      </form>

      <div class="auth-divider">or</div>

      <a href="<?= $root ?>applicant/register.php" class="btn btn-outline-green btn-lg btn-full">
        Create New Account
      </a>
    </div>
    <div class="auth-card-footer">
      <a href="<?= $root ?>status.php">Check application status without logging in</a>
      <br><br>
      Staff member? <a href="<?= $root ?>admin/login.php">Admin login →</a>
    </div>
  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
