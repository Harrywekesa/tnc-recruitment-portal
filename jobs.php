<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/constants.php';

$pdo = db();
// ── Filters from GET ─────────────────────────────────────────
$filter_scope  = $_GET['scope']  ?? 'all';       // all | county_wide | ward_specific
$filter_sc     = $_GET['sc']     ?? '';           // sub-county
$filter_status = $_GET['status'] ?? 'Open';       // Open | Closed | all
$filter_dept   = $_GET['dept']   ?? '';
$filter_q      = trim($_GET['q'] ?? '');

// ── Build query ───────────────────────────────────────────────
$where  = ['1=1'];
$params = [];

if ($filter_scope !== 'all') {
    $where[]  = 'scope = ?';
    $params[] = $filter_scope;
}
if ($filter_sc !== '') {
    $where[]  = 'target_sub_county = ?';
    $params[] = $filter_sc;
}
if ($filter_status !== 'all') {
    $where[]  = 'status = ?';
    $params[] = $filter_status;
}
if ($filter_dept !== '') {
    $where[]  = 'department = ?';
    $params[] = $filter_dept;
}
if ($filter_q !== '') {
    $where[]  = '(title LIKE ? OR department LIKE ? OR description LIKE ?)';
    $like     = "%$filter_q%";
    $params   = array_merge($params, [$like, $like, $like]);
}

$sql = "SELECT * FROM jobs WHERE " . implode(' AND ', $where) . "
        ORDER BY
          FIELD(status,'Open','Closed','Draft','Cancelled'),
          scope ASC,
          target_sub_county ASC,
          deadline ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$all_jobs = $stmt->fetchAll();

// ── Separate county-wide vs ward-specific ────────────────────
$county_jobs = array_filter($all_jobs, fn($j) => $j['scope'] === 'county_wide');
$ward_jobs   = array_filter($all_jobs, fn($j) => $j['scope'] === 'ward_specific');

// Group ward jobs by sub-county
$by_subcounty = [];
foreach ($ward_jobs as $job) {
    $by_subcounty[$job['target_sub_county']][] = $job;
}

// ── Departments for filter ────────────────────────────────────
$departments = $pdo->query("SELECT DISTINCT department FROM jobs ORDER BY department")->fetchAll(PDO::FETCH_COLUMN);

