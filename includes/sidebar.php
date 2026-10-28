<?php
/**
 * Shared Sidebar - 3 Clean Categories:
 * - Videos
 * - Images (Project-wise)
 * - Documents & Archives
 */
$pdo = getDB();

// Quick counts by the 3 simple categories
$statStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_files,
        COALESCE(SUM(file_size), 0) AS total_size,
        SUM(CASE WHEN asset_type = 'video' THEN 1 ELSE 0 END) AS count_videos,
        SUM(CASE WHEN asset_type = 'image' THEN 1 ELSE 0 END) AS count_images,
        SUM(CASE WHEN asset_type IN ('document', 'archive') THEN 1 ELSE 0 END) AS count_docs_zips
    FROM assets
    WHERE is_public = 1
");
$sidebarStats = $statStmt->fetch() ?: [
    'total_files' => 0, 'total_size' => 0, 'count_videos' => 0, 'count_images' => 0, 'count_docs_zips' => 0
];

$currentType = $_GET['type'] ?? '';
?>
<aside class="yt-sidebar" id="ytSidebar">
  <div class="d-flex flex-column h-100">
    <nav class="nav flex-column gap-1">
      <!-- VIDEOS -->
      <a class="nav-link <?= ($currentType === 'video' || (empty($currentType) && basename($_SERVER['PHP_SELF']) === 'index.php')) ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=video">
        <i class="bi bi-play-btn-fill text-danger"></i>
        <span class="nav-text">Videos</span>
        <span class="badge bg-secondary ms-auto small nav-text"><?= (int)$sidebarStats['count_videos'] ?></span>
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

    <!-- Sidebar Footer Stats -->
    <div class="sidebar-footer mt-auto pt-3 border-top border-secondary-subtle" style="font-size: 0.75rem; color: var(--yt-text-muted);">
      <div class="mb-1">
        <span class="badge bg-success-subtle text-success">● Live CDN</span>
      </div>
      <div><strong><?= (int)$sidebarStats['total_files'] ?></strong> Total Assets</div>
      <div><strong><?= formatBytes((float)$sidebarStats['total_size']) ?></strong> Stored</div>
      <div class="mt-2 text-muted" style="font-size: 0.7rem;">&copy; <?= date('Y') ?> Runit Showcase</div>
    </div>
  </div>
</aside>
