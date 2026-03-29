<?php
require 'includes/db.php';
$pdo = db();

$sql = "SELECT a.id AS app_id, a.job_id, a.ref_no, a.status AS app_status, 
        COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS full_name, 
        COALESCE(u.phone, a.phone) AS phone, 
        COALESCE(u.email, a.email) AS email, 
        j.title AS job_title, 
        i.interview_date, i.interview_time, i.venue, i.mode AS interview_mode, i.requirements, i.panel_members, i.status AS int_status 
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        JOIN jobs j ON a.job_id = j.id
        LEFT JOIN interviews i ON a.id = i.application_id
        WHERE a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed')
        ORDER BY i.interview_date IS NULL ASC, i.interview_date ASC, a.submitted_at DESC";

$cands = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

echo "--- INTERVIEWS RENDER CHECK ---\n";
foreach($cands as $c) {
    echo "Ref: {$c['ref_no']} | Name: '{$c['full_name']}' | Phone: '{$c['phone']}' | Email: '{$c['email']}' | AppStatus: {$c['app_status']} | IntDate: '{$c['interview_date']}'\n";
}

$sql2 = "SELECT a.ref_no, a.id_no, COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS full_name, 
        COALESCE(u.sub_county, a.sub_county) AS sub_county, 
        COALESCE(u.ward, a.ward) AS ward
        FROM applications a LEFT JOIN users u ON a.user_id = u.id WHERE a.status = 'Shortlisted'";
$cands2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);

echo "\n--- STATUS RENDER CHECK ---\n";
foreach($cands2 as $c) {
    echo "Ref: {$c['ref_no']} | Name: '{$c['full_name']}' | Sub: '{$c['sub_county']}' | Ward: '{$c['ward']}'\n";
}
