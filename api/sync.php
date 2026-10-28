<?php
/**
 * API: Trigger Filesystem / FTP Sync
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/auth.php';
require_once __DIR__ . '/../includes/SyncManager.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Please login as superadmin.']);
    exit;
}

try {
    $sync = new SyncManager();
    $stats = $sync->sync();
    echo json_encode(['success' => true, 'stats' => $stats]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
