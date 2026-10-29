<?php
/**
 * API: Global System Reset
 * Truncates asset database tables and empties all files in assets/ folder
 * Superadmin authentication + password verification required
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login as superadmin.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed. POST required.']);
    exit;
}

$pdo = getDB();

// Verify current superadmin password for safety
$password = $_POST['password'] ?? '';
$confirmText = trim($_POST['confirm_text'] ?? '');

if (empty($password)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Admin password is required to authorize global reset.']);
    exit;
}

if (strtoupper($confirmText) !== 'RESET') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Please type RESET to confirm this irreversible action.']);
    exit;
}

// Check admin credentials
$adminId = $_SESSION['admin_id'] ?? null;
if ($adminId) {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $adminId]);
    $hash = $stmt->fetchColumn();
} else {
    $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $_SESSION['admin_username'] ?? 'superadmin']);
    $hash = $stmt->fetchColumn();
}

if (!$hash || !password_verify($password, $hash)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Incorrect admin password. Global reset aborted.']);
    exit;
}

// -------------------------------------------------------------
// 1. Truncate Database Tables
// -------------------------------------------------------------
$deletedAssetsCount = 0;
try {
    $deletedAssetsCount = (int)$pdo->query("SELECT COUNT(*) FROM assets")->fetchColumn();

    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("TRUNCATE TABLE asset_tags");
    $pdo->exec("TRUNCATE TABLE video_chapters");
    $pdo->exec("TRUNCATE TABLE cdn_logs");
    $pdo->exec("TRUNCATE TABLE playlist_items");
    $pdo->exec("TRUNCATE TABLE playlists");
    $pdo->exec("TRUNCATE TABLE assets");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");

    // Ensure default categories remain intact
    $pdo->exec("
        INSERT INTO categories (name, slug, icon) VALUES
        ('Videos', 'videos', 'bi-camera-video'),
        ('Motion Comics', 'motion-comics', 'bi-film'),
        ('Images & Artwork', 'images', 'bi-image'),
        ('Documents & PDFs', 'documents', 'bi-file-earmark-text'),
        ('Archives & Zips', 'archives', 'bi-file-earmark-zip')
        ON DUPLICATE KEY UPDATE name=VALUES(name)
    ");
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database reset error: ' . $e->getMessage()]);
    exit;
}

// -------------------------------------------------------------
// 2. Empty Assets Folders on Filesystem
// -------------------------------------------------------------
$filesDeleted = 0;
$directoriesCleaned = 0;

$subFolders = ['videos', 'images', 'documents', 'archives', 'thumbnails'];

function emptyFolderRecursively(string $dir, bool $isRootDir = false): int {
    $count = 0;
    if (!is_dir($dir)) return 0;
    $items = scandir($dir);
    if ($items === false) return 0;

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') continue;
        if ($item === '.gitignore') continue; // preserve gitkeep or gitignore

        $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
        if (is_dir($fullPath)) {
            $count += emptyFolderRecursively($fullPath, false);
            @rmdir($fullPath);
        } elseif (is_file($fullPath)) {
            if (@unlink($fullPath)) {
                $count++;
            }
        }
    }
    return $count;
}

foreach ($subFolders as $folder) {
    $targetDir = ASSETS_DIR . DIRECTORY_SEPARATOR . $folder;
    if (!is_dir($targetDir)) {
        @mkdir($targetDir, 0755, true);
    } else {
        $filesDeleted += emptyFolderRecursively($targetDir);
    }
    $directoriesCleaned++;
}

// Clear any loose files in assets root (except folders and .gitignore)
if (is_dir(ASSETS_DIR)) {
    foreach (scandir(ASSETS_DIR) as $item) {
        if ($item === '.' || $item === '..' || $item === '.gitignore') continue;
        $p = ASSETS_DIR . DIRECTORY_SEPARATOR . $item;
        if (is_file($p)) {
            if (@unlink($p)) $filesDeleted++;
        }
    }
}

// -------------------------------------------------------------
// 3. Reset assets.json catalog if exists
// -------------------------------------------------------------
$catalogPath = ROOT_DIR . DIRECTORY_SEPARATOR . 'assets.json';
if (file_exists($catalogPath)) {
    $emptyCatalog = [
        'cdnBaseUrl'   => getBaseUrl(),
        'updatedAt'    => date('c'),
        'totalAssets'  => 0,
        'categories'   => [
            'videos'    => 0,
            'images'    => 0,
            'documents' => 0,
            'archives'  => 0
        ],
        'assets'       => []
    ];
    @file_put_contents($catalogPath, json_encode($emptyCatalog, JSON_PRETTY_PRINT));
}

echo json_encode([
    'success'              => true,
    'message'              => 'Global reset complete! All assets and database records have been purged.',
    'db_records_removed'   => $deletedAssetsCount,
    'files_deleted'        => $filesDeleted,
    'directories_refreshed'=> $directoriesCleaned
]);
