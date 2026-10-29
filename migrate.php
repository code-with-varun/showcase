<?php
/**
 * ONE-TIME DB Migration Script
 * Run this ONCE on the Hostinger server via browser, then DELETE it.
 * URL: https://yourdomain.com/migrate.php
 * 
 * Adds: visibility column (0=private, 1=unlisted, 2=public)
 *       copyright_info column
 * Migrates: existing is_public data to visibility
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Security: Only allow admin to run this
if (!isAdminLoggedIn()) {
    die('<h2>Access denied. Please <a href="login.php">log in as admin</a> first.</h2>');
}

$pdo = getDB();
$results = [];
$errors  = [];

function runSql(PDO $pdo, string $label, string $sql): string {
    try {
        $pdo->exec($sql);
        return "<tr><td>✅</td><td>{$label}</td><td class='text-success'>OK</td></tr>";
    } catch (PDOException $e) {
        if (str_contains($e->getMessage(), 'Duplicate column') || str_contains($e->getMessage(), 'already exists')) {
            return "<tr><td>⏭️</td><td>{$label}</td><td class='text-warning'>Already exists — skipped</td></tr>";
        }
        return "<tr><td>❌</td><td>{$label}</td><td class='text-danger'>" . htmlspecialchars($e->getMessage()) . "</td></tr>";
    }
}

$steps = [];

// Step 1: Add visibility column
$steps[] = runSql($pdo, 'Add visibility column',
    "ALTER TABLE assets ADD COLUMN visibility TINYINT(1) NOT NULL DEFAULT 2 
     COMMENT '0=private, 1=unlisted, 2=public'"
);

// Step 2: Add copyright_info column
$steps[] = runSql($pdo, 'Add copyright_info column',
    "ALTER TABLE assets ADD COLUMN copyright_info TEXT NULL 
     COMMENT 'Copyright notice, license, or attribution info'"
);

// Step 3: Add index on visibility
$steps[] = runSql($pdo, 'Add index on visibility',
    "ALTER TABLE assets ADD INDEX idx_visibility (visibility)"
);

// Step 4: Migrate existing is_public=1 to visibility=2 (public)
$steps[] = runSql($pdo, 'Migrate is_public=1 → visibility=2 (public)',
    "UPDATE assets SET visibility = 2 WHERE is_public = 1 AND visibility = 2"
);

// Step 5: Migrate existing is_public=0 to visibility=0 (private)
$steps[] = runSql($pdo, 'Migrate is_public=0 → visibility=0 (private)',
    "UPDATE assets SET visibility = 0 WHERE is_public = 0"
);

// Step 6: Create playlists table
$steps[] = runSql($pdo, 'Create playlists table',
    "CREATE TABLE IF NOT EXISTS playlists (
      id VARCHAR(36) PRIMARY KEY,
      title VARCHAR(255) NOT NULL,
      slug VARCHAR(255) NOT NULL,
      description TEXT NULL,
      thumbnail_path VARCHAR(500) NULL,
      visibility TINYINT(1) DEFAULT 1 COMMENT '0=private, 1=unlisted, 2=public',
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
      INDEX idx_playlist_created (created_at),
      INDEX idx_playlist_visibility (visibility)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Step 7: Create playlist_items table
$steps[] = runSql($pdo, 'Create playlist_items table',
    "CREATE TABLE IF NOT EXISTS playlist_items (
      id INT AUTO_INCREMENT PRIMARY KEY,
      playlist_id VARCHAR(36) NOT NULL,
      asset_id VARCHAR(36) NOT NULL,
      position INT UNSIGNED NOT NULL DEFAULT 0,
      created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_pi_playlist (playlist_id),
      INDEX idx_pi_asset (asset_id),
      INDEX idx_pi_position (position)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

// Verify
$count = $pdo->query("SELECT COUNT(*) FROM assets WHERE visibility IS NOT NULL")->fetchColumn();
$steps[] = "<tr><td>🔍</td><td>Verification</td><td class='text-success'>{$count} assets have visibility set</td></tr>";

?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DB Migration - Runit Showcase</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width: 720px;">
  <h2 class="mb-3"><i class="bi bi-database-gear"></i> DB Migration Results</h2>
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <table class="table table-hover mb-0">
        <thead class="table-dark"><tr><th></th><th>Step</th><th>Status</th></tr></thead>
        <tbody>
          <?= implode("\n", $steps) ?>
        </tbody>
      </table>
    </div>
  </div>
  <div class="alert alert-warning mt-3">
    <strong>⚠️ Important:</strong> Migration complete! Please <strong>delete this file (<code>migrate.php</code>)</strong> from the server now for security.
  </div>
  <a href="index.php" class="btn btn-danger">← Go to Showcase</a>
  <a href="studio.php" class="btn btn-outline-secondary ms-2">Creator Studio</a>
</div>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</body>
</html>
