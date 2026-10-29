<?php
/**
 * API: Web UI File Uploader
 * Accepts browser file uploads and registers them in MySQL
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/SyncManager.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || empty($_FILES['file'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No file uploaded']);
    exit;
}

$file = $_FILES['file'];
if ($file['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'error' => 'Upload error code: ' . $file['error']]);
    exit;
}

$pdo = getDB();
$sync = new SyncManager($pdo);

$originalName = basename($file['name']);
$ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$assetType = $sync->determineAssetType($ext);

// Determine folder destination
$folderMap = [
    'video'    => 'videos',
    'image'    => 'images',
    'document' => 'documents',
    'archive'  => 'archives'
];
$targetSubfolder = $folderMap[$assetType] ?? 'documents';
$uploadDir = ASSETS_DIR . DIRECTORY_SEPARATOR . $targetSubfolder;
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Generate unique clean filename to avoid collisions
$sanitizedBase = $sync->slugify(pathinfo($originalName, PATHINFO_FILENAME));
$newFileName = $sanitizedBase . '-' . time() . '.' . $ext;
$targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to save uploaded file on server.']);
    exit;
}

$relPath = 'assets/' . $targetSubfolder . '/' . $newFileName;
$fileSize = filesize($targetPath);
$mimeType = $sync->detectMimeType($targetPath, $ext);

// Generate ID and Title
$id = $sync->generateUniqueId();
$title = !empty($_POST['title']) ? trim($_POST['title']) : $sync->formatTitle($originalName);
$slug = $sync->slugify($title) . '-' . substr($id, 0, 4);
$description = !empty($_POST['description']) ? trim($_POST['description']) : "Uploaded via Web Studio: {$originalName}";
$copyrightInfo = !empty($_POST['copyright_info']) ? trim($_POST['copyright_info']) : null;

// Visibility: 0=private, 1=unlisted (default), 2=public
$visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
$visibility = max(0, min(2, $visibility));
$isPublic   = ($visibility >= 1) ? 1 : 0;

// Project assignment
$project = !empty($_POST['project']) ? trim($_POST['project']) : null;

// Category ID resolution
$categoryId = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;
if (!$categoryId) {
    $catSlug = $targetSubfolder;
    $cStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :s LIMIT 1");
    $cStmt->execute([':s' => $catSlug]);
    $catRow = $cStmt->fetch();
    $categoryId = $catRow ? (int)$catRow['id'] : null;
}

// Handle optional thumbnail upload
$thumbRelPath = null;
if ($assetType === 'image') {
    $thumbRelPath = $relPath;
} elseif (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    $thumbName = 'thumb-' . $id . '.' . $thumbExt;
    $thumbDest = THUMBNAILS_DIR . DIRECTORY_SEPARATOR . $thumbName;
    if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0777, true);
    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbDest)) {
        $thumbRelPath = 'assets/thumbnails/' . $thumbName;
    }
}

// Insert into assets table
$insert = $pdo->prepare("
    INSERT INTO assets (
        id, title, slug, description, copyright_info, asset_type, category_id, project,
        file_path, file_name, file_size, mime_type, extension,
        thumbnail_path, source, visibility, is_public, created_at, updated_at
    ) VALUES (
        :id, :title, :slug, :desc, :copyright, :type, :cat_id, :proj,
        :path, :filename, :size, :mime, :ext,
        :thumb, 'web_upload', :visibility, :is_public, NOW(), NOW()
    )
");

$insert->execute([
    ':id'         => $id,
    ':title'      => $title,
    ':slug'       => $slug,
    ':desc'       => $description,
    ':copyright'  => $copyrightInfo,
    ':type'       => $assetType,
    ':cat_id'     => $categoryId,
    ':proj'       => $project,
    ':path'       => $relPath,
    ':filename'   => $newFileName,
    ':size'       => $fileSize,
    ':mime'       => $mimeType,
    ':ext'        => $ext,
    ':thumb'      => $thumbRelPath,
    ':visibility' => $visibility,
    ':is_public'  => $isPublic,
]);

// Handle tags
if (!empty($_POST['tags'])) {
    $rawTags = explode(',', $_POST['tags']);
    $tagStmt = $pdo->prepare("INSERT INTO asset_tags (asset_id, tag) VALUES (:id, :tag)");
    foreach ($rawTags as $t) {
        $t = trim($t);
        if ($t) $tagStmt->execute([':id' => $id, ':tag' => $t]);
    }
}

echo json_encode([
    'success' => true,
    'asset' => [
        'id'        => $id,
        'title'     => $title,
        'type'      => $assetType,
        'url'       => getBaseUrl() . '/watch.php?v=' . $id,
        'streamUrl' => getBaseUrl() . '/stream.php?v=' . $id,
        'cdnUrl'    => getBaseUrl() . '/' . $relPath,
    ]
]);
