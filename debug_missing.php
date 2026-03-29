<?php
require 'includes/db.php';
$pdo = db();

echo "--- INTERVIEWS (Shortlists) TRACE ---\n";
$sql = "SELECT a.id, a.ref_no, a.status, a.user_id, a.first_name, a.last_name, u.full_name, u.username,
        COALESCE(u.full_name, CONCAT(a.first_name, ' ', a.last_name)) AS display_name,
        COALESCE(u.phone, a.phone) AS display_phone
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        WHERE a.status IN ('Shortlisted', 'Interview Scheduled', 'Interviewed')";
$res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
foreach($res as $r) {
    echo "ID: {$r['id']} | Ref: {$r['ref_no']} | User: '{$r['user_id']}' | User_Full: '{$r['full_name']}' | a_first: '{$r['first_name']}' | Display: '{$r['display_name']}'\n";
}

echo "\n--- STATUS TRACE (For App ID 11 typically) ---\n";
$sql2 = "SELECT a.id, a.ref_no, a.user_id, a.id_no, u.username, u.full_name, u.sub_county, u.ward
         FROM applications a LEFT JOIN users u ON a.user_id = u.id ORDER BY a.id DESC LIMIT 5";
$res2 = $pdo->query($sql2)->fetchAll(PDO::FETCH_ASSOC);
foreach($res2 as $r) {
    echo "ID: {$r['id']} | Ref: {$r['ref_no']} | a.id_no: '{$r['id_no']}' | u.username(ID): '{$r['username']}' | DisplayName: '{$r['full_name']}' | SubC: '{$r['sub_county']}'\n";
}
