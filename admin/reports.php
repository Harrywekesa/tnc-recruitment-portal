<?php
declare(strict_types=1);
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/constants.php';
require_admin(); // HR, Admin, and SuperAdmin can access reports

$pdo = db();

if (isset($_GET['download'])) {
    $type = $_GET['download'];
    $job_id = (int)($_GET['job_id'] ?? 0);
    
    // Setup Native CSV Streaming Engine
    header('Content-Type: text/csv');
    header('Cache-Control: no-cache, must-revalidate');
    $out = fopen('php://output', 'w');

    // 1. FULL APPLICANT LIST
    if ($type === 'master_manifest' && $job_id) {
        header('Content-Disposition: attachment; filename="Applicant_Export_JOB_'.$job_id.'.csv"');
        log_activity('Exported Applicant Data', 'Job', (string)$job_id);
        
        fputcsv($out, ['Appl ID', 'Job Title', 'Full Name', 'ID Number', 'Phone', 'Email', 'Gender', 'Sub-County', 'Ward', 'Highest Education', 'Status', 'Submitted At']);
        
        // Dynamically sub-querying the highest qualification from the new schema mapping
        $sql = "SELECT a.id, j.title, u.full_name, u.username, u.phone, u.email, u.gender, u.sub_county, u.ward, 
                (SELECT title FROM application_qualifications aq WHERE aq.application_id = a.id ORDER BY aq.year_completed DESC LIMIT 1) AS highest_education, 
                a.status, a.submitted_at 
                FROM applications a 
                JOIN users u ON a.user_id = u.id 
                JOIN jobs j ON a.job_id = j.id 
                WHERE a.job_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$job_id]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }
    
    // 2. SHORTLISTED ROSTER
    else if ($type === 'shortlisted_roster' && $job_id) {
        header('Content-Disposition: attachment; filename="Interview_Roster_JOB_'.$job_id.'.csv"');
        log_activity('Exported Interview Roster', 'Job', (string)$job_id);
        
        fputcsv($out, ['Appl ID', 'Candidate Name', 'ID Number', 'Phone', 'Email', 'Ward', 'App Status', 'Interview Date', 'Location', 'HR Panel']);
        
        $sql = "SELECT a.id, u.full_name, u.username, u.phone, u.email, u.ward, a.status AS app_status, i.interview_date, i.location, i.panel 
                FROM applications a 
                JOIN users u ON a.user_id = u.id 
                LEFT JOIN interviews i ON a.id = i.application_id 
                WHERE a.job_id = ? AND a.status IN ('Shortlisted', 'Interviewed', 'Hired')
                ORDER BY i.interview_date ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$job_id]);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }
    
    // 3. DEMOGRAPHICS REPORT
    else if ($type === 'demographics') {
        header('Content-Disposition: attachment; filename="Applicant_Demographics.csv"');
        log_activity('Exported Applicant Demographics', 'Analytics', 'ALL');
        
        fputcsv($out, ['Sub-County', 'Ward', 'Total Applicants']);
        
        $sql = "SELECT sub_county, ward, COUNT(id) AS total_candidates 
                FROM users 
                WHERE role = 'applicant' AND sub_county IS NOT NULL 
                GROUP BY sub_county, ward 
                ORDER BY sub_county ASC, total_candidates DESC";
        $stmt = $pdo->query($sql);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }
    
    // 4. VACANCY MATRIX
    else if ($type === 'vacancy_matrix') {
        header('Content-Disposition: attachment; filename="Job_Summary_Report.csv"');
        log_activity('Exported Job Vacancy Summary', 'Analytics', 'ALL');
        
        fputcsv($out, ['Job Code', 'Job Title', 'Department', 'Job Group', 'Terms', 'Ward Target', 'Total Applicants', 'Status', 'Deadline']);
        
        $sql = "SELECT j.job_code, j.title, j.department, j.job_group, j.terms, j.target_ward, 
                (SELECT COUNT(id) FROM applications a WHERE a.job_id = j.id) AS applicants_count,
                j.status, j.deadline 
                FROM jobs j 
                ORDER BY j.created_at DESC";
        $stmt = $pdo->query($sql);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }

    // 5. GLOBAL APPLICANT DATABASE
    else if ($type === 'global_applicants') {
        $target_ward = $_GET['ward'] ?? '';
        $filename = "Master_Applicant_Database" . ($target_ward ? "_Ward_" . preg_replace('/[^a-zA-Z0-9]/', '', $target_ward) : "_All") . ".csv";
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        log_activity('Exported Global Applicant Database', 'Analytics', $target_ward ?: 'ALL WARDS');
        
        fputcsv($out, ['Appl ID', 'Job Applied For', 'Full Name', 'ID Number', 'Phone', 'Email', 'Gender', 'Sub-County', 'Ward', 'Highest Education', 'Status', 'Submitted At']);
        
        $sql = "SELECT a.id, j.title AS job_title, u.full_name, u.username, u.phone, u.email, u.gender, u.sub_county, u.ward, 
                (SELECT title FROM application_qualifications aq WHERE aq.application_id = a.id ORDER BY aq.year_completed DESC LIMIT 1) AS highest_education, 
                a.status, a.submitted_at 
                FROM applications a 
                JOIN users u ON a.user_id = u.id 
                JOIN jobs j ON a.job_id = j.id";
                
        $params = [];
        if (!empty($target_ward)) {
            $sql .= " WHERE u.ward = ?";
            $params[] = $target_ward;
        }
        $sql .= " ORDER BY u.ward ASC, j.title ASC, u.full_name ASC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        while ($r = $stmt->fetch(PDO::FETCH_ASSOC)) {
            fputcsv($out, $r);
        }
    }

    fclose($out);
    exit;
}

