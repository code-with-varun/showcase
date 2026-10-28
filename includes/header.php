<?php
/**
 * Shared Header Template (Light Theme)
 * Showcase Tube & Media CDN Vault
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/auth.php';

$currentAdmin = getCurrentAdmin();
$baseUrl = getBaseUrl();
$searchQuery = trim($_GET['q'] ?? '');
$activePage = basename($_SERVER['PHP_SELF'], '.php');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= isset($pageTitle) ? h($pageTitle) . ' - ' : '' ?>Runit Showcase</title>
  <meta name="description" content="High-performance media vault and streaming platform for videos, images, and documents.">
  
  <!-- Favicon badge -->
  <link rel="icon" type="image/png" href="<?= $baseUrl ?>/public/Runit%20logo%20pack/app-icon-red.png">

  <!-- Bootstrap 5.3 CSS & Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  
  <!-- Custom YouTube Theme -->
  <link rel="stylesheet" href="<?= $baseUrl ?>/css/youtube.css">
</head>
<body data-bs-theme="light">

  <!-- Top Navigation Header -->
  <header class="yt-header">
    <div class="d-flex align-items-center gap-2">
      <button class="btn btn-link text-dark p-1" id="sidebarToggleBtn" aria-label="Toggle Sidebar" style="font-size: 1.25rem;">
        <i class="bi bi-list"></i>
      </button>
      <a href="<?= $baseUrl ?>/index.php" class="yt-brand d-flex align-items-center gap-2 text-decoration-none">
        <img src="<?= $baseUrl ?>/public/Runit%20logo%20pack/app-icon-red.png" alt="Runit" height="32" class="rounded">
        <span class="brand-text text-dark fw-bold" style="letter-spacing: -0.5px; font-size: 1.15rem;">RUNIT <span style="color: #d6111a; font-weight: 800;">SHOWCASE</span></span>
      </a>
    </div>

    <!-- Center Search Bar -->
    <div class="yt-search-container mx-2 mx-sm-4 flex-grow-1 d-flex justify-content-center">
      <form action="<?= $baseUrl ?>/index.php" method="GET" class="yt-search-group w-100" style="max-width: 550px;">
        <input type="text" name="q" class="form-control yt-search-input" placeholder="Search videos, images, documents..." value="<?= h($searchQuery) ?>" aria-label="Search">
        <?php if (!empty($_GET['type'])): ?>
          <input type="hidden" name="type" value="<?= h($_GET['type']) ?>">
        <?php endif; ?>
        <button type="submit" class="btn yt-search-btn" title="Search">
          <i class="bi bi-search"></i>
        </button>
      </form>
    </div>

    <!-- Header Right Actions -->
    <div class="d-flex align-items-center gap-2">
      <?php if ($currentAdmin): ?>
        <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-danger btn-sm rounded-pill d-flex align-items-center gap-1 px-3">
          <i class="bi bi-plus-lg"></i>
          <span class="d-none d-sm-inline">Create & Upload</span>
        </a>
        <div class="dropdown">
          <button class="btn btn-light border btn-sm rounded-circle p-1 dropdown-toggle d-flex align-items-center justify-content-center shadow-sm" type="button" data-bs-toggle="dropdown" aria-expanded="false" style="width: 36px; height: 36px;">
            <i class="bi bi-person-fill text-danger"></i>
          </button>
          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light-subtle">
            <li class="dropdown-header text-uppercase" style="font-size: 0.75rem;">Logged as Superadmin</li>
            <li><a class="dropdown-item" href="<?= $baseUrl ?>/studio.php"><i class="bi bi-gear-fill me-2"></i>Creator Studio</a></li>
            <li><a class="dropdown-item" href="#" id="quickSyncBtn"><i class="bi bi-arrow-repeat me-2"></i>Sync Filesystem</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item text-danger" href="<?= $baseUrl ?>/logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sign Out</a></li>
          </ul>
        </div>
      <?php else: ?>
        <a href="<?= $baseUrl ?>/login.php" class="btn btn-outline-danger btn-sm rounded-pill px-3 d-flex align-items-center gap-1">
          <i class="bi bi-person-circle"></i>
          <span>Sign In</span>
        </a>
      <?php endif; ?>
    </div>
  </header>

  <!-- Left Sidebar Drawer -->
  <?php include __DIR__ . '/sidebar.php'; ?>

  <!-- Main Content Container -->
  <main class="yt-main-content">
