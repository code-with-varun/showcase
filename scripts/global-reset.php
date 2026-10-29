<?php
/**
 * CLI: Global Reset Script
 * Run: php scripts/global-reset.php
 */

if (php_sapi_name() !== 'cli') {
    die("This script can only be run from the command line.\n");
}

require_once __DIR__ . '/../config/database.php';

echo "====================================================\n";
echo " DANGER: Runit Showcase Global System Reset\n";
echo "====================================================\n";
echo "This will:\n";
echo "  1. Truncate assets, tags, chapters, and CDN logs\n";
echo "  2. Delete all files in assets/ (videos, images, docs, thumbnails)\n";
echo "  3. Preserve users (superadmin) and categories\n";
echo "----------------------------------------------------\n";

echo "Type 'RESET' to confirm: ";
$handle = fopen("php://stdin", "r");
$line = trim(fgets($handle));

if ($line !== 'RESET') {
    echo "Aborted. No changes made.\n";
    exit(0);
}

$pdo = getDB();

echo "Truncating database tables...\n";
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
$pdo->exec("TRUNCATE TABLE asset_tags");
$pdo->exec("TRUNCATE TABLE video_chapters");
$pdo->exec("TRUNCATE TABLE cdn_logs");
$pdo->exec("TRUNCATE TABLE playlist_items");
$pdo->exec("TRUNCATE TABLE playlists");
$pdo->exec("TRUNCATE TABLE assets");
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
echo "[OK] Tables truncated.\n";

echo "Emptying assets folders...\n";
$subFolders = ['videos', 'images', 'documents', 'archives', 'thumbnails'];
$filesDeleted = 0;

function emptyDir(string $dir): int {
    $c = 0;
    if (!is_dir($dir)) return 0;
    foreach (scandir($dir) as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitignore') continue;
        $path = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($path)) {
            $c += emptyDir($path);
            @rmdir($path);
        } else {
            if (@unlink($path)) $c++;
        }
    }
    return $c;
}

foreach ($subFolders as $sf) {
    $t = ASSETS_DIR . DIRECTORY_SEPARATOR . $sf;
    if (is_dir($t)) {
        $filesDeleted += emptyDir($t);
    } else {
        @mkdir($t, 0755, true);
    }
}
echo "[OK] Files deleted: {$filesDeleted}\n";

$catalog = ROOT_DIR . DIRECTORY_SEPARATOR . 'assets.json';
if (file_exists($catalog)) {
    @file_put_contents($catalog, json_encode([
        'cdnBaseUrl' => getBaseUrl(),
        'updatedAt'  => date('c'),
        'totalAssets'=> 0,
        'categories' => ['videos'=>0, 'images'=>0, 'documents'=>0, 'archives'=>0],
        'assets'     => []
    ], JSON_PRETTY_PRINT));
    echo "[OK] assets.json reset.\n";
}

echo "Global reset finished successfully!\n";
