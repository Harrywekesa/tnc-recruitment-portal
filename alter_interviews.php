<?php
require 'includes/db.php';
$pdo = db();
try {
    $pdo->exec("ALTER TABLE interviews ADD COLUMN requirements VARCHAR(500) AFTER mode");
    echo "SUCCESS\n";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "SUCCESS (already exists)\n";
    } else {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
}
