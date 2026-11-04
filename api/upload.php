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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Normalise uploaded files into an array (supports both single file and multiple files[])
$filesToProcess = [];
if (!empty($_FILES['files']['name'])) {
    if (is_array($_FILES['files']['name'])) {
        $count = count($_FILES['files']['name']);
        for ($i = 0; $i < $count; $i++) {
            if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                $filesToProcess[] = [
                    'name'     => $_FILES['files']['name'][$i],
                    'tmp_name' => $_FILES['files']['tmp_name'][$i],
                    'size'     => $_FILES['files']['size'][$i],
                    'error'    => $_FILES['files']['error'][$i]
                ];
            }
        }
    } else if ($_FILES['files']['error'] === UPLOAD_ERR_OK) {
        $filesToProcess[] = $_FILES['files'];
    }
} elseif (!empty($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $filesToProcess[] = $_FILES['file'];
}

if (empty($filesToProcess)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'No files uploaded or upload error occurred.']);
    exit;
}

$pdo = getDB();
$sync = new SyncManager($pdo);
$uploadedAssets = [];
$errors = [];

// Determine shared parameters
$sharedTitle       = !empty($_POST['title']) ? trim($_POST['title']) : '';
$sharedDescription = !empty($_POST['description']) ? trim($_POST['description']) : '';
$copyrightInfo     = !empty($_POST['copyright_info']) ? trim($_POST['copyright_info']) : null;
$visibility        = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
$visibility        = max(0, min(2, $visibility));
$isPublic          = ($visibility >= 1) ? 1 : 0;
$project           = !empty($_POST['project']) ? trim($_POST['project']) : null;
$categoryId        = !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null;

// Handle optional custom thumbnail provided once for this batch
$selectedGalleryThumb = null;
if (!empty($_POST['selected_gallery_thumbnail'])) {
    $gal = trim($_POST['selected_gallery_thumbnail']);
    if (str_starts_with($gal, 'assets/') && file_exists(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $gal))) {
        $selectedGalleryThumb = $gal;
    }
}

$uploadedThumbPath = null;
if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
    $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
    $thumbName = 'thumb-batch-' . time() . '-' . bin2hex(random_bytes(3)) . '.' . $thumbExt;
    $thumbDest = THUMBNAILS_DIR . DIRECTORY_SEPARATOR . $thumbName;
    if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0777, true);
    if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbDest)) {
        $uploadedThumbPath = 'assets/thumbnails/' . $thumbName;
    }
}

foreach ($filesToProcess as $file) {
    $originalName = basename($file['name']);
    $ext = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
    $assetType = $sync->determineAssetType($ext);

    // Folder destination
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

    $sanitizedBase = $sync->slugify(pathinfo($originalName, PATHINFO_FILENAME));
    $newFileName = $sanitizedBase . '-' . time() . '-' . substr(bin2hex(random_bytes(3)), 0, 4) . '.' . $ext;
    $targetPath = $uploadDir . DIRECTORY_SEPARATOR . $newFileName;

    if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
        $errors[] = "Failed to store: {$originalName}";
        continue;
    }

    $relPath = 'assets/' . $targetSubfolder . '/' . $newFileName;
    $fileSize = filesize($targetPath);
    $mimeType = $sync->detectMimeType($targetPath, $ext);

    $id = $sync->generateUniqueId();
    // Use custom title only if a single file was uploaded, otherwise derive from filename
    $fileTitle = (count($filesToProcess) === 1 && $sharedTitle !== '') ? $sharedTitle : $sync->formatTitle($originalName);
    $slug = $sync->slugify($fileTitle) . '-' . substr($id, 0, 4);
    $desc = $sharedDescription ?: "Uploaded via Web Studio: {$originalName}";

    // Resolve category
    $fileCatId = $categoryId;
    if (!$fileCatId) {
        $catSlug = $targetSubfolder;
        $cStmt = $pdo->prepare("SELECT id FROM categories WHERE slug = :s LIMIT 1");
        $cStmt->execute([':s' => $catSlug]);
        $catRow = $cStmt->fetch();
        $fileCatId = $catRow ? (int)$catRow['id'] : null;
    }

    // Determine thumbnail
    $thumbRelPath = null;
    if ($assetType === 'image') {
        $thumbRelPath = $relPath;
    } elseif ($selectedGalleryThumb) {
        $thumbRelPath = $selectedGalleryThumb;
    } elseif ($uploadedThumbPath) {
        $thumbRelPath = $uploadedThumbPath;
    }

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
        ':title'      => $fileTitle,
        ':slug'       => $slug,
        ':desc'       => $desc,
        ':copyright'  => $copyrightInfo,
        ':type'       => $assetType,
        ':cat_id'     => $fileCatId,
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

    $uploadedAssets[] = [
        'id'    => $id,
        'title' => $fileTitle,
        'type'  => $assetType,
        'path'  => $relPath,
        'size'  => $fileSize
    ];
}

if (!empty($uploadedAssets)) {
    echo json_encode([
        'success' => true,
        'count'   => count($uploadedAssets),
        'assets'  => $uploadedAssets,
        'errors'  => $errors
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error'   => !empty($errors) ? implode('; ', $errors) : 'Upload failed.'
    ]);
}
exit;
