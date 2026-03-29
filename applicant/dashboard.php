<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_applicant();

$user = auth_user();
$pdo  = db();

// Fetch this applicant's applications with job info and interview
$apps = $pdo->prepare("
    SELECT a.*, j.title AS job_title, j.department, j.job_code, j.scope,
           j.target_sub_county, j.target_ward,
           i.interview_date, i.interview_time, i.venue, i.mode AS interview_mode, i.requirements, i.status AS interview_status
    FROM applications a
    JOIN jobs j ON a.job_id = j.id
    LEFT JOIN interviews i ON i.application_id = a.id
    WHERE a.user_id = ?
    ORDER BY a.submitted_at DESC
");
$apps->execute([$user['id']]);
$applications = $apps->fetchAll();

// Fetch user's full profile
$profile = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$profile->execute([$user['id']]);
$profile = $profile->fetch();

$page_title = 'My Dashboard';
$active_nav = '';
$root       = '../';
require_once __DIR__ . '/../includes/partials/header.php';
?>

<div class="page-hdr">
  <div class="container">
    <div>
      <div class="breadcrumb"><a href="<?= $root ?>index.php">Home</a> <span>›</span> <span>My Dashboard</span></div>
      <h1>Welcome, <?= h(explode(' ', $profile['full_name'])[0]) ?></h1>
      <div class="page-hdr-sub">
        <?= h($profile['ward'] ?? '—') ?> Ward, <?= h($profile['sub_county'] ?? '—') ?> Sub-County
        <?php if (!$profile['id_verified']): ?>
          &nbsp;·&nbsp; <span style="color:var(--gold-300);font-size:13px;">⏳ ID verification pending</span>
        <?php else: ?>
          &nbsp;·&nbsp; <span style="color:var(--green-200);font-size:13px;">✓ ID verified</span>
        <?php endif; ?>
      </div>
    </div>
    <div class="page-hdr-stats">
      <div class="hdr-stat">
        <div class="hdr-stat-num"><?= count($applications) ?></div>
        <div class="hdr-stat-lbl">Applications</div>
      </div>
      <div class="hdr-stat">
        <div class="hdr-stat-num"><?= count(array_filter($applications, fn($a) => $a['status'] === 'Shortlisted')) ?></div>
        <div class="hdr-stat-lbl">Shortlisted</div>
      </div>
    </div>
  </div>
</div>

<div class="page-wrap">
  <div class="container" style="display:grid;grid-template-columns:280px 1fr;gap:28px;align-items:start;">

    <!-- Sidebar: Profile summary -->
    <aside>
      <div class="card card-sm fade-up" style="margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:14px;">My Profile</div>
        <div style="display:flex;flex-direction:column;gap:10px;font-size:13.5px;">
          <div><span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Full Name</span><?= h($profile['full_name']) ?></div>
          <div><span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">National ID</span><?= h($profile['username']) ?></div>
          <div><span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Email</span><?= h($profile['email']) ?></div>
          <div><span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Sub-County</span><?= h($profile['sub_county'] ?? '—') ?></div>
          <div><span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:2px;">Ward</span><?= h($profile['ward'] ?? '—') ?></div>
          <div>
            <span style="color:var(--text-light);display:block;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:.05em;margin-bottom:4px;">ID Verification</span>
            <?php if ($profile['id_verified']): ?>
              <span class="badge badge-success">✓ Verified</span>
            <?php else: ?>
              <span class="badge badge-gold">⏳ Pending review</span>
            <?php endif; ?>
          </div>
        </div>
      </div>

      <div class="card card-sm fade-up" style="margin-bottom:16px;">
        <div style="font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--green-600);margin-bottom:14px;">Quick Links</div>
        <div style="display:flex;flex-direction:column;gap:8px;">
          <a href="profile.php" class="btn btn-green btn-sm" style="justify-content:flex-start;">Edit My Profile</a>
          <a href="<?= $root ?>jobs.php" class="btn btn-outline-green btn-sm" style="justify-content:flex-start;">Browse Vacancies</a>
          <a href="<?= $root ?>status.php" class="btn btn-outline-dark btn-sm" style="justify-content:flex-start;">Check Status by Ref</a>
          <a href="<?= $root ?>logout.php" class="btn btn-outline-dark btn-sm" style="justify-content:flex-start;">Sign Out</a>
        </div>
      </div>

      <div class="alert alert-warning fade-up" style="font-size:13px;">
        <span class="alert-icon" style="font-size:15px;">⚠</span>
        <div>Canvassing in any form leads to automatic disqualification.</div>
      </div>
    </aside>

    <!-- Main: Applications list -->
    <div>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;" class="fade-up">
        <h2 style="font-family:var(--font-display);font-size:22px;font-weight:700;color:var(--green-900);">My Applications</h2>
        <a href="<?= $root ?>jobs.php" class="btn btn-green btn-sm">+ Apply for a Position</a>
      </div>

      <?php if (empty($applications)): ?>
        <div class="card card-pad fade-up" style="text-align:center;padding:60px 24px;">
          <div style="font-size:40px;margin-bottom:14px;">📋</div>
          <div style="font-size:17px;font-weight:700;color:var(--green-800);margin-bottom:8px;">No applications yet</div>
          <div style="font-size:14px;color:var(--text-muted);margin-bottom:22px;">Browse open vacancies and submit your first application.</div>
          <a href="<?= $root ?>jobs.php" class="btn btn-green">Browse Vacancies</a>
        </div>
      <?php else: ?>
        <div style="display:flex;flex-direction:column;gap:16px;">
          <?php foreach ($applications as $app):
            $status_cfg = APP_STATUSES[$app['status']] ?? ['label'=>$app['status'],'class'=>'badge-neutral'];
          ?>
          <div class="card card-pad fade-up">
            <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:14px;">
              <div>
                <div style="font-family:var(--font-mono);font-size:10.5px;color:var(--text-light);letter-spacing:.05em;margin-bottom:4px;"><?= h($app['job_code']) ?></div>
                <div style="font-family:var(--font-display);font-size:17px;font-weight:700;color:var(--green-900);"><?= h($app['job_title']) ?></div>
                <div style="font-size:13px;color:var(--text-muted);"><?= h($app['department']) ?></div>
                <?php if ($app['scope'] === 'ward_specific'): ?>
                  <span class="badge badge-ward" style="margin-top:6px;">📍 <?= h($app['target_ward']) ?> Ward</span>
                <?php endif; ?>
              </div>
              <span class="badge <?= $status_cfg['class'] ?>" style="font-size:12.5px;padding:5px 14px;">
                <?= h($status_cfg['label']) ?>
              </span>
            </div>

            <div style="display:flex;flex-wrap:wrap;gap:20px;font-size:12.5px;color:var(--text-muted);padding:12px 0;border-top:1px solid var(--border);border-bottom:1px solid var(--border);margin-bottom:14px;">
              <span>📋 Ref: <strong style="color:var(--green-800);font-family:var(--font-mono)"><?= h($app['ref_no']) ?></strong></span>
              <span>📅 Submitted: <?= date('d M Y', strtotime($app['submitted_at'])) ?></span>
              <?php if ($app['review_at'] ?? false): ?>
                <span>🔍 Reviewed: <?= date('d M Y', strtotime($app['reviewed_at'])) ?></span>
              <?php endif; ?>
            </div>

            <?php if ($app['status'] === 'Interview Scheduled' && $app['interview_date']): ?>
              <div style="background:#f4ebf9; border:1px solid #d8b4e2; padding:16px; border-radius:8px; margin-top:8px;">
                <div style="display:flex; align-items:center; gap:8px; margin-bottom:12px;">
                  <span style="font-size:20px;">📅</span>
                  <h4 style="margin:0; font-size:15px; color:#6b21a8; font-weight:700;">Official Interview Scheduled</h4>
                </div>
                
                <div style="display:grid; grid-template-columns:1fr 1fr; gap:12px; font-size:13.5px; color:var(--text-dark);">
                  <div>
                    <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Date & Time</span>
                    <strong><?= date('l, d F Y', strtotime($app['interview_date'])) ?></strong><br>
                    <?= date('g:i A', strtotime($app['interview_time'])) ?>
                  </div>
                  <div>
                    <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Format</span>
                    <span class="badge <?= $app['interview_mode'] === 'Virtual' ? 'badge-info' : 'badge-gold' ?>"><?= h($app['interview_mode']) ?></span>
                  </div>
                  
                  <div style="grid-column: span 2;">
                    <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">
                      <?= $app['interview_mode'] === 'Virtual' ? 'Video Meeting Link' : 'Physical Venue Location' ?>
                    </span>
                    <?php if ($app['interview_mode'] === 'Virtual' && filter_var($app['venue'], FILTER_VALIDATE_URL)): ?>
                        <a href="<?= h($app['venue']) ?>" target="_blank" style="color:#6b21a8; font-weight:600; text-decoration:underline;">Click Here to Join Meeting</a>
                    <?php else: ?>
                        <strong><?= h($app['venue']) ?></strong>
                    <?php endif; ?>
                  </div>
                  
                  <?php if ($app['requirements']): ?>
                  <div style="grid-column: span 2; background:#fff; padding:10px 12px; border-radius:4px; border-left:3px solid #6b21a8; margin-top:4px;">
                    <span style="display:block; font-size:11px; color:var(--text-muted); text-transform:uppercase; font-weight:600; letter-spacing:0.05em; margin-bottom:2px;">Important Instructions</span>
                    <?= h($app['requirements']) ?>
                  </div>
                  <?php endif; ?>
                </div>
              </div>
            <?php endif; ?>

            <?php if ($app['reviewer_notes'] && in_array($app['status'], ['Not Shortlisted','Rejected'])): ?>
              <div class="alert alert-danger" style="margin-top:12px;margin-bottom:0;">
                <span class="alert-icon">ℹ</span>
                <div><?= h($app['reviewer_notes']) ?></div>
              </div>
            <?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
