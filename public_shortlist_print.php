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
    body { font-family: 'Times New Roman', Times, serif; color: #000; background: #fff; line-height: 1.4; padding: 0; margin: 0; }
    h1, h2, h3, h4 { margin: 0 0 10px 0; text-align: center; }
    .header { border-bottom: 2px solid #000; padding-bottom: 20px; margin-bottom: 30px; text-align: center; }
    .header img { width: 100px; height: auto; margin-bottom: 15px; }
    .details { text-align: center; font-size: 14px; margin-bottom: 30px; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 30px; font-size: 13.5px; }
    th, td { border: 1px solid #000; padding: 8px 10px; text-align: left; }
    th { background-color: #f2f2f2; font-weight: bold; text-transform: uppercase; font-size: 12px; }
    .footer { text-align: center; font-size: 12px; margin-top: 50px; padding-top: 15px; border-top: 1px solid #ccc; }
    @media print {
        #print-btn { display: none; }
    }
</style>
</head>
<body onload="window.print()">
    <div style="text-align: right; padding: 10px;">
        <button id="print-btn" onclick="window.print()" style="padding: 10px 20px; font-size: 16px; cursor: pointer; background: #166534; color: #fff; border: none; border-radius: 4px;">Click to Save PDF / Print</button>
    </div>

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
</body>
</html>
