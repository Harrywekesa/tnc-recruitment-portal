<?php
$_GET['job'] = 1;
ob_start();
include 'public_shortlist_print.php';
$out = ob_get_clean();
echo strpos($out, 'Fatal error') !== false ? "PHP ERRORED\n" : "NO PHP ERROR\n";
file_put_contents('test_print_out.html', $out);
