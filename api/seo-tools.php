<?php
/**
 * API: SEO & Google Search Console Tools
 * Re-generates static sitemap.xml, updates robots.txt, and pings search engines
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

if (!isAdminLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'status';
$pdo = getDB();
$baseUrl = getBaseUrl();
$root = dirname(__DIR__);

switch ($action) {
    case 'status':
        $sitemapPath = $root . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $robotsPath  = $root . DIRECTORY_SEPARATOR . 'robots.txt';

        // Count public videos, images, playlists
        $vCount = $pdo->query("SELECT COUNT(*) FROM assets WHERE visibility = 2 AND asset_type = 'video'")->fetchColumn();
        $iCount = $pdo->query("SELECT COUNT(*) FROM assets WHERE visibility = 2 AND asset_type = 'image'")->fetchColumn();
        $pCount = $pdo->query("SELECT COUNT(*) FROM playlists WHERE visibility = 2")->fetchColumn();
        $totCount = $pdo->query("SELECT COUNT(*) FROM assets WHERE visibility = 2")->fetchColumn();

        $sitemapExists = file_exists($sitemapPath);
        $sitemapTime = $sitemapExists ? date('Y-m-d H:i:s', filemtime($sitemapPath)) : 'Dynamic (sitemap.php)';
        $sitemapSize = $sitemapExists ? filesize($sitemapPath) : 0;

        echo json_encode([
            'success' => true,
            'stats' => [
                'public_videos'    => (int)$vCount,
                'public_images'    => (int)$iCount,
                'public_playlists' => (int)$pCount,
                'total_public'     => (int)$totCount,
                'sitemap_url'      => $baseUrl . '/sitemap.xml',
                'robots_url'       => $baseUrl . '/robots.txt',
                'sitemap_updated'  => $sitemapTime,
                'sitemap_size'     => $sitemapSize > 0 ? round($sitemapSize / 1024, 2) . ' KB' : 'Dynamic Stream',
                'robots_exists'    => file_exists($robotsPath)
            ]
        ]);
        break;

    case 'build_sitemap':
        // Generate XML via sitemap.php buffer
        ob_start();
        include $root . DIRECTORY_SEPARATOR . 'sitemap.php';
        $xmlContent = ob_get_clean();

        $destFile = $root . DIRECTORY_SEPARATOR . 'sitemap.xml';
        $written = file_put_contents($destFile, $xmlContent);

        if ($written !== false) {
            echo json_encode([
                'success' => true,
                'message' => 'Sitemap generated successfully!',
                'url'     => $baseUrl . '/sitemap.xml',
                'bytes'   => $written,
                'updated' => date('Y-m-d H:i:s')
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'error'   => 'Could not write static sitemap.xml. Check folder permissions.'
            ]);
        }
        break;

    case 'save_robots':
        $content = $_POST['content'] ?? '';
        if (empty($content)) {
            echo json_encode(['success' => false, 'error' => 'robots.txt content cannot be empty']);
            exit;
        }

        $destFile = $root . DIRECTORY_SEPARATOR . 'robots.txt';
        $written = file_put_contents($destFile, $content);

        if ($written !== false) {
            echo json_encode(['success' => true, 'message' => 'robots.txt updated successfully!']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Could not write robots.txt file.']);
        }
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}

