<?php
require 'includes/db.php';
$pdo = db();
$sql = "SELECT id, ref_no, first_name, user_id, status FROM applications";
try {
    $cands = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    print_r($cands);
} catch (Exception $e) {
    echo "SQL ERROR: " . $e->getMessage();
}
