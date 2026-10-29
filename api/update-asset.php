<?php
/**
 * API: Update Asset Metadata
 * Edits details for assets (whether uploaded via Web UI or synced via FTP)
 * Supports 3-state visibility: 0=private, 1=unlisted, 2=public
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

$title         = !empty($_POST['title']) ? trim($_POST['title']) : $asset['title'];
$desc          = isset($_POST['description']) ? trim($_POST['description']) : $asset['description'];
$copyrightInfo = isset($_POST['copyright_info']) ? trim($_POST['copyright_info']) : ($asset['copyright_info'] ?? '');
$categoryId    = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : $asset['category_id'];
$project       = isset($_POST['project']) ? trim($_POST['project']) : ($asset['project'] ?? null);
$duration      = isset($_POST['duration_seconds']) ? (int)$_POST['duration_seconds'] : $asset['duration_seconds'];

// 3-state visibility: 0=private, 1=unlisted, 2=public
$visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : ($asset['visibility'] ?? 2);
$visibility = max(0, min(2, $visibility)); // clamp 0-2
$isPublic   = ($visibility >= 1) ? 1 : 0; // legacy compatibility

$thumbPath = $asset['thumbnail_path'];
if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    $thumbName = 'thumb-' . $id . '-' . time() . '.' . $thumbExt;
    $thumbDest = THUMBNAILS_DIR . DIRECTORY_SEPARATOR . $thumbName;
    if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0777, true);
    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbDest)) {
        $thumbPath = 'assets/thumbnails/' . $thumbName;
    }
}

// Update assets record
$upStmt = $pdo->prepare("
    UPDATE assets 
    SET title = :title, description = :desc, copyright_info = :copyright,
        category_id = :cat, project = :proj,
        thumbnail_path = :thumb, visibility = :visibility, is_public = :pub,
        duration_seconds = :dur, updated_at = NOW()
    WHERE id = :id
");
$upStmt->execute([
    ':title'      => $title,
    ':desc'       => $desc,
    ':copyright'  => ($copyrightInfo !== '' ? $copyrightInfo : null),
    ':cat'        => $categoryId,
    ':proj'       => ($project !== '' ? $project : null),
    ':thumb'      => $thumbPath,
    ':visibility' => $visibility,
    ':pub'        => $isPublic,
    ':dur'        => $duration,
    ':id'         => $id
]);

// Update tags
if (isset($_POST['tags'])) {
    $delTags = $pdo->prepare("DELETE FROM asset_tags WHERE asset_id = :id");
    $delTags->execute([':id' => $id]);

    $tagList = explode(',', $_POST['tags']);
    $insTag = $pdo->prepare("INSERT INTO asset_tags (asset_id, tag) VALUES (:id, :t)");
    foreach ($tagList as $tag) {
        $t = trim($tag);
        if ($t !== '') $insTag->execute([':id' => $id, ':t' => $t]);
    }
}

// Update chapters (if provided)
if (isset($_POST['chapters'])) {
    $delChap = $pdo->prepare("DELETE FROM video_chapters WHERE asset_id = :id");
    $delChap->execute([':id' => $id]);

    $lines = explode("\n", $_POST['chapters']);
    $insChap = $pdo->prepare("INSERT INTO video_chapters (asset_id, timestamp_seconds, title) VALUES (:id, :sec, :ctitle)");
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$line) continue;
        // Parse format: "01:23 Intro" or "1:23:45 Scene"
        if (preg_match('/^(\d+):(\d+)(?::(\d+))?\s+(.+)$/', $line, $m)) {
            if (!empty($m[3])) {
                $seconds = ((int)$m[1] * 3600) + ((int)$m[2] * 60) + (int)$m[3];
                $cTitle = trim($m[4]);
            } else {
                $seconds = ((int)$m[1] * 60) + (int)$m[2];
                $cTitle = trim($m[4]);
            }
            $insChap->execute([':id' => $id, ':sec' => $seconds, ':ctitle' => $cTitle]);
        }
    }
}

echo json_encode([
    'success' => true,
    'message' => 'Asset updated successfully',
    'asset'   => [
        'id'         => $id,
        'title'      => $title,
        'visibility' => $visibility,
        'thumbnail'  => $thumbPath ? getBaseUrl() . '/' . $thumbPath : null
    ]
]);
