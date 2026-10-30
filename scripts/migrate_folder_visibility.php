<?php
require_once __DIR__ . '/../config/database.php';

try {
    $pdo = getDB();
    $sql = "CREATE TABLE IF NOT EXISTS folder_visibility (
      id INT AUTO_INCREMENT PRIMARY KEY,
      folder_path VARCHAR(255) NOT NULL UNIQUE,
      visibility TINYINT(1) NOT NULL DEFAULT 1,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $pdo->exec($sql);
    echo "✓ Table folder_visibility created successfully!\n";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}