// Fetch all jobs for dropdowns
$active_jobs = $pdo->query("SELECT id, title, job_code FROM jobs ORDER BY title ASC")->fetchAll();

// Fetch distinct wards for dropdowns
$wards_list = $pdo->query("SELECT DISTINCT ward FROM users WHERE role = 'applicant' AND ward IS NOT NULL AND ward != '' ORDER BY ward ASC")->fetchAll(PDO::FETCH_COLUMN);

// Extracted UI styling for polished cards
$extra_css = "
.report-card { gap: 16px; display: flex; flex-direction: column; justify-content: space-between; height: 100%; border: 1px solid var(--border); box-shadow: 0 4px 12px rgba(0,0,0,0.03); padding: 24px; border-radius: 12px; }
.report-icon { width: 44px; height: 44px; display: inline-flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 20px; box-shadow: 0 2px 6px rgba(0,0,0,0.08); margin-bottom: 8px; }
.report-card h3 { font-size: 16px; font-weight: 700; color: var(--green-900); margin-bottom: 8px; }
.report-card p { font-size: 12px; color: var(--text-light); line-height: 1.5; margin-bottom: 16px; flex: 1; }
.btn-icon { display: inline-flex; justify-content: center; align-items: center; gap: 8px; font-weight: 600; font-size: 13px; }
";

$page_title = 'Administrative Reports';
$active_nav = 'admin_dash';
$root = '../';
require_once __DIR__ . '/../includes/partials/header.php';
$admin_page = 'reports';
require_once __DIR__ . '/partials/admin_nav.php';
?>

