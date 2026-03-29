<?php
require_once __DIR__ . '/includes/auth.php';
logout_user();
header('Location: /tnc-portal/index.php');
exit;
