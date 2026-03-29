<?php
require_once __DIR__ . '/includes/db.php';
$pdo = db();

try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS activity_logs (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        entity_type VARCHAR(50) NULL,
        entity_id VARCHAR(50) NULL,
        ip_address VARCHAR(45) NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user (user_id),
        INDEX idx_entity (entity_type, entity_id)
    )");
    echo "TABLE CREATED VERIFIED.\n";
} catch (Exception $e) {
    echo "ERROR CREATING LOGS TABLE: " . $e->getMessage() . "\n";
}
