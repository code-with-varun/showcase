<?php
/**
 * API: Like counter incrementer & toggle
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';

if (session_status() === PHP_SESSION_NONE) session_start();

$id = $_POST['id'] ?? null;
if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Asset ID required']);
    exit;
}

$sessionKey = 'liked_' . $id;
$isCurrentlyLiked = !empty($_SESSION[$sessionKey]);

$pdo = getDB();

if ($isCurrentlyLiked) {
    // Already liked -> Toggle OFF (Unlike)
    unset($_SESSION[$sessionKey]);
    $stmt = $pdo->prepare("UPDATE assets SET likes_count = GREATEST(0, CAST(likes_count AS SIGNED) - 1) WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $liked = false;
} else {
    // Not yet liked -> Toggle ON (Like)
    $_SESSION[$sessionKey] = true;
    $stmt = $pdo->prepare("UPDATE assets SET likes_count = likes_count + 1 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $liked = true;
}

$getStmt = $pdo->prepare("SELECT likes_count FROM assets WHERE id = :id LIMIT 1");
$getStmt->execute([':id' => $id]);
$row = $getStmt->fetch();

echo json_encode([
    'success' => true,
    'liked'   => $liked,
    'likes'   => (int)($row['likes_count'] ?? 0)
]);
