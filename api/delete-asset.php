<?php
/**
 * API: Delete Asset
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$pdo = getDB();
$id = $_POST['id'] ?? null;
$deletePhysical = !empty($_POST['delete_physical']);

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Asset ID required']);
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$asset = $stmt->fetch();

if (!$asset) {
    echo json_encode(['success' => false, 'error' => 'Asset not found']);
    exit;
}

if ($deletePhysical) {
    $filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['file_path']);
    if (file_exists($filePath)) {
        @unlink($filePath);
    }
    if ($asset['thumbnail_path'] && str_contains($asset['thumbnail_path'], 'thumbnails/')) {
        $thumbPath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['thumbnail_path']);
        if (file_exists($thumbPath)) @unlink($thumbPath);
    }
}

// Delete related records
$pdo->prepare("DELETE FROM asset_tags WHERE asset_id = :id")->execute([':id' => $id]);
$pdo->prepare("DELETE FROM video_chapters WHERE asset_id = :id")->execute([':id' => $id]);
$pdo->prepare("DELETE FROM cdn_logs WHERE asset_id = :id")->execute([':id' => $id]);
$pdo->prepare("DELETE FROM assets WHERE id = :id")->execute([':id' => $id]);

echo json_encode(['success' => true, 'message' => 'Asset deleted successfully']);