<div class="page-wrap" style="background:#f9fafa; padding-bottom:100px;">
  <div class="container" style="max-width:1100px;">
    
    <div style="display:flex; justify-content:space-between; align-items:flex-end; margin-bottom:32px;">
      <div>
        <h2 style="font-size:24px; font-weight:800; color:var(--green-900);">Export Data & Reports</h2>
        <p style="color:var(--text-muted); font-size:15px; margin-top:6px; max-width:600px; line-height:1.5;">Review and download applicant data, interview lists, and demographic information directly into Excel CSV format.</p>
      </div>
      <div style="font-size:32px;">📊</div>
    </div>

    <!-- 2x2 Grid precisely matching original layout constraints -->
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:24px;">

        <!-- 1. FULL APPLICANT LIST -->
        <div class="card fade-up report-card">
            <div>
                <div class="report-icon" style="background:#f4f5f5;">📋</div>
                <h3>Applicant Data Export</h3>
                <p>Download a comprehensive list of all candidates who applied for a specific role. Includes contact information, demographics, and highest education details.</p>
            </div>
            
            <form method="GET" style="display:flex; gap:12px; margin:0;" target="_blank">
                <input type="hidden" name="download" value="master_manifest">
                <select name="job_id" class="form-select" required style="flex:1;">
                    <option value="">-- Select Job Posting --</option>
                    <?php foreach($active_jobs as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= h($j['job_code'].' - '.$j['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-outline-dark btn-sm btn-icon">Export CSV 📥</button>
            </form>
        </div>

        <!-- 2. SHORTLISTED INTERVIEW REPORT -->
        <div class="card fade-up report-card" style="animation-delay:0.1s;">
            <div>
                <div class="report-icon" style="background:#fefaec;">📅</div>
                <h3>Interview Roster</h3>
                <p>Filter only candidates marked as 'Shortlisted' or 'Interviewed'. This report includes specific interview dates, times, and HR panel assignments.</p>
            </div>
            
            <form method="GET" style="display:flex; gap:12px; margin:0;" target="_blank">
                <input type="hidden" name="download" value="shortlisted_roster">
                <select name="job_id" class="form-select" required style="flex:1;">
                    <option value="">-- Select Job Posting --</option>
                    <?php foreach($active_jobs as $j): ?>
                        <option value="<?= $j['id'] ?>"><?= h($j['job_code'].' - '.$j['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-gold btn-sm btn-icon">Download 📥</button>
            </form>
        </div>

        <!-- 3. DEMOGRAPHICS REPORT -->
        <div class="card fade-up report-card" style="animation-delay:0.2s;">
            <div>
                <div class="report-icon" style="background:#eef6fa;">🌍</div>
                <h3>County Applicant Demographics</h3>
                <p>View total applicant numbers grouped safely by Sub-County and Ward. Useful for monitoring equal opportunity and regional representation.</p>
            </div>
            <a href="?download=demographics" target="_blank" class="btn btn-outline-dark btn-sm btn-icon" style="width:100%;">Create Summary Report 📥</a>
        </div>

        <!-- 4. VACANCY SYSTEM MATRIX -->
        <div class="card fade-up report-card" style="animation-delay:0.3s;">
            <div>
                <div class="report-icon" style="background:var(--green-900); color:var(--gold);">🏢</div>
                <h3>Job Vacancy Summary</h3>
                <p>Download a high-level overview of all published job postings, their deadlines, open/closed statuses, and the total volume of applications received so far.</p>
            </div>
            <a href="?download=vacancy_matrix" target="_blank" class="btn btn-green btn-sm btn-icon" style="width:100%;">Create Vacancy Report 📥</a>
        </div>

        <!-- 5. GLOBAL APPLICANT DATABASE (Full Width) -->
        <div class="card fade-up report-card" style="animation-delay:0.4s; grid-column: 1 / -1;">
            <div style="display:flex; gap:24px; align-items:flex-start; flex-wrap:wrap;">
               <div class="report-icon" style="background:#f4ebf9; color:#6b21a8; font-size:24px; min-width:56px; height:56px;">🗃️</div>
               <div style="flex:1; min-width:300px;">
                    <h3 style="font-size:18px;">Master Applicant Database <span class="badge badge-success" style="vertical-align:middle; margin-left:8px;">New</span></h3>
                    <p style="font-size:13px; max-width:700px;">Download the entire merged list of all applicants across every single job posting. You can export the entire county-wide database, or filter it specifically to see all applicants from a single specific Ward.</p>
                    
                    <form method="GET" style="display:flex; gap:12px; margin:0; max-width: 500px; flex-wrap:wrap;" target="_blank">
                        <input type="hidden" name="download" value="global_applicants">
                        <select name="ward" class="form-select" style="flex:1; min-width:200px;">
                            <option value="">-- All Wards (Merged Database) --</option>
                            <?php foreach($wards_list as $w): ?>
                                <option value="<?= h($w) ?>"><?= h($w) ?> Ward</option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn btn-outline-dark btn-sm btn-icon" style="padding:10px 20px;">Export Master CSV 📥</button>
                    </form>
               </div>
            </div>
        </div>

    </div>

  </div>
</div>

<?php require_once __DIR__ . '/../includes/partials/footer.php'; ?>
