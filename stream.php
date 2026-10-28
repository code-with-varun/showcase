<?php
/**
 * High-Performance Video & Media Streaming Engine
 * Supports HTTP 206 Partial Content / Byte-Range requests for instant scrubbing
 */

require_once __DIR__ . '/config/database.php';

$assetId = $_GET['v'] ?? $_GET['id'] ?? null;
if (!$assetId) {
    http_response_code(400);
    die("Asset ID required.");
}

$pdo = getDB();
$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = :id OR slug = :slug LIMIT 1");
$stmt->execute([':id' => $assetId, ':slug' => $assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    http_response_code(404);
    die("Asset not found.");
}

$filePath = ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $asset['file_path']);
if (!file_exists($filePath)) {
    http_response_code(404);
    die("Physical file missing on server.");
}

// Log CDN/Embed access if referrer present
$referrer = $_SERVER['HTTP_REFERER'] ?? null;
if ($referrer) {
    try {
        $logStmt = $pdo->prepare("INSERT INTO cdn_logs (asset_id, referrer, ip_hash) VALUES (:aid, :ref, :ip)");
        $ipHash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '');
        $logStmt->execute([':aid' => $asset['id'], ':ref' => substr($referrer, 0, 255), ':ip' => $ipHash]);
    } catch (Exception $e) {}
}

// Increment view count with light session debounce (skip for card hover previews)
if (!isset($_GET['preview'])) {
    if (session_status() === PHP_SESSION_NONE) session_start();
    $viewKey = 'viewed_' . $asset['id'];
    if (!isset($_SESSION[$viewKey])) {
        $_SESSION[$viewKey] = time();
        $updateView = $pdo->prepare("UPDATE assets SET views_count = views_count + 1 WHERE id = :id");
        $updateView->execute([':id' => $asset['id']]);
    }
}

$fileSize = filesize($filePath);
$mimeType = $asset['mime_type'] ?: 'application/octet-stream';
$fileName = basename($filePath);

// Clear output buffers
if (ob_get_level()) ob_end_clean();

$fp = @fopen($filePath, 'rb');
if (!$fp) {
    http_response_code(500);
    die("Could not open file for streaming.");
}

$start = 0;
$end = $fileSize - 1;

// Handle Byte-Range requests (Essential for YouTube-like scrubbing)
if (isset($_SERVER['HTTP_RANGE'])) {
    $range = $_SERVER['HTTP_RANGE'];
    if (preg_match('/bytes=\h*(\d+)-(\d*)[\D.*]?/i', $range, $matches)) {
        $start = intval($matches[1]);
        if (!empty($matches[2])) {
            $end = intval($matches[2]);
        }
    }

    if ($start > $end || $start > $fileSize - 1) {
        header('HTTP/1.1 416 Range Not Satisfiable');
        header("Content-Range: bytes */$fileSize");
        exit;
    }

    $length = $end - $start + 1;
    fseek($fp, $start);

    header('HTTP/1.1 206 Partial Content');
    header("Content-Range: bytes $start-$end/$fileSize");
} else {
    $length = $fileSize;
    header('HTTP/1.1 200 OK');
}

// Common headers
header("Content-Type: $mimeType");
header("Content-Length: $length");
header("Accept-Ranges: bytes");
header("Cache-Control: public, max-age=604800");
header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($filePath)) . ' GMT');
header('ETag: "' . md5($asset['id'] . $fileSize . filemtime($filePath)) . '"');

// If requested as download
if (isset($_GET['download']) && $_GET['download'] == '1') {
    header("Content-Disposition: attachment; filename=\"$fileName\"");
} else {
    header("Content-Disposition: inline; filename=\"$fileName\"");
}

// Stream the data in 64KB chunks
$bufferSize = 65536;
$bytesSent = 0;

while (!feof($fp) && ($bytesSent < $length) && (connection_status() === 0)) {
    $bytesToRead = min($bufferSize, $length - $bytesSent);
    $data = fread($fp, $bytesToRead);
    echo $data;
    flush();
    $bytesSent += strlen($data);
}

fclose($fp);
exit;
