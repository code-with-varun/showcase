<?php
/**
 * API: Gallery Images Selector for Thumbnails
 * Fetches available images from image assets and thumbnails folder
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$baseUrl = getBaseUrl();

// 1. Fetch images from assets table
$stmt = $pdo->query("
    SELECT id, title, file_path, thumbnail_path, project, created_at 
    FROM assets 
    WHERE asset_type = 'image' OR extension IN ('jpg', 'jpeg', 'png', 'webp')
    ORDER BY created_at DESC 
    LIMIT 60
");
$dbImages = $stmt->fetchAll(PDO::FETCH_ASSOC);

$gallery = [];
$seenPaths = [];

foreach ($dbImages as $img) {
    $p = str_replace('\\', '/', $img['file_path']);
    if (isset($seenPaths[$p])) continue;
    $seenPaths[$p] = true;

    $gallery[] = [
        'id'        => $img['id'],
        'title'     => $img['title'],
        'path'      => $p,
        'url'       => $baseUrl . '/' . ltrim($p, '/'),
        'project'   => $img['project'] ?? 'Gallery',
        'source'    => 'asset'
    ];
}

// 2. Scan assets/thumbnails directory for existing generated thumbnails
$thumbDir = THUMBNAILS_DIR;
if (is_dir($thumbDir)) {
    $files = scandir($thumbDir);
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || str_starts_with($file, '.')) continue;
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png', 'webp'])) {
            $p = 'assets/thumbnails/' . $file;
            if (isset($seenPaths[$p])) continue;
            $seenPaths[$p] = true;

            $gallery[] = [
                'id'        => 'thumb_' . md5($file),
                'title'     => pathinfo($file, PATHINFO_FILENAME),
                'path'      => $p,
                'url'       => $baseUrl . '/' . $p,
                'project'   => 'Thumbnails',
                'source'    => 'thumbnail'
            ];
        }
    }
}

echo json_encode([
    'success' => true,
    'images'  => $gallery
]);
