<?php
/**
 * Dynamic XML Sitemap Generator
 * Supports Standard URLs, Google Video Sitemap schema, and Google Image Sitemap schema.
 * Can be accessed directly at /sitemap.xml or called by cron / superadmin.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

// Set proper XML Header
header('Content-Type: application/xml; charset=utf-8');

// Fetch all public assets (visibility = 2)
$assetStmt = $pdo->prepare("
    SELECT id, title, slug, description, asset_type, extension, file_path, file_size, 
           thumbnail_path, duration_seconds, views_count, created_at, updated_at 
    FROM assets 
    WHERE visibility = 2 
    ORDER BY created_at DESC
");
$assetStmt->execute();
$publicAssets = $assetStmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch all public playlists (visibility = 2)
$plStmt = $pdo->prepare("
    SELECT id, title, slug, description, thumbnail_path, updated_at 
    FROM playlists 
    WHERE visibility = 2 
    ORDER BY updated_at DESC
");
$plStmt->execute();
$publicPlaylists = $plStmt->fetchAll(PDO::FETCH_ASSOC);

// Output XML
echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:video="http://www.google.com/schemas/sitemap-video/1.1"
        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">

  <!-- Core Feeds -->
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/') ?></loc>
    <changefreq>daily</changefreq>
    <priority>1.0</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/videos') ?></loc>
    <changefreq>daily</changefreq>
    <priority>0.9</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/playlists') ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/images') ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/documents') ?></loc>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
  </url>

  <!-- Public Playlists -->
  <?php foreach ($publicPlaylists as $pl): 
    $plUrl = $baseUrl . '/playlist/' . ($pl['slug'] ?: $pl['id']);
    $plDate = date('c', strtotime($pl['updated_at'] ?: date('Y-m-d H:i:s')));
  ?>
  <url>
    <loc><?= htmlspecialchars($plUrl) ?></loc>
    <lastmod><?= $plDate ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
  </url>
  <?php endforeach; ?>

  <!-- Public Videos with Google Video Schema -->
  <?php foreach ($publicAssets as $asset): 
    $lastMod = date('c', strtotime($asset['updated_at'] ?: $asset['created_at']));
    $targetSlug = $asset['slug'] ?: $asset['id'];
    $isVideo = ($asset['asset_type'] === 'video');
    $isImage = ($asset['asset_type'] === 'image');

    if ($isVideo):
      $watchUrl = $baseUrl . '/watch/' . $targetSlug;
      $streamUrl = $baseUrl . '/stream/' . $targetSlug;
      $thumbUrl = $asset['thumbnail_path'] 
          ? ($baseUrl . '/' . ltrim($asset['thumbnail_path'], '/'))
          : ($baseUrl . '/assets/thumbnails/thumb-flowcv.png');
      $desc = !empty($asset['description']) ? $asset['description'] : ($asset['title'] . ' on Runit Showcase');
      $dur = (int)$asset['duration_seconds'];
  ?>
  <url>
    <loc><?= htmlspecialchars($watchUrl) ?></loc>
    <lastmod><?= $lastMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.9</priority>
    <video:video>
      <video:thumbnail_loc><?= htmlspecialchars($thumbUrl) ?></video:thumbnail_loc>
      <video:title><?= htmlspecialchars($asset['title']) ?></video:title>
      <video:description><?= htmlspecialchars(mb_substr($desc, 0, 1000)) ?></video:description>
      <video:content_loc><?= htmlspecialchars($streamUrl) ?></video:content_loc>
      <video:player_loc allow_embed="yes" autoplay="ap=1"><?= htmlspecialchars($baseUrl . '/embed/' . $targetSlug) ?></video:player_loc>
      <?php if ($dur > 0): ?>
        <video:duration><?= $dur ?></video:duration>
      <?php endif; ?>
      <video:view_count><?= (int)$asset['views_count'] ?></video:view_count>
      <video:publication_date><?= date('c', strtotime($asset['created_at'])) ?></video:publication_date>
      <video:family_friendly>yes</video:family_friendly>
      <video:live>no</video:live>
    </video:video>
  </url>
  <?php elseif ($isImage): 
    $imgUrl = $baseUrl . '/' . ltrim($asset['file_path'], '/');
  ?>
  <url>
    <loc><?= htmlspecialchars($baseUrl . '/images') ?></loc>
    <lastmod><?= $lastMod ?></lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.7</priority>
    <image:image>
      <image:loc><?= htmlspecialchars($imgUrl) ?></image:loc>
      <image:title><?= htmlspecialchars($asset['title']) ?></image:title>
    </image:image>
  </url>
  <?php endif; ?>
  <?php endforeach; ?>

</urlset>
