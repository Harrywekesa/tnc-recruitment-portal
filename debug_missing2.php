<?php
require 'includes/db.php';
$pdo = db();

$sql = "SELECT a.id, a.ref_no, a.status, a.first_name, a.last_name, a.id_no, a.phone, a.email, a.user_id, u.username, u.full_name, u.phone as uphone, u.email as uemail, u.sub_county, u.ward
        FROM applications a 
        LEFT JOIN users u ON a.user_id = u.id 
        ORDER BY a.id DESC LIMIT 15";
$res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
echo json_encode($res, JSON_PRETTY_PRINT);
