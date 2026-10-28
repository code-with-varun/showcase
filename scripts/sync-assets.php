<?php
/**
 * CLI Sync Script: php scripts/sync-assets.php
 * Synchronizes dropped FTP / Filesystem assets with MySQL database
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../includes/SyncManager.php';

echo "========================================================\n";
echo " Showcase Tube & Asset CDN - Filesystem Sync Engine     \n";
echo "========================================================\n";
echo "Scanning assets directory: " . ASSETS_DIR . " ...\n";

$sync = new SyncManager();
$result = $sync->sync();

echo "Files Scanned : " . $result['scanned'] . "\n";
echo "Assets Added  : " . $result['added'] . "\n";
echo "Assets Updated: " . $result['updated'] . "\n";
echo "Assets Removed: " . $result['missing'] . "\n\n";

if (!empty($result['details'])) {
    echo "Activity Details:\n";
    foreach ($result['details'] as $detail) {
        echo " - " . $detail . "\n";
    }
} else {
    echo "Everything is already in sync with MySQL!\n";
}
echo "========================================================\n";
