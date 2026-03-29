<?php
declare(strict_types=1);
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/constants.php';

$pdo = db();
$job_id = (int)($_GET['job'] ?? 0);

if (!$job_id) {
    die("Invalid Job ID Reference.");
}

$stmt = $pdo->prepare("
    SELECT j.title, j.job_code, j.department 
    FROM jobs j 
    WHERE j.id = ?
");
$stmt->execute([$job_id]);
$job = $stmt->fetch();

if (!$job) {
    die("Job not found.");
}

$stmt = $pdo->prepare("
    SELECT a.id_no, 
           COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS full_name,
           COALESCE(u.username, a.id_no) AS display_id,
           COALESCE(u.sub_county, a.sub_county) AS sub_county,
           COALESCE(u.ward, a.ward) AS ward
    FROM applications a 
    LEFT JOIN users u ON a.user_id = u.id
    WHERE a.job_id = ? AND a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed', 'Hired')
    ORDER BY full_name
");
$stmt->execute([$job_id]);
$shortlisted = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Official Shortlist: <?= h($job['title']) ?> PDF Print</title>
<style>
    @page { margin: 15mm; size: A4 portrait; }
    body { font-family: 'Times New Roman', Times, serif; color: #000; background: #eaedf0; line-height: 1.4; padding: 20px; }
    #wrapper { max-width: 800px; margin: 0 auto; background: #fff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
    h1, h2, h3, h4 { margin: 0 0 10px 0; text-align: center; }
    .header { border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; text-align: center; }
    .details { text-align: center; font-size: 14px; margin-bottom: 30px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13.5px; }
    th, td { border: 1px solid #000; padding: 8px 10px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 12px; }
    .footer { text-align: center; font-size: 12px; margin-top: 50px; padding-top: 15px; border-top: 1px solid #ccc; }
    .status-msg { text-align: center; font-family: sans-serif; font-size: 16px; color: #166534; font-weight: 600; padding: 15px; background: #dcfce7; border-radius: 6px; margin-bottom: 20px; }
</style>
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.10.1/html2pdf.bundle.min.js"></script>
</head>
<body>
    <div id="wrapper">
        <div id="status-indicator" class="status-msg">Generating Official PDF Document... Please wait.</div>
        
        <div id="pdf-content" style="background:#fff; padding:20px;">
            <div class="header">
        <h2>COUNTY GOVERNMENT OF TRANS NZOIA</h2>
        <h3>COUNTY PUBLIC SERVICE BOARD</h3>
        <div>Official Shortlisted Candidates Document</div>
    </div>

    <div class="details">
        <strong>Position / Job Title:</strong> <?= h($job['title']) ?><br>
        <strong>Job Reference:</strong> <?= h($job['job_code']) ?><br>
        <strong>Department:</strong> <?= h($job['department']) ?><br>
        <strong>Total Shortlisted:</strong> <?= count($shortlisted) ?> Candidates
    </div>

    <table>
        <thead>
            <tr>
                <th style="width: 8%;">S/N</th>
                <th style="width: 38%;">Candidate Name</th>
                <th style="width: 18%;">National ID</th>
                <th style="width: 18%;">Sub-County</th>
                <th style="width: 18%;">Ward</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($shortlisted)): ?>
                <tr><td colspan="5" style="text-align:center;">No candidates published yet.</td></tr>
            <?php else: ?>
                <?php foreach ($shortlisted as $i => $a): 
                    $id_display = (strlen((string)($a['display_id'] ?? '')) > 4) ? substr((string)$a['display_id'], 0, -4) . '****' : ($a['display_id'] ?? '—');
                ?>
                <tr>
                    <td><?= $i + 1 ?></td>
                    <td><strong style="text-transform:uppercase;"><?= h($a['full_name']) ?></strong></td>
                    <td style="font-family: monospace; font-size: 14px;"><?= h($id_display) ?></td>
                    <td><?= h($a['sub_county'] ?? '—') ?></td>
                    <td><?= h($a['ward'] ?? '—') ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>

            <div class="footer">
                Generated on <?= date('d F Y, g:i A') ?> — TNC Recruitment Portal<br>
                <strong>This is an official document of the County Government of Trans Nzoia.</strong>
            </div>
        </div>
    </div>

<script>
window.onload = function() {
    var element = document.getElementById('pdf-content');
    
    // Configure PDF options
    var opt = {
        margin:       10,
        filename:     'Official_Shortlist_<?= preg_replace('/[^a-zA-Z0-9]+/', '_', $job['job_code']) ?>.pdf',
        image:        { type: 'jpeg', quality: 0.98 },
        html2canvas:  { scale: 2, useCORS: true },
        jsPDF:        { unit: 'mm', format: 'a4', orientation: 'portrait' }
    };
    
    // Generate and Download PDF
    html2pdf().set(opt).from(element).save().then(function() {
        document.getElementById('status-indicator').innerText = "PDF downloaded successfully! You can now close this tab.";
        setTimeout(function(){ window.close(); }, 3000);
    });
};
</script>
</body>
</html>
