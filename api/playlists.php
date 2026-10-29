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

        $stmt = $pdo->prepare("
            INSERT INTO playlists (id, title, slug, description, visibility, created_at, updated_at)
            VALUES (:id, :title, :slug, :desc, :vis, NOW(), NOW())
        ");
        $stmt->execute([
            ':id'    => $id,
            ':title' => $title,
            ':slug'  => $slug,
            ':desc'  => $description ?: null,
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

    default:
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Unknown action']);
}
