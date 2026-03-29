<?php
require 'includes/db.php';
$pdo = db();
$sql = "SELECT a.id AS app_id, a.job_id, a.status AS app_status, u.full_name, u.phone, u.email, j.title AS job_title, 
        i.interview_date, i.interview_time, i.venue, i.mode AS interview_mode, i.requirements, i.panel_members, i.status AS int_status 
        FROM applications a 
        JOIN users u ON a.user_id = u.id 
        JOIN jobs j ON a.job_id = j.id
        LEFT JOIN interviews i ON a.id = i.application_id
        WHERE a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed')
        ORDER BY i.interview_date IS NULL ASC, i.interview_date ASC, a.submitted_at DESC";

try {
    $cands = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    print_r($cands);
} catch (Exception $e) {
    echo "SQL ERROR: " . $e->getMessage();
}
