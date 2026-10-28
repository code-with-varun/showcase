<?php
/**
 * Shared YouTube-style Collapsible Sidebar
 */
$pdo = getDB();

// Quick stats for sidebar
$statStmt = $pdo->query("SELECT COUNT(*) AS total_files, COALESCE(SUM(file_size), 0) AS total_size FROM assets");
$sidebarStats = $statStmt->fetch() ?: ['total_files' => 0, 'total_size' => 0];

$currentType = $_GET['type'] ?? '';
$currentCat = $_GET['category'] ?? '';
?>
<aside class="yt-sidebar" id="ytSidebar">
  <div class="d-flex flex-column h-100">
    <nav class="nav flex-column gap-1">
      <a class="nav-link <?= ($activePage === 'index' && empty($currentType) && empty($currentCat)) ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php">
        <i class="bi bi-house-door-fill"></i>
        <span class="nav-text">Home</span>
      </a>

      <a class="nav-link <?= ($currentType === 'video' || $currentCat === 'videos') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=video">
        <i class="bi bi-play-btn-fill"></i>
        <span class="nav-text">Videos</span>
      </a>

      <a class="nav-link <?= ($currentCat === 'motion-comics') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?category=motion-comics">
        <i class="bi bi-film"></i>
        <span class="nav-text">Motion Comics</span>
      </a>

      <a class="nav-link <?= ($activePage === 'images' || $currentType === 'image') ? 'active' : '' ?>" href="<?= $baseUrl ?>/images.php">
        <i class="bi bi-images"></i>
        <span class="nav-text">Images & Panels</span>
      </a>

      <a class="nav-link <?= ($activePage === 'documents' || $currentType === 'document') ? 'active' : '' ?>" href="<?= $baseUrl ?>/documents.php">
        <i class="bi bi-file-earmark-text-fill"></i>
        <span class="nav-text">Documents</span>
      </a>

      <a class="nav-link <?= ($currentType === 'archive') ? 'active' : '' ?>" href="<?= $baseUrl ?>/index.php?type=archive">
        <i class="bi bi-archive-fill"></i>
        <span class="nav-text">Archives & Zips</span>
      </a>

      <hr class="my-2" style="border-color: var(--yt-border);">

      <div class="sidebar-heading">Repository & CDN</div>

      <a class="nav-link <?= ($activePage === 'studio') ? 'active' : '' ?>" href="<?= $baseUrl ?>/studio.php">
        <i class="bi bi-cpu-fill text-danger"></i>
        <span class="nav-text">Creator Studio</span>
      </a>

      <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#cdnHubModal">
        <i class="bi bi-share-fill text-info"></i>
        <span class="nav-text">CDN & Embed Hub</span>
      </a>

      <?php if (isAdminLoggedIn()): ?>
        <a class="nav-link" href="#" id="sidebarSyncBtn">
          <i class="bi bi-arrow-repeat text-success"></i>
          <span class="nav-text">Sync Filesystem</span>
        </a>
      <?php endif; ?>
    </nav>

    <!-- Sidebar Footer Stats -->
    <div class="sidebar-footer mt-auto pt-3 border-top border-secondary-subtle" style="font-size: 0.75rem; color: var(--yt-text-muted);">
      <div class="mb-1">
        <span class="badge bg-success-subtle text-success">● Live CDN</span>
      </div>
      <div><strong><?= (int)$sidebarStats['total_files'] ?></strong> Total Assets</div>
      <div><strong><?= formatBytes((float)$sidebarStats['total_size']) ?></strong> Stored</div>
      <div class="mt-2 text-muted" style="font-size: 0.7rem;">&copy; <?= date('Y') ?> Showcase Tube</div>
    </div>
  </div>
</aside>
