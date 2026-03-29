<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';

$pdo = db();
$stats = $pdo->query("
    SELECT
        (SELECT COUNT(*) FROM jobs)                                    AS total_jobs,
        (SELECT COUNT(*) FROM jobs WHERE status='Open')                AS open_jobs,
        (SELECT COUNT(*) FROM applications)                            AS total_apps,
        (SELECT COUNT(*) FROM applications WHERE status='Shortlisted') AS shortlisted
")->fetch();

$open_jobs = $pdo->query("
    SELECT id, job_code, title, department, type, deadline, vacancies, min_experience
    FROM jobs WHERE status='Open'
    ORDER BY deadline ASC LIMIT 6
")->fetchAll();

$page_title = 'Official Recruitment Portal';
$active_nav = 'home';
$root       = '';
require_once __DIR__ . '/includes/partials/header.php';
?>

<!-- Hero -->
<section class="hero">
  <div class="hero-pattern"></div>
  <div class="hero-grid"></div>
  <div class="container">
    <div class="hero-content">
      <div class="hero-badge">
        <span class="hero-badge-dot"></span>
        Official Recruitment Portal
      </div>
      <h1>
        Careers at<br>
        <em>Trans Nzoia County</em><br>
        Government
      </h1>
      <p class="hero-desc">
        Explore current vacancies, submit applications online, and track your
        recruitment progress — all through the County Government's official portal.
      </p>
      <div class="hero-actions">
        <a href="jobs.php" class="btn btn-gold btn-lg">View Open Positions</a>
        <a href="apply.php" class="btn btn-ghost btn-lg">Apply Online</a>
      </div>
      <div class="hero-meta">
        <div class="hero-meta-item">
          <div class="hero-meta-num"><?= $stats['open_jobs'] ?></div>
          <div class="hero-meta-lbl">Open Positions</div>
        </div>
        <div class="hero-meta-div"></div>
        <div class="hero-meta-item">
          <div class="hero-meta-num"><?= $stats['total_apps'] ?></div>
          <div class="hero-meta-lbl">Applications Received</div>
        </div>
        <div class="hero-meta-div"></div>
        <div class="hero-meta-item">
          <div class="hero-meta-num">5</div>
          <div class="hero-meta-lbl">Sub-Counties</div>
        </div>
        <div class="hero-meta-div"></div>
        <div class="hero-meta-item">
          <div class="hero-meta-num">2026</div>
          <div class="hero-meta-lbl">Recruitment Cycle</div>
        </div>
      </div>
    </div>

    <!-- Status Checker Card -->
    <div class="hero-card">
      <div class="hero-card-title">Check Application Status</div>
      <div class="hero-card-sub">Enter your reference number to track your application</div>
      <form action="status.php" method="GET">
        <div class="form-group">
          <label class="form-label" for="ref_no">Reference Number</label>
          <input class="form-input" type="text" id="ref_no" name="ref"
                 placeholder="e.g. APP-001" autocomplete="off">
        </div>
        <div class="form-group">
          <label class="form-label" for="id_no">National ID Number</label>
          <input class="form-input" type="text" id="id_no" name="id"
                 placeholder="e.g. 12345678">
        </div>
        <button type="submit" class="btn btn-gold btn-lg">Track Application</button>
      </form>
      <p class="hero-card-note">Your reference number was sent to your email on submission</p>
    </div>
  </div>
</section>

<!-- Stats Strip -->
<div class="stats-strip">
  <div class="container">
    <div class="stat-item">
      <div class="stat-icon stat-icon-green">📋</div>
      <div class="stat-body">
        <div class="stat-num"><?= $stats['total_jobs'] ?></div>
        <div class="stat-lbl">Total Vacancies Advertised</div>
      </div>
    </div>
    <div class="stat-item">
      <div class="stat-icon stat-icon-green">✅</div>
      <div class="stat-body">
        <div class="stat-num"><?= $stats['open_jobs'] ?></div>
        <div class="stat-lbl">Currently Open</div>
      </div>
    </div>
    <div class="stat-item">
      <div class="stat-icon stat-icon-gold">📁</div>
      <div class="stat-body">
        <div class="stat-num"><?= $stats['total_apps'] ?></div>
        <div class="stat-lbl">Applications Received</div>
      </div>
    </div>
    <div class="stat-item">
      <div class="stat-icon stat-icon-gold">⭐</div>
      <div class="stat-body">
        <div class="stat-num"><?= $stats['shortlisted'] ?></div>
        <div class="stat-lbl">Candidates Shortlisted</div>
      </div>
    </div>
  </div>
</div>

<!-- Current Vacancies -->
<section class="jobs-section">
  <div class="container">

    <div class="notice-banner fade-up">
      <div class="notice-icon">📢</div>
      <div class="notice-text">
        <strong>Notice:</strong> Shortlisting for Procurement Officer (TNC/PO/2026) is complete.
        <?= $stats['shortlisted'] ?> candidates shortlisted.
        <a href="shortlist.php">View shortlist →</a>
      </div>
    </div>

    <div class="section-head fade-up">
      <div>
        <span class="section-eyebrow">Current Vacancies</span>
        <h2 class="section-title">Open Positions</h2>
        <p class="section-desc">All positions advertised by the County Government of Trans Nzoia. Applications are processed through this portal only.</p>
      </div>
      <a href="jobs.php" class="btn btn-outline-green">All Vacancies</a>
    </div>

    <?php if (empty($open_jobs)): ?>
      <div style="text-align:center;padding:60px 20px;color:var(--text-muted)">
        <div style="font-size:40px;margin-bottom:14px">📋</div>
        <div style="font-size:17px;font-weight:600;color:var(--green-800);margin-bottom:8px">No open positions at this time</div>
        <div>Check back soon or subscribe to job alerts.</div>
      </div>
    <?php else: ?>
      <div class="jobs-grid">
        <?php
        $dept_icons = [
          'Finance'     => '💼',
          'ICT'         => '💻',
          'Health'      => '🏥',
          'Education'   => '🎓',
          'Agriculture' => '🌾',
          'default'     => '🏛',
        ];
        foreach ($open_jobs as $job):
          $icon = '🏛';
          foreach ($dept_icons as $key => $ico) {
            if (stripos($job['department'], $key) !== false) { $icon = $ico; break; }
          }
          $deadline_ts = strtotime($job['deadline']);
          $days_left   = ceil(($deadline_ts - time()) / 86400);
          $urgent      = $days_left <= 7 && $days_left > 0;
        ?>
        <div class="job-card fade-up">
          <div class="job-code"><?= h($job['job_code']) ?></div>
          <div class="job-card-header">
            <div style="flex:1">
              <div class="job-title"><?= h($job['title']) ?></div>
              <div class="job-dept"><?= h($job['department']) ?></div>
            </div>
            <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px">
              <div class="stat-icon stat-icon-green" style="width:38px;height:38px;font-size:18px"><?= $icon ?></div>
              <span class="badge badge-open">Open</span>
            </div>
          </div>
          <div class="job-meta">
            <div class="job-meta-item">
              <span class="job-meta-icon">🗓</span>
              <span>Deadline: <strong><?= date('d M Y', $deadline_ts) ?></strong></span>
              <?php if ($urgent): ?>
                <span class="badge badge-danger" style="margin-left:4px"><?= $days_left ?>d left</span>
              <?php endif; ?>
            </div>
            <div class="job-meta-item">
              <span class="job-meta-icon">📍</span>
              <span><?= h($job['type']) ?></span>
            </div>
            <div class="job-meta-item">
              <span class="job-meta-icon">👥</span>
              <span><?= $job['vacancies'] ?> <?= $job['vacancies'] == 1 ? 'vacancy' : 'vacancies' ?></span>
            </div>
            <div class="job-meta-item">
              <span class="job-meta-icon">📅</span>
              <span>Min <?= $job['min_experience'] ?>yr exp</span>
            </div>
          </div>
          <div class="job-card-footer">
            <a href="jobs.php#<?= h($job['job_code']) ?>" class="btn btn-outline-green btn-sm">Details</a>
            <a href="apply.php?job=<?= $job['id'] ?>" class="btn btn-green btn-sm">Apply Now</a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- How to Apply -->
<section class="steps-section">
  <div class="container">
    <div style="text-align:center;max-width:600px;margin:0 auto 0" class="fade-up">
      <span class="section-eyebrow">Application Process</span>
      <h2 class="section-title">How to Apply</h2>
      <p class="section-desc" style="margin:0 auto">Complete your application in four simple steps. Ensure all documents are ready before you start.</p>
    </div>
    <div class="steps-grid">
      <div class="step-card fade-up">
        <div class="step-icon">🔍</div>
        <div class="step-num">1</div>
        <div class="step-title">Browse Vacancies</div>
        <div class="step-desc">Review all advertised positions and download the full job descriptions.</div>
      </div>
      <div class="step-card fade-up">
        <div class="step-icon">📝</div>
        <div class="step-num">2</div>
        <div class="step-title">Fill the Form</div>
        <div class="step-desc">Complete the 5-step online application with personal and qualification details.</div>
      </div>
      <div class="step-card fade-up">
        <div class="step-icon">📎</div>
        <div class="step-num">3</div>
        <div class="step-title">Upload Documents</div>
        <div class="step-desc">Attach your CV, certificates, and cover letter (PDF or Word, max 5MB each).</div>
      </div>
      <div class="step-card fade-up">
        <div class="step-icon">✅</div>
        <div class="step-num">4</div>
        <div class="step-title">Track Status</div>
        <div class="step-desc">Use your reference number to track your application status at any time.</div>
      </div>
    </div>
  </div>
</section>


<!-- Footer -->

<?php require_once __DIR__ . '/includes/partials/footer.php'; ?>
