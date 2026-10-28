<?php
/**
 * Showcase Tube - YouTube-style Home Feed
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

// Filtering parameters
$filterType = $_GET['type'] ?? '';
$filterCat  = $_GET['category'] ?? '';
$searchQ    = trim($_GET['q'] ?? '');

// Build query
$where = ['is_public = 1'];
$params = [];

if ($filterType) {
    $where[] = 'asset_type = :type';
    $params[':type'] = $filterType;
}

if ($filterCat) {
    if ($filterCat === 'motion-comics') {
        $where[] = '(file_path LIKE :catpath OR category_id = (SELECT id FROM categories WHERE slug = "motion-comics" LIMIT 1))';
        $params[':catpath'] = '%motion-comic%';
    } else {
        $where[] = 'category_id = (SELECT id FROM categories WHERE slug = :cslug LIMIT 1)';
        $params[':cslug'] = $filterCat;
    }
}

if ($searchQ) {
    $where[] = '(title LIKE :sq OR file_name LIKE :sq OR description LIKE :sq)';
    $params[':sq'] = '%' . $searchQ . '%';
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE $whereSql 
        ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll();

$pageTitle = $searchQ ? "Search: {$searchQ}" : ($filterCat ? ucwords(str_replace('-', ' ', $filterCat)) : 'Home Feed');
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">

  <!-- Category Filter Chips -->
  <div class="yt-chip-bar">
    <a href="<?= $baseUrl ?>/index.php" class="yt-chip <?= (!$filterType && !$filterCat) ? 'active' : '' ?>">
      <i class="bi bi-compass"></i> All Assets
    </a>
    <a href="<?= $baseUrl ?>/index.php?type=video" class="yt-chip <?= ($filterType === 'video') ? 'active' : '' ?>">
      <i class="bi bi-play-circle-fill text-danger"></i> Videos
    </a>
    <a href="<?= $baseUrl ?>/index.php?category=motion-comics" class="yt-chip <?= ($filterCat === 'motion-comics') ? 'active' : '' ?>">
      <i class="bi bi-film text-warning"></i> AI Motion Comics
    </a>
    <a href="<?= $baseUrl ?>/images.php" class="yt-chip <?= ($filterType === 'image') ? 'active' : '' ?>">
      <i class="bi bi-images text-info"></i> Images & Panels
    </a>
    <a href="<?= $baseUrl ?>/documents.php" class="yt-chip <?= ($filterType === 'document') ? 'active' : '' ?>">
      <i class="bi bi-file-earmark-text text-primary"></i> Documents & PDFs
    </a>
    <a href="<?= $baseUrl ?>/index.php?type=archive" class="yt-chip <?= ($filterType === 'archive') ? 'active' : '' ?>">
      <i class="bi bi-archive text-success"></i> Archives & Zips
    </a>
  </div>

  <?php if ($searchQ): ?>
    <div class="d-flex align-items-center justify-content-between mb-3 pb-2 border-bottom border-secondary">
      <div>
        <h5 class="mb-0 text-white">Search results for <span class="text-info">"<?= h($searchQ) ?>"</span></h5>
        <small class="text-secondary"><?= count($assets) ?> items discovered</small>
      </div>
      <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline-secondary btn-sm">Clear Search</a>
    </div>
  <?php endif; ?>

  <!-- Assets Feed Grid -->
  <?php if (empty($assets)): ?>
    <div class="text-center py-5 my-5">
      <div class="display-1 text-secondary mb-3"><i class="bi bi-camera-reels"></i></div>
      <h3 class="text-white">No Assets Found</h3>
      <p class="text-secondary mb-4">No media assets match your active filter or search query.</p>
      <div class="d-flex justify-content-center gap-2">
        <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline-light">Reset Filters</a>
        <?php if (isAdminLoggedIn()): ?>
          <a href="<?= $baseUrl ?>/studio.php" class="btn btn-danger"><i class="bi bi-upload me-1"></i> Upload in Studio</a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-xl-4">
      <?php foreach ($assets as $asset): 
        $isVideo = ($asset['asset_type'] === 'video');
        $isImage = ($asset['asset_type'] === 'image');
        $isDoc   = ($asset['asset_type'] === 'document');
        $isArch  = ($asset['asset_type'] === 'archive');

        $watchUrl = $isVideo ? "{$baseUrl}/watch.php?v={$asset['id']}" : "{$baseUrl}/watch.php?v={$asset['id']}";
        if ($isDoc) $watchUrl = "{$baseUrl}/documents.php?v={$asset['id']}";
        if ($isImage) $watchUrl = "{$baseUrl}/images.php?v={$asset['id']}";

        $streamUrl = "{$baseUrl}/stream.php?v={$asset['id']}";
        $rawCdnUrl = "{$baseUrl}/" . ltrim($asset['file_path'], '/');

        // Thumbnail fallback
        $thumbUrl = '';
        if ($asset['thumbnail_path']) {
            $thumbUrl = "{$baseUrl}/" . ltrim($asset['thumbnail_path'], '/');
        } elseif ($isImage) {
            $thumbUrl = $rawCdnUrl;
        } else {
            // SVG Placeholder
            $icon = $isVideo ? 'play-btn-fill' : ($isDoc ? 'file-earmark-pdf-fill' : 'archive-fill');
            $bg = $isVideo ? '%237f1d1d' : ($isDoc ? '%231e3a8a' : '%2314532d');
            $thumbUrl = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'><rect width='640' height='360' fill='{$bg}'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23ffffff' font-size='48' font-family='sans-serif'>" . strtoupper($asset['extension']) . "</text></svg>";
        }

        // Generate embed snippet
        $embedTag = $isVideo 
          ? "<iframe src=\"{$baseUrl}/embed.php?v={$asset['id']}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>"
          : ($isImage ? "<img src=\"{$rawCdnUrl}\" alt=\"" . h($asset['title']) . "\" loading=\"lazy\" />" : "<a href=\"{$rawCdnUrl}\" download>Download " . h($asset['title']) . "</a>");
        
        $shareData = json_encode([
          'id'          => $asset['id'],
          'title'       => $asset['title'],
          'watchUrl'    => $watchUrl,
          'cdnUrl'      => $isVideo ? $streamUrl : $rawCdnUrl,
          'embedTag'    => $embedTag,
          'markdownTag' => "![" . h($asset['title']) . "]({$rawCdnUrl})"
        ]);
      ?>
        <div class="col">
          <div class="yt-card">
            <!-- Thumbnail Wrapper -->
            <a href="<?= $watchUrl ?>" class="yt-thumb-wrapper">
              <img src="<?= $thumbUrl ?>" alt="<?= h($asset['title']) ?>" loading="lazy">
              
              <?php if ($isVideo): ?>
                <!-- Hover silent preview video -->
                <video class="hover-preview" src="<?= $streamUrl ?>" muted preload="none"></video>
                <span class="yt-duration-badge">
                  <?= $asset['duration_seconds'] > 0 ? formatDuration((int)$asset['duration_seconds']) : 'HD' ?>
                </span>
              <?php else: ?>
                <span class="yt-duration-badge bg-secondary"><?= strtoupper($asset['extension']) ?></span>
              <?php endif; ?>

              <span class="yt-type-badge"><?= $asset['asset_type'] ?></span>
            </a>

            <!-- Card Body / Meta -->
            <div class="yt-card-body">
              <div class="yt-author-avatar">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
              </div>
              <div class="yt-card-info">
                <a href="<?= $watchUrl ?>" class="text-decoration-none">
                  <h6 class="yt-card-title" title="<?= h($asset['title']) ?>"><?= h($asset['title']) ?></h6>
                </a>
                <div class="yt-card-meta">
                  <div><?= h($asset['category_name'] ?: 'Showcase Vault') ?></div>
                  <div class="d-flex align-items-center justify-content-between mt-1">
                    <span><?= number_format((int)$asset['views_count']) ?> views • <?= timeAgo($asset['created_at']) ?></span>
                    
                    <!-- Quick Actions Menu -->
                    <div class="dropdown">
                      <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown" aria-expanded="false" style="font-size: 1rem;">
                        <i class="bi bi-three-dots-vertical"></i>
                      </button>
                      <ul class="dropdown-menu dropdown-menu-end shadow">
                        <li><a class="dropdown-item" href="<?= $watchUrl ?>"><i class="bi bi-play me-2"></i>Open Asset</a></li>
                        <li><a class="dropdown-item" href="#" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>'><i class="bi bi-share me-2"></i>Share & Embed</a></li>
                        <li><a class="dropdown-item" href="<?= $isVideo ? $streamUrl : $rawCdnUrl ?>" download><i class="bi bi-download me-2"></i>Direct Download</a></li>
                        <?php if (isAdminLoggedIn()): ?>
                          <li><hr class="dropdown-divider"></li>
                          <li><a class="dropdown-item text-warning" href="<?= $baseUrl ?>/studio.php?edit=<?= $asset['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit in Studio</a></li>
                        <?php endif; ?>
                      </ul>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
