<?php
/**
 * API: Like counter incrementer
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
if (!empty($_SESSION[$sessionKey])) {
    echo json_encode(['success' => false, 'alreadyLiked' => true]);
    exit;
}

$_SESSION[$sessionKey] = true;
$pdo = getDB();
$stmt = $pdo->prepare("UPDATE assets SET likes_count = likes_count + 1 WHERE id = :id");
$stmt->execute([':id' => $id]);

$getStmt = $pdo->prepare("SELECT likes_count FROM assets WHERE id = :id LIMIT 1");
$getStmt->execute([':id' => $id]);
$row = $getStmt->fetch();

echo json_encode([
    'success' => true,
    'likes'   => (int)($row['likes_count'] ?? 0)
]);
