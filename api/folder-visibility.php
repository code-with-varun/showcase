<?php
/**
 * API: Folder-Level Visibility & Bulk Asset Visibility Management
 * Supports:
 *  - list: Get all folders with file counts, current visibility breakdown & folder rules
 *  - update_folder: Set visibility for an entire folder in bulk and persist folder rule
 *  - bulk_assets: Set visibility for selected asset IDs in bulk
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$pdo = getDB();
$isAdmin = isAdminLoggedIn();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

if (!$isAdmin) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized. Superadmin login required.']);
    exit;
}

// Ensure folder_visibility table exists
$pdo->exec("CREATE TABLE IF NOT EXISTS folder_visibility (
  id INT AUTO_INCREMENT PRIMARY KEY,
  folder_path VARCHAR(255) NOT NULL UNIQUE,
  visibility TINYINT(1) NOT NULL DEFAULT 1 COMMENT '0=private, 1=unlisted, 2=public',
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_fv_path (folder_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

switch ($action) {
    case 'list':
        // 1. Fetch saved folder rules
        $rulesStmt = $pdo->query("SELECT folder_path, visibility, updated_at FROM folder_visibility");
        $savedRules = [];
        while ($r = $rulesStmt->fetch()) {
            $savedRules[$r['folder_path']] = (int)$r['visibility'];
        }

        // 2. Fetch all asset paths and their visibility
        $stmt = $pdo->query("SELECT id, file_path, file_size, visibility, asset_type, project FROM assets");
        $folders = [];

        while ($asset = $stmt->fetch()) {
            $filePath = str_replace('\\', '/', $asset['file_path']);
            $dir = dirname($filePath);
            if ($dir === '.' || $dir === '') {
                $dir = 'assets';
            }

            if (!isset($folders[$dir])) {
                $folders[$dir] = [
                    'folder_path' => $dir,
                    'display_name' => preg_replace('#^assets/#', '', $dir),
                    'asset_type'   => $asset['asset_type'],
                    'project'      => $asset['project'] ?? '',
                    'total_files'  => 0,
                    'total_size'   => 0,
                    'count_private'  => 0,
                    'count_unlisted' => 0,
                    'count_public'   => 0,
                    'rule_visibility' => $savedRules[$dir] ?? null,
                ];
            }

            $folders[$dir]['total_files']++;
            $folders[$dir]['total_size'] += (float)$asset['file_size'];
            $v = (int)$asset['visibility'];
            if ($v === 0) $folders[$dir]['count_private']++;
            elseif ($v === 2) $folders[$dir]['count_public']++;
            else $folders[$dir]['count_unlisted']++;
        }

        // 3. Scan physical subdirectories under assets/ in case some have no files yet
        $assetsDir = ASSETS_DIR;
        if (is_dir($assetsDir)) {
            $it = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($assetsDir, RecursiveDirectoryIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );
            foreach ($it as $item) {
                if ($item->isDir()) {
                    $rel = 'assets/' . str_replace('\\', '/', substr($item->getPathname(), strlen($assetsDir) + 1));
                    if (str_contains($rel, 'thumbnails')) continue;
                    if (!isset($folders[$rel])) {
                        $folders[$rel] = [
                            'folder_path' => $rel,
                            'display_name' => preg_replace('#^assets/#', '', $rel),
                            'asset_type'   => 'mixed',
                            'project'      => '',
                            'total_files'  => 0,
                            'total_size'   => 0,
                            'count_private'  => 0,
                            'count_unlisted' => 0,
                            'count_public'   => 0,
                            'rule_visibility' => $savedRules[$rel] ?? null,
                        ];
                    }
                }
            }
        }

        // Sort by folder path alphabetically
        ksort($folders);

        // Compute summary status for each folder
        foreach ($folders as &$f) {
            $tot = $f['total_files'];
            if ($tot === 0) {
                $f['status'] = 'empty';
                $f['effective_vis'] = $f['rule_visibility'] ?? 1;
            } elseif ($f['count_public'] === $tot) {
                $f['status'] = 'all_public';
                $f['effective_vis'] = 2;
            } elseif ($f['count_private'] === $tot) {
                $f['status'] = 'all_private';
                $f['effective_vis'] = 0;
            } elseif ($f['count_unlisted'] === $tot) {
                $f['status'] = 'all_unlisted';
                $f['effective_vis'] = 1;
            } else {
                $f['status'] = 'mixed';
                $f['effective_vis'] = $f['rule_visibility'] ?? 'mixed';
            }
        }
        unset($f);

        echo json_encode([
            'success' => true,
            'folders' => array_values($folders)
        ]);
        break;

    case 'update_folder':
        $folderPath = trim($_POST['folder_path'] ?? '');
        $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
        $visibility = max(0, min(2, $visibility));
        $isPublic   = ($visibility >= 1) ? 1 : 0;

        if (empty($folderPath)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Folder path required']);
            exit;
        }

        // Normalize folder path: e.g. "assets/videos/Lavish-Dreamers"
        $folderPath = str_replace('\\', '/', $folderPath);
        $folderPath = trim($folderPath, '/');
        if (!str_starts_with($folderPath, 'assets')) {
            $folderPath = 'assets/' . $folderPath;
        }

        // 1. Save or update the persistent folder visibility rule
        $ruleStmt = $pdo->prepare("
            INSERT INTO folder_visibility (folder_path, visibility, updated_at)
            VALUES (:path, :vis, NOW())
            ON DUPLICATE KEY UPDATE visibility = VALUES(visibility), updated_at = NOW()
        ");
        $ruleStmt->execute([
            ':path' => $folderPath,
            ':vis'  => $visibility
        ]);

        // 2. Bulk update all current assets whose path starts with this folder
        $prefix = rtrim($folderPath, '/') . '/%';
        $exact  = rtrim($folderPath, '/');

        $upStmt = $pdo->prepare("
            UPDATE assets 
            SET visibility = :vis, is_public = :pub, updated_at = NOW() 
            WHERE file_path LIKE :prefix OR file_path = :exact
        ");
        $upStmt->execute([
            ':vis'    => $visibility,
            ':pub'    => $isPublic,
            ':prefix' => $prefix,
            ':exact'  => $exact
        ]);
        $affected = $upStmt->rowCount();

        $labelInfo = getVisibilityLabel($visibility);

        echo json_encode([
            'success'     => true,
            'affected'    => $affected,
            'folder_path' => $folderPath,
            'visibility'  => $visibility,
            'label'       => $labelInfo['label'],
            'message'     => "Folder '{$folderPath}' updated to {$labelInfo['label']} ({$affected} files affected)."
        ]);
        break;

    case 'bulk_assets':
        $ids = $_POST['asset_ids'] ?? [];
        if (is_string($ids)) {
            $ids = json_decode($ids, true) ?: explode(',', $ids);
        }
        $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
        $visibility = max(0, min(2, $visibility));
        $isPublic   = ($visibility >= 1) ? 1 : 0;

        if (empty($ids) || !is_array($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No asset IDs provided']);
            exit;
        }

        $cleanIds = array_filter(array_map('trim', $ids));
        if (empty($cleanIds)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'No valid asset IDs provided']);
            exit;
        }

        $placeholders = implode(',', array_fill(0, count($cleanIds), '?'));
        $params = array_merge([$visibility, $isPublic], $cleanIds);

        $upStmt = $pdo->prepare("
            UPDATE assets 
            SET visibility = ?, is_public = ?, updated_at = NOW() 
            WHERE id IN ($placeholders)
        ");
        $upStmt->execute($params);
        $affected = $upStmt->rowCount();

        $labelInfo = getVisibilityLabel($visibility);

        echo json_encode([
            'success'    => true,
            'affected'   => $affected,
            'visibility' => $visibility,
            'label'      => $labelInfo['label'],
            'message'    => "Updated {$affected} assets to {$labelInfo['label']}."
        ]);
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}

