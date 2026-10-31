<?php
/**
 * API: Playlists Management
 * Supports create, update, delete, add_item, remove_item, list
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$pdo = getDB();
$isAdmin = isAdminLoggedIn();
$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

function generatePlaylistId(): string {
    return 'pl_' . substr(bin2hex(random_bytes(6)), 0, 10);
}

function slugify(string $text): string {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    return strtolower($text ?: 'playlist');
}

switch ($action) {
    case 'list':
        // Guests only see public playlists; admin sees all
        $where = $isAdmin ? '1=1' : 'p.visibility = 2';
        $stmt = $pdo->prepare("
            SELECT p.*, COUNT(pi.id) AS video_count,
                   (SELECT a.thumbnail_path FROM playlist_items pi2 
                    JOIN assets a ON pi2.asset_id = a.id 
                    WHERE pi2.playlist_id = p.id 
                    ORDER BY pi2.position ASC LIMIT 1) AS first_thumbnail,
                   (SELECT pi3.asset_id FROM playlist_items pi3 
                    WHERE pi3.playlist_id = p.id 
                    ORDER BY pi3.position ASC LIMIT 1) AS first_asset_id
            FROM playlists p
            LEFT JOIN playlist_items pi ON p.id = pi.playlist_id
            WHERE $where
            GROUP BY p.id
            ORDER BY p.updated_at DESC
        ");
        $stmt->execute();
        $playlists = $stmt->fetchAll();
        echo json_encode(['success' => true, 'playlists' => $playlists]);
        break;

    case 'create':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
        $visibility = max(0, min(2, $visibility));

        if (empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Playlist title is required.']);
            exit;
        }

        $id = generatePlaylistId();
        $slug = slugify($title) . '-' . substr($id, 3, 4);

        // Handle playlist thumbnail (gallery pick or device file)
        $thumbPath = null;
        if (!empty($_POST['selected_gallery_thumbnail'])) {
            $gal = trim($_POST['selected_gallery_thumbnail']);
            if (str_starts_with($gal, 'assets/') && file_exists(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $gal))) {
                $thumbPath = $gal;
            }
        }
        if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $thumbName = 'thumb-pl-' . $id . '-' . time() . '.' . $thumbExt;
            $thumbDest = THUMBNAILS_DIR . DIRECTORY_SEPARATOR . $thumbName;
            if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0777, true);
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbDest)) {
                $thumbPath = 'assets/thumbnails/' . $thumbName;
            }
        }

        $stmt = $pdo->prepare("
            INSERT INTO playlists (id, title, slug, description, thumbnail_path, visibility, created_at, updated_at)
            VALUES (:id, :title, :slug, :desc, :thumb, :vis, NOW(), NOW())
        ");
        $stmt->execute([
            ':id'    => $id,
            ':title' => $title,
            ':slug'  => $slug,
            ':desc'  => $description ?: null,
            ':thumb' => $thumbPath,
            ':vis'   => $visibility,
        ]);

        echo json_encode([
            'success'  => true,
            'message'  => 'Playlist created successfully!',
            'playlist' => ['id' => $id, 'title' => $title, 'visibility' => $visibility]
        ]);
        break;

    case 'delete':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $id = $_POST['id'] ?? '';
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Playlist ID required']);
            exit;
        }

        $pdo->prepare("DELETE FROM playlist_items WHERE playlist_id = :id")->execute([':id' => $id]);
        $pdo->prepare("DELETE FROM playlists WHERE id = :id")->execute([':id' => $id]);

        echo json_encode(['success' => true, 'message' => 'Playlist deleted successfully.']);
        break;

    case 'add_item':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $playlistId = $_POST['playlist_id'] ?? '';
        $assetId    = $_POST['asset_id'] ?? '';

        if (empty($playlistId) || empty($assetId)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Playlist ID and Asset ID required']);
            exit;
        }

        // Check if already in playlist
        $check = $pdo->prepare("SELECT id FROM playlist_items WHERE playlist_id = :pid AND asset_id = :aid LIMIT 1");
        $check->execute([':pid' => $playlistId, ':aid' => $assetId]);
        if ($check->fetch()) {
            echo json_encode(['success' => true, 'message' => 'Video is already in this playlist.']);
            exit;
        }

        // Get max position
        $posStmt = $pdo->prepare("SELECT COALESCE(MAX(position), 0) + 1 FROM playlist_items WHERE playlist_id = :pid");
        $posStmt->execute([':pid' => $playlistId]);
        $nextPos = (int)$posStmt->fetchColumn();

        $ins = $pdo->prepare("
            INSERT INTO playlist_items (playlist_id, asset_id, position, created_at)
            VALUES (:pid, :aid, :pos, NOW())
        ");
        $ins->execute([':pid' => $playlistId, ':aid' => $assetId, ':pos' => $nextPos]);

        // Touch updated_at on playlist
        $pdo->prepare("UPDATE playlists SET updated_at = NOW() WHERE id = :pid")->execute([':pid' => $playlistId]);

        echo json_encode(['success' => true, 'message' => 'Added to playlist!']);
        break;

    case 'remove_item':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $playlistId = $_POST['playlist_id'] ?? '';
        $assetId    = $_POST['asset_id'] ?? '';

        $pdo->prepare("DELETE FROM playlist_items WHERE playlist_id = :pid AND asset_id = :aid")->execute([
            ':pid' => $playlistId,
            ':aid' => $assetId
        ]);

        $pdo->prepare("UPDATE playlists SET updated_at = NOW() WHERE id = :pid")->execute([':pid' => $playlistId]);

        echo json_encode(['success' => true, 'message' => 'Removed from playlist.']);
        break;

    case 'get':
        $id = $_GET['id'] ?? $_POST['id'] ?? '';
        if (empty($id)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Playlist ID required']);
            exit;
        }

        $stmt = $pdo->prepare("SELECT * FROM playlists WHERE id = :id OR slug = :slug LIMIT 1");
        $stmt->execute([':id' => $id, ':slug' => $id]);
        $playlist = $stmt->fetch();

        if (!$playlist) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Playlist not found']);
            exit;
        }

        if ((int)$playlist['visibility'] === 0 && !$isAdmin) {
            http_response_code(403);
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            exit;
        }

        $itemStmt = $pdo->prepare("
            SELECT a.id, a.title, a.duration_seconds, a.thumbnail_path, a.file_name, a.views_count, pi.position, pi.id as item_id 
            FROM playlist_items pi 
            JOIN assets a ON pi.asset_id = a.id 
            WHERE pi.playlist_id = :pid 
            ORDER BY pi.position ASC, pi.created_at ASC
        ");
        $itemStmt->execute([':pid' => $playlist['id']]);
        $items = $itemStmt->fetchAll();

        echo json_encode(['success' => true, 'playlist' => $playlist, 'items' => $items]);
        break;

    case 'update':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $id = $_POST['id'] ?? '';
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $visibility = isset($_POST['visibility']) ? (int)$_POST['visibility'] : 1;
        $visibility = max(0, min(2, $visibility));

        if (empty($id) || empty($title)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Playlist ID and Title are required']);
            exit;
        }

        // Fetch current playlist for existing thumbnail
        $currStmt = $pdo->prepare("SELECT thumbnail_path FROM playlists WHERE id = :id LIMIT 1");
        $currStmt->execute([':id' => $id]);
        $currentPl = $currStmt->fetch();
        $thumbPath = $currentPl['thumbnail_path'] ?? null;

        // Check gallery pick
        if (isset($_POST['selected_gallery_thumbnail'])) {
            $gal = trim($_POST['selected_gallery_thumbnail']);
            if ($gal !== '') {
                if (str_starts_with($gal, 'assets/') && file_exists(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $gal))) {
                    $thumbPath = $gal;
                }
            } elseif (isset($_POST['clear_thumbnail']) && $_POST['clear_thumbnail'] === '1') {
                $thumbPath = null;
            }
        }

        // Check device upload
        if (!empty($_FILES['thumbnail']) && $_FILES['thumbnail']['error'] === UPLOAD_ERR_OK) {
            $thumbExt = strtolower(pathinfo($_FILES['thumbnail']['name'], PATHINFO_EXTENSION));
            $thumbName = 'thumb-pl-' . $id . '-' . time() . '.' . $thumbExt;
            $thumbDest = THUMBNAILS_DIR . DIRECTORY_SEPARATOR . $thumbName;
            if (!is_dir(THUMBNAILS_DIR)) mkdir(THUMBNAILS_DIR, 0777, true);
            if (move_uploaded_file($_FILES['thumbnail']['tmp_name'], $thumbDest)) {
                $thumbPath = 'assets/thumbnails/' . $thumbName;
            }
        }

        $upStmt = $pdo->prepare("
            UPDATE playlists 
            SET title = :title, description = :desc, thumbnail_path = :thumb, visibility = :vis, updated_at = NOW() 
            WHERE id = :id
        ");
        $upStmt->execute([
            ':id'    => $id,
            ':title' => $title,
            ':desc'  => $description ?: null,
            ':thumb' => $thumbPath,
            ':vis'   => $visibility,
        ]);

        echo json_encode(['success' => true, 'message' => 'Playlist updated successfully!']);
        break;

    case 'reorder':
        if (!$isAdmin) {
            http_response_code(401);
            echo json_encode(['success' => false, 'error' => 'Unauthorized']);
            exit;
        }

        $playlistId = $_POST['playlist_id'] ?? '';
        $assetId    = $_POST['asset_id'] ?? '';
        $direction  = $_POST['direction'] ?? ''; // 'up' or 'down'

        if (empty($playlistId) || empty($assetId) || !in_array($direction, ['up', 'down'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid reorder parameters']);
            exit;
        }

        // Fetch all items in current order
        $stmt = $pdo->prepare("SELECT id, asset_id, position FROM playlist_items WHERE playlist_id = :pid ORDER BY position ASC, created_at ASC");
        $stmt->execute([':pid' => $playlistId]);
        $items = $stmt->fetchAll();

        $targetIdx = -1;
        foreach ($items as $idx => $it) {
            if ($it['asset_id'] === $assetId) {
                $targetIdx = $idx;
                break;
            }
        }

        if ($targetIdx === -1) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Item not found in playlist']);
            exit;
        }

        $swapIdx = ($direction === 'up') ? $targetIdx - 1 : $targetIdx + 1;
        if ($swapIdx >= 0 && $swapIdx < count($items)) {
            $currentId  = $items[$targetIdx]['id'];
            $swapId     = $items[$swapIdx]['id'];
            $posCurrent = $items[$swapIdx]['position'];
            $posSwap    = $items[$targetIdx]['position'];

            // Ensure distinct positions
            if ($posCurrent === $posSwap) {
                $posCurrent = $swapIdx + 1;
                $posSwap    = $targetIdx + 1;
            }

            $u1 = $pdo->prepare("UPDATE playlist_items SET position = :pos WHERE id = :id");
            $u1->execute([':pos' => $posCurrent, ':id' => $currentId]);
            $u1->execute([':pos' => $posSwap, ':id' => $swapId]);

            $pdo->prepare("UPDATE playlists SET updated_at = NOW() WHERE id = :pid")->execute([':pid' => $playlistId]);
            echo json_encode(['success' => true, 'message' => 'Playlist order updated!']);
        } else {
            echo json_encode(['success' => true, 'message' => 'Item is already at the limit']);
        }
        break;

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
