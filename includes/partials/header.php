<?php
declare(strict_types=1);
// includes/partials/header.php
// Usage: $page_title, $active_nav (e.g. 'jobs'), $extra_css (optional inline block)
if (session_status() === PHP_SESSION_NONE) session_start();
require_once dirname(__DIR__) . '/auth.php';
require_once dirname(__DIR__) . '/constants.php';

$page_title = $page_title ?? 'Recruitment Portal';
$active_nav = $active_nav ?? '';
$full_title  = $page_title . ' — County Government of Trans Nzoia';

$root = $root ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="Official recruitment portal for the County Government of Trans Nzoia. Browse vacancies, apply online, track your application.">
<title><?= htmlspecialchars($full_title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;600;700&family=DM+Sans:wght@300;400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= $root ?>assets/css/style.css">
<?php if (!empty($extra_css)): ?>
<style><?= $extra_css ?></style>
<?php endif; ?>
</head>
<body>

<!-- Topbar -->
<div class="topbar">
  <div class="container">
    <div class="topbar-left">
      <span>County Government of Trans Nzoia — Official Recruitment Portal</span>
      <div class="topbar-div"></div>
      <a href="tel:+25405320604">+254 (053) 20604</a>
    </div>
    <div class="topbar-right">
      <?php if (is_logged_in()): ?>
        <?php if (has_role('superadmin','admin','hr')): ?>
          <a href="<?= $root ?>admin/">Admin Panel</a>
        <?php else: ?>
          <a href="<?= $root ?>applicant/dashboard.php">My Application</a>
        <?php endif; ?>
        <div class="topbar-div"></div>
        <a href="<?= $root ?>logout.php">Sign out</a>
      <?php else: ?>
        <a href="<?= $root ?>applicant/login.php">Applicant Login</a>
        <div class="topbar-div"></div>
        <a href="<?= $root ?>applicant/register.php">Create Account</a>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Navbar -->
<nav class="navbar">
  <div class="container">
    <a href="<?= $root ?>index.php" class="nav-brand">
      <div class="nav-crest">TN</div>
      <div class="nav-brand-text">
        <div class="nav-brand-title">Trans Nzoia County</div>
        <div class="nav-brand-sub">Recruitment Portal</div>
      </div>
    </a>
    <div class="nav-links">
      <?php if ($active_nav === 'admin_dash'): ?>
        <span style="font-size:15px; font-weight:700; color:var(--text-muted); text-transform:uppercase; letter-spacing:1px;">Secure Backend Administration</span>
      <?php else: ?>
        <a href="<?= $root ?>index.php"           class="<?= $active_nav==='home'      ?'active':'' ?>">Home</a>
        <a href="<?= $root ?>jobs.php"            class="<?= $active_nav==='jobs'      ?'active':'' ?>">Vacancies</a>
        <a href="<?= $root ?>apply.php"           class="<?= $active_nav==='apply'     ?'active':'' ?>">Apply Online</a>
        <a href="<?= $root ?>status.php"          class="<?= $active_nav==='status'    ?'active':'' ?>">Check Status</a>
        <a href="<?= $root ?>shortlist.php"       class="<?= $active_nav==='shortlist' ?'active':'' ?>">Shortlists</a>
      <?php endif; ?>
    </div>
    <div class="nav-actions">
      <?php if (is_logged_in() && has_role('applicant')): ?>
        <a href="<?= $root ?>applicant/dashboard.php" class="btn btn-gold">My Application</a>
        <a href="<?= $root ?>logout.php" class="btn btn-outline-green" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.8);border-color:rgba(255,255,255,.2)">Sign Out</a>
      <?php elseif (is_logged_in() && has_role('superadmin','admin','hr')): ?>
        <?php if ($active_nav !== 'admin_dash'): ?>
          <a href="<?= $root ?>admin/" class="btn btn-ghost">Admin Dashboard</a>
        <?php endif; ?>
        <a href="<?= $root ?>logout.php" class="btn btn-outline-green" style="background:rgba(255,255,255,.08);color:rgba(255,255,255,.8);border-color:rgba(255,255,255,.2)">Sign Out</a>
      <?php else: ?>
        <a href="<?= $root ?>applicant/login.php" class="btn btn-ghost">Sign In</a>
        <a href="<?= $root ?>applicant/register.php" class="btn btn-gold">Create Account</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
