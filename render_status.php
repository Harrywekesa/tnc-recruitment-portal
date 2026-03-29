<?php
$_POST['ref'] = 'TNC2809A';
$_POST['id_no'] = '37197161';
$_SERVER['REQUEST_METHOD'] = 'POST';
ob_start();
include 'status.php';
file_put_contents('output_status.html', ob_get_clean());
echo "DONE rendered status.\n";
