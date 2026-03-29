<?php
require 'includes/db.php';
$pdo = db();
$stmt = $pdo->query('DESCRIBE interviews');
print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