// ── Counts for tabs ───────────────────────────────────────────
$counts = $pdo->query("
    SELECT scope, COUNT(*) as cnt FROM jobs WHERE status='Open' GROUP BY scope
")->fetchAll();
$cnt = ['county_wide' => 0, 'ward_specific' => 0, 'all' => 0];
foreach ($counts as $c) {
    $cnt[$c['scope']] = (int)$c['cnt'];
    $cnt['all'] += (int)$c['cnt'];
}

$dept_icons = [
    'Finance'    => '💼', 'ICT'     => '💻', 'Health'   => '🏥',
    'Education'  => '🎓', 'Agriculture' => '🌾', 'Legal' => '⚖️',
    'Public'     => '🏛',
];
function dept_icon(string $dept, array $map): string {
    foreach ($map as $key => $icon) {
        if (stripos($dept, $key) !== false) return $icon;
    }
    return '🏛';
}
function days_badge(string $deadline): string {
    $d = ceil((strtotime($deadline) - time()) / 86400);
    if ($d < 0)  return '<span class="badge badge-closed">Closed</span>';
    if ($d <= 7) return '<span class="badge badge-danger">' . $d . 'd left</span>';
    return '';
}
?>
<?php
$page_title = 'Vacancies';
$active_nav = 'jobs';
$root       = '';
require_once __DIR__ . '/includes/partials/header.php';
?>

<!-- Page Header -->
<div class="page-hdr">
  <div class="page-hdr-bg"></div>
  <div class="page-hdr-grid"></div>
  <div class="container">
    <div class="page-hdr-left">
      <div class="breadcrumb">
        <a href="index.php">Home</a>
        <span class="breadcrumb-sep">›</span>
        <span>Vacancies</span>
      </div>
      <h1>Current Vacancies</h1>
      <div class="page-hdr-sub">All positions advertised by the County Government of Trans Nzoia for the 2026 recruitment cycle</div>
    </div>
    <div class="page-hdr-stats">
      <div class="hdr-stat">
        <div class="hdr-stat-num"><?= $cnt['all'] ?></div>
        <div class="hdr-stat-lbl">Open Positions</div>
      </div>
      <div class="hdr-stat">
        <div class="hdr-stat-num"><?= $cnt['county_wide'] ?></div>
        <div class="hdr-stat-lbl">County-Wide</div>
      </div>
      <div class="hdr-stat">
        <div class="hdr-stat-num"><?= $cnt['ward_specific'] ?></div>
        <div class="hdr-stat-lbl">Ward-Based</div>
      </div>
    </div>
  </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
  <div class="container">
    <form method="GET" style="display:contents">
      <div class="filter-group">
        <label class="filter-label">Status</label>
        <select class="filter-select" name="status" onchange="this.form.submit()">
          <option value="Open"  <?= $filter_status==='Open'  ?'selected':'' ?>>Open</option>
          <option value="Closed"<?= $filter_status==='Closed'?'selected':'' ?>>Closed</option>
          <option value="all"   <?= $filter_status==='all'   ?'selected':'' ?>>All</option>
        </select>
      </div>
      <div class="filter-divider"></div>
      <div class="filter-group">
        <label class="filter-label">Department</label>
        <select class="filter-select" name="dept" onchange="this.form.submit()">
          <option value="">All Departments</option>
          <?php foreach ($departments as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $filter_dept===$d?'selected':'' ?>><?= htmlspecialchars($d) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="filter-divider"></div>
      <div class="filter-search">
        <input type="text" name="q" value="<?= htmlspecialchars($filter_q) ?>" placeholder="Search by title, department…">
        <button type="submit">&#128269;</button>
      </div>
      <?php if ($filter_q || $filter_dept || $filter_status!=='Open'): ?>
        <a href="jobs.php" class="filter-clear">&#10005; Clear filters</a>
      <?php endif; ?>
      <input type="hidden" name="scope" value="<?= htmlspecialchars($filter_scope) ?>">
    </form>
  </div>
</div>

<!-- Scope Tabs -->
<div class="scope-tabs">
  <div class="container">
    <a href="?scope=all&status=<?= urlencode($filter_status) ?>&dept=<?= urlencode($filter_dept) ?>&q=<?= urlencode($filter_q) ?>"
       class="scope-tab <?= $filter_scope==='all'?'active':'' ?>">
      All Vacancies <span class="tab-count"><?= count($all_jobs) ?></span>
    </a>
    <a href="?scope=county_wide&status=<?= urlencode($filter_status) ?>&dept=<?= urlencode($filter_dept) ?>&q=<?= urlencode($filter_q) ?>"
       class="scope-tab <?= $filter_scope==='county_wide'?'active':'' ?>">
      County-Wide Posts <span class="tab-count"><?= count($county_jobs) ?></span>
    </a>
    <a href="?scope=ward_specific&status=<?= urlencode($filter_status) ?>&dept=<?= urlencode($filter_dept) ?>&q=<?= urlencode($filter_q) ?>"
       class="scope-tab <?= $filter_scope==='ward_specific'?'active':'' ?>">
      Ward-Based Posts <span class="tab-count"><?= count($ward_jobs) ?></span>
    </a>
  </div>
</div>

<!-- Main Body -->
<div class="page-body">
  <div class="container">

    <!-- Sidebar -->
    <aside class="sidebar">
      <?php if ($filter_scope !== 'county_wide'): ?>
      <div class="sidebar-card">
        <div class="sidebar-hdr">Filter by Sub-County</div>
        <div class="sidebar-body">
          <div class="ward-item <?= $filter_sc===''?'active':'' ?>"
               onclick="location='?scope=<?= urlencode($filter_scope) ?>&status=<?= urlencode($filter_status) ?>&dept=<?= urlencode($filter_dept) ?>&q=<?= urlencode($filter_q) ?>'">
            <span>All Sub-Counties</span>
          </div>
          <?php foreach (SUB_COUNTIES as $sc => $wards):
            $open_in_sc = count(array_filter($ward_jobs, fn($j) => $j['target_sub_county'] === $sc && $j['status']==='Open'));
          ?>
          <div class="sc-group">
            <div class="sc-group-label"><?= htmlspecialchars($sc) ?> <span><?= $open_in_sc ?> open</span></div>
            <ul class="ward-list">
              <?php foreach ($wards as $ward):
                $open_in_ward = count(array_filter($ward_jobs, fn($j) => $j['target_ward']===$ward && $j['status']==='Open'));
              ?>
              <li class="ward-item <?= ($filter_sc===$sc)?'active':'' ?>"
                  onclick="location='?scope=ward_specific&sc=<?= urlencode($sc) ?>&status=<?= urlencode($filter_status) ?>&dept=<?= urlencode($filter_dept) ?>&q=<?= urlencode($filter_q) ?>'">
                <span><?= htmlspecialchars($ward) ?></span>
                <?php if ($open_in_ward): ?>
                  <span class="ward-open-badge"><?= $open_in_ward ?></span>
                <?php endif; ?>
              </li>
              <?php endforeach; ?>
            </ul>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>

      <div class="notice-box">
        <strong>&#9888; Important Notice</strong>
        The County Government does not charge fees at any stage of recruitment.
        Canvassing leads to automatic disqualification.
        Only shortlisted candidates will be contacted.
      </div>
    </aside>

    <!-- Content -->
    <div class="content-area">

      <?php if (empty($all_jobs)): ?>
        <div class="empty-state">
          <div class="empty-icon">📋</div>
          <div class="empty-title">No vacancies found</div>
          <div class="empty-desc">Try adjusting your search or filter criteria.</div>
        </div>

      <?php else: ?>

        <!-- County-Wide Section -->
        <?php if ($filter_scope !== 'ward_specific' && !empty($county_jobs)): ?>
        <div class="section-divider fade-up">
          <div class="section-divider-label">
            <div class="section-divider-icon section-divider-icon-green">🏛</div>
            <div>
              <div class="section-divider-title">County-Wide Positions</div>
              <div class="section-divider-count">Open to qualified applicants from all sub-counties</div>
            </div>
          </div>
          <div class="section-divider-line"></div>
          <span style="font-size:12px;color:var(--text-light);white-space:nowrap"><?= count($county_jobs) ?> position<?= count($county_jobs)!==1?'s':'' ?></span>
        </div>
        <div class="jobs-grid" id="county-jobs">
          <?php foreach ($county_jobs as $job): ?>
          <div class="job-card <?= $job['status']==='Closed'?'closed':'' ?> fade-up" id="job-<?= $job['id'] ?>">
            <div class="job-card-icon"><?= dept_icon($job['department'], $dept_icons) ?></div>
            <div class="job-card-body">
              <div class="job-card-top">
                <div>
                  <div class="job-code"><?= htmlspecialchars($job['job_code']) ?></div>
                  <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                  <div class="job-dept"><?= htmlspecialchars($job['department']) ?></div>
                </div>
                <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
                  <span class="badge <?= $job['status']==='Open'?'badge-open':'badge-closed' ?>"><?= $job['status'] ?></span>
                  <?= days_badge($job['deadline']) ?>
                </div>
              </div>
              <div class="job-meta-row">
                <div class="job-meta-item">&#128197; Deadline: <strong><?= date('d M Y', strtotime($job['deadline'])) ?></strong></div>
                <div class="job-meta-item">&#128198; <?= htmlspecialchars($job['type']) ?></div>
                <div class="job-meta-item">&#128101; <?= $job['vacancies'] ?> <?= $job['vacancies']==1?'vacancy':'vacancies' ?></div>
                <?php if ($job['min_experience']): ?>
                  <div class="job-meta-item">&#127891; Min <?= $job['min_experience'] ?>yr exp</div>
                <?php endif; ?>
                <?php if ($job['salary_scale']): ?>
                  <div class="job-meta-item">&#128178; <?= htmlspecialchars($job['salary_scale']) ?></div>
                <?php endif; ?>
              </div>
              <div class="job-desc"><?= htmlspecialchars($job['description']) ?></div>
              <div class="job-actions">
                <button class="btn btn-outline-green btn-sm" onclick="openModal(<?= $job['id'] ?>)">View Details</button>
                <?php if ($job['status']==='Open'): ?>
                  <?php if (is_logged_in() && has_role('applicant')): ?>
                    <a href="apply.php?job=<?= $job['id'] ?>" class="btn btn-green btn-sm">Apply Now</a>
                  <?php else: ?>
                    <a href="applicant/login.php?next=<?= urlencode('apply.php?job='.$job['id']) ?>" class="btn btn-green btn-sm">Login to Apply</a>
                  <?php endif; ?>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <!-- Ward-Specific Section -->
        <?php if ($filter_scope !== 'county_wide' && !empty($ward_jobs)): ?>
        <div class="section-divider fade-up" style="margin-top:<?= empty($county_jobs)||$filter_scope==='ward_specific'?'0':'48px' ?>">
          <div class="section-divider-label">
            <div class="section-divider-icon section-divider-icon-gold">📍</div>
            <div>
              <div class="section-divider-title">Ward-Based Positions</div>
              <div class="section-divider-count">Allocated per ward — applicant must reside in the specific ward</div>
            </div>
          </div>
          <div class="section-divider-line"></div>
          <span style="font-size:12px;color:var(--text-light);white-space:nowrap"><?= count($ward_jobs) ?> position<?= count($ward_jobs)!==1?'s':'' ?></span>
        </div>

        <?php foreach (SUB_COUNTIES as $sc_name => $sc_wards):
          if (!isset($by_subcounty[$sc_name])) continue;
          $sc_jobs = $by_subcounty[$sc_name];
          $open_count = count(array_filter($sc_jobs, fn($j) => $j['status']==='Open'));
        ?>
        <div class="sc-section fade-up" id="sc-<?= strtolower(str_replace(' ','-',$sc_name)) ?>">
          <div class="sc-section-hdr" onclick="toggleSC(this)">
            <div class="sc-section-title">📍 <?= htmlspecialchars($sc_name) ?> Sub-County</div>
            <div class="sc-section-meta"><?= $open_count ?> open · <?= count($sc_wards) ?> wards</div>
            <div class="sc-toggle">&#9660;</div>
          </div>
          <div class="sc-jobs">
            <?php foreach ($sc_jobs as $job): ?>
            <div class="job-card <?= $job['status']==='Closed'?'closed':'' ?>" id="job-<?= $job['id'] ?>">
              <div class="job-card-icon gold-bg">🏥</div>
              <div class="job-card-body">
                <div class="job-card-top">
                  <div>
                    <div class="job-ward-tag">&#128205; <?= htmlspecialchars($job['target_ward']) ?> Ward</div>
                    <div class="job-code"><?= htmlspecialchars($job['job_code']) ?></div>
                    <div class="job-title"><?= htmlspecialchars($job['title']) ?></div>
                    <div class="job-dept"><?= htmlspecialchars($job['department']) ?></div>
                  </div>
                  <div style="display:flex;flex-direction:column;align-items:flex-end;gap:6px;flex-shrink:0">
                    <span class="badge <?= $job['status']==='Open'?'badge-open':'badge-closed' ?>"><?= $job['status'] ?></span>
                    <?= days_badge($job['deadline']) ?>
                  </div>
                </div>
                <div class="job-meta-row">
                  <div class="job-meta-item">&#128197; Deadline: <strong><?= date('d M Y', strtotime($job['deadline'])) ?></strong></div>
                  <?php if ($job['salary_scale']): ?>
                    <div class="job-meta-item">&#128178; <?= htmlspecialchars($job['salary_scale']) ?></div>
                  <?php endif; ?>
                </div>
                <div class="job-desc"><?= htmlspecialchars($job['description']) ?></div>
                <div class="job-actions">
                  <button class="btn btn-outline-green btn-sm" onclick="openModal(<?= $job['id'] ?>)">View Details</button>
                  <?php if ($job['status']==='Open'): ?>
                    <?php if (is_logged_in() && has_role('applicant')): ?>
                      <a href="apply.php?job=<?= $job['id'] ?>" class="btn btn-gold btn-sm">Apply — <?= htmlspecialchars($job['target_ward']) ?> Ward</a>
                    <?php else: ?>
                      <a href="applicant/login.php?next=<?= urlencode('apply.php?job='.$job['id']) ?>" class="btn btn-gold btn-sm">Login to Apply</a>
                    <?php endif; ?>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

      <?php endif; ?>
    </div><!-- /content-area -->
  </div>
</div>

<!-- Job Detail Modal -->
<div class="modal-backdrop" id="modal-backdrop" onclick="handleBackdropClick(event)">
  <div class="modal" id="modal-box">
    <div class="modal-hdr">
      <div class="modal-hdr-left">
        <div class="modal-job-code" id="m-code"></div>
        <div class="modal-title" id="m-title"></div>
        <div class="modal-dept" id="m-dept"></div>
      </div>
      <button class="modal-close" onclick="closeModal()">&#10005;</button>
    </div>
    <div class="modal-body">
      <div id="m-ward-tag" style="display:none;margin-bottom:16px"></div>
      <div class="modal-meta-grid">
        <div class="modal-meta-item">
          <div class="modal-meta-label">Deadline</div>
          <div class="modal-meta-value" id="m-deadline"></div>
        </div>
        <div class="modal-meta-item">
          <div class="modal-meta-label">Vacancies</div>
          <div class="modal-meta-value" id="m-vacancies"></div>
        </div>
        <div class="modal-meta-item">
          <div class="modal-meta-label">Salary Scale</div>
          <div class="modal-meta-value" id="m-salary"></div>
        </div>
      </div>
      <div class="modal-section">
        <div class="modal-section-title">About the role</div>
        <p id="m-desc"></p>
      </div>
      <div class="modal-section" id="m-reqs-wrap">
        <div class="modal-section-title">Requirements</div>
        <ul id="m-reqs"></ul>
      </div>
      <div class="modal-section" id="m-resp-wrap">
        <div class="modal-section-title">Key responsibilities</div>
        <ul id="m-resp"></ul>
      </div>
    </div>
    <div class="modal-footer">
      <button class="btn btn-outline-green" onclick="closeModal()">Close</button>
      <a href="#" class="btn btn-green" id="m-apply-btn">Login to Apply</a>
    </div>
  </div>
</div>

<!-- Embed all job data for JS modal -->
<script>
const JOBS = <?= json_encode(array_values($all_jobs), JSON_HEX_TAG|JSON_HEX_AMP) ?>;
const IS_LOGGED_IN = <?= is_logged_in() && has_role('applicant') ? 'true' : 'false' ?>;

function openModal(id) {
  const job = JOBS.find(j => j.id == id);
  if (!job) return;

  document.getElementById('m-code').textContent    = job.job_code;
  document.getElementById('m-title').textContent   = job.title;
  document.getElementById('m-dept').textContent    = job.department;
  document.getElementById('m-deadline').textContent = new Date(job.deadline).toLocaleDateString('en-KE',{day:'numeric',month:'short',year:'numeric'});
  document.getElementById('m-vacancies').textContent = job.vacancies + (job.vacancies==1?' vacancy':' vacancies');
  document.getElementById('m-salary').textContent  = job.salary_scale || 'As per scheme of service';
  document.getElementById('m-desc').textContent    = job.description;

  // Ward tag
  const wt = document.getElementById('m-ward-tag');
  if (job.scope === 'ward_specific' && job.target_ward) {
    wt.innerHTML = '<span class="badge badge-ward" style="font-size:13px;padding:5px 14px">📍 ' + job.target_sub_county + ' · ' + job.target_ward + ' Ward</span>';
    wt.style.display = 'block';
  } else {
    wt.style.display = 'none';
  }

  // Requirements
  const reqsWrap = document.getElementById('m-reqs-wrap');
  const reqsList = document.getElementById('m-reqs');
  if (job.requirements) {
    reqsList.innerHTML = job.requirements.split('\n').filter(Boolean).map(r => `<li>${r}</li>`).join('');
    reqsWrap.style.display = 'block';
  } else { reqsWrap.style.display = 'none'; }

  // Responsibilities
  const respWrap = document.getElementById('m-resp-wrap');
  const respList = document.getElementById('m-resp');
  if (job.responsibilities) {
    respList.innerHTML = job.responsibilities.split('\n').filter(Boolean).map(r => `<li>${r}</li>`).join('');
    respWrap.style.display = 'block';
  } else { respWrap.style.display = 'none'; }

  // Apply button
  const applyBtn = document.getElementById('m-apply-btn');
  if (job.status === 'Open') {
    if (IS_LOGGED_IN) {
      applyBtn.href = 'apply.php?job=' + job.id;
      applyBtn.textContent = 'Apply Now';
      applyBtn.className = 'btn btn-green';
    } else {
      applyBtn.href = 'applicant/login.php?next=' + encodeURIComponent('apply.php?job=' + job.id);
      applyBtn.textContent = 'Login to Apply';
      applyBtn.className = 'btn btn-green';
    }
    applyBtn.style.display = '';
  } else {
    applyBtn.style.display = 'none';
  }

  document.getElementById('modal-backdrop').classList.add('open');
  document.body.style.overflow = 'hidden';
}

function closeModal() {
  document.getElementById('modal-backdrop').classList.remove('open');
  document.body.style.overflow = '';
}

function handleBackdropClick(e) {
  if (e.target === document.getElementById('modal-backdrop')) closeModal();
}

document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

function toggleSC(hdr) {
  hdr.closest('.sc-section').classList.toggle('collapsed');
}

// Fade-up observer
const obs = new IntersectionObserver(entries => {
  entries.forEach((e, i) => {
    if (e.isIntersecting) {
      setTimeout(() => e.target.classList.add('visible'), i * 60);
      obs.unobserve(e.target);
    }
  });
}, { threshold: 0.08 });
document.querySelectorAll('.fade-up').forEach(el => obs.observe(el));
</script>

<?php require_once __DIR__ . '/includes/partials/footer.php'; ?>
