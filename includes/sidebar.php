<?php
/**
 * Shared Sidebar - 3 Clean Categories:
 * - Videos
 * - Images (Project-wise)
 * - Documents & Archives
 */
$pdo = getDB();

// Quick counts: only show publicly visible assets to guests; admin sees all
$isAdmin = (function_exists('isAdminLoggedIn') && isAdminLoggedIn());

if ($isAdmin) {
    $statStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN asset_type = 'video' THEN 1 ELSE 0 END) AS count_videos,
            SUM(CASE WHEN asset_type = 'image' THEN 1 ELSE 0 END) AS count_images,
            SUM(CASE WHEN asset_type IN ('document', 'archive') THEN 1 ELSE 0 END) AS count_docs_zips
        FROM assets
    ");
} else {
    $statStmt = $pdo->query("
        SELECT 
            SUM(CASE WHEN asset_type = 'video' THEN 1 ELSE 0 END) AS count_videos,
            SUM(CASE WHEN asset_type = 'image' THEN 1 ELSE 0 END) AS count_images,
            SUM(CASE WHEN asset_type IN ('document', 'archive') THEN 1 ELSE 0 END) AS count_docs_zips
        FROM assets
        WHERE visibility = 2
    ");
}
$sidebarStats = $statStmt->fetch() ?: [
    'count_videos' => 0, 'count_images' => 0, 'count_docs_zips' => 0
];

$plCountStmt = $pdo->query($isAdmin ? "SELECT COUNT(*) FROM playlists" : "SELECT COUNT(*) FROM playlists WHERE visibility = 2");
$countPlaylists = (int)$plCountStmt->fetchColumn();

$currentType = $_GET['type'] ?? '';
?>
<aside class="yt-sidebar" id="ytSidebar">
  <div class="d-flex flex-column h-100">
    <!-- Mobile drawer header with close button -->
    <div class="d-flex d-lg-none align-items-center justify-content-between pb-3 mb-2 border-bottom border-light-subtle px-2">
      <div class="d-flex align-items-center gap-2">
        <img src="<?= $baseUrl ?>/public/Runit%20logo%20pack/app-icon-red.png" alt="Runit" height="24" class="rounded">
        <span class="fw-bold text-dark small" style="letter-spacing: -0.3px;">RUNIT SHOWCASE</span>
      </div>
      <button class="btn btn-sm btn-light border-0 rounded-circle text-secondary p-1" id="sidebarCloseBtn" aria-label="Close sidebar" style="width: 32px; height: 32px;">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <nav class="nav flex-column gap-1">
      <!-- VIDEOS -->
      <a class="nav-link <?= ($currentType === 'video' || (empty($currentType) && basename($_SERVER['PHP_SELF']) === 'index.php')) ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=video">
        <i class="bi bi-play-btn-fill text-danger"></i>
        <span class="nav-text">Videos</span>
        <span class="badge bg-secondary ms-auto small nav-text"><?= (int)$sidebarStats['count_videos'] ?></span>
      </a>

      <!-- PLAYLISTS -->
      <a class="nav-link <?= ($currentType === 'playlist') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=playlist">
        <i class="bi bi-collection-play-fill text-danger"></i>
        <span class="nav-text">Playlists</span>
        <span class="badge bg-secondary ms-auto small nav-text"><?= (int)$countPlaylists ?></span>
      </a>

      <!-- IMAGES -->
      <a class="nav-link <?= ($currentType === 'image') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=image">
        <i class="bi bi-images text-info"></i>
        <span class="nav-text">Images</span>
        <span class="badge bg-secondary ms-auto small nav-text"><?= (int)$sidebarStats['count_images'] ?></span>
      </a>

      <!-- DOCUMENTS & ARCHIVES -->
      <a class="nav-link <?= ($currentType === 'document' || $currentType === 'docs_zips') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=document">
        <i class="bi bi-archive-fill text-warning"></i>
        <span class="nav-text">Documents & Archives</span>
        <span class="badge bg-secondary ms-auto small nav-text"><?= (int)$sidebarStats['count_docs_zips'] ?></span>
      </a>

    </nav>
  </div>
</aside>
