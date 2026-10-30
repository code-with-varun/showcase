<?php
/**
 * Creator Studio & Asset Management Center (Light Theme)
 * Runit Showcase & Media CDN Vault
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

// Enforce admin login
requireAdmin();

$pdo = getDB();
$baseUrl = getBaseUrl();

// Stats
$statsStmt = $pdo->query("
    SELECT 
        COUNT(*) AS total_assets,
        COALESCE(SUM(file_size), 0) AS total_size,
        COALESCE(SUM(views_count), 0) AS total_views,
        SUM(CASE WHEN asset_type = 'video' THEN 1 ELSE 0 END) AS total_videos,
        SUM(CASE WHEN asset_type = 'image' THEN 1 ELSE 0 END) AS total_images,
        SUM(CASE WHEN asset_type IN ('document', 'archive') THEN 1 ELSE 0 END) AS total_docs_zips
    FROM assets
");
$studioStats = $statsStmt->fetch();

// Categories & Projects
$catStmt = $pdo->query("SELECT * FROM categories ORDER BY name ASC");
$categories = $catStmt->fetchAll();

$projStmt = $pdo->query("SELECT DISTINCT project FROM assets WHERE project IS NOT NULL AND project != '' ORDER BY project ASC");
$allProjects = $projStmt->fetchAll(PDO::FETCH_COLUMN) ?: [];

// Fetch assets with filters
$typeFilter = $_GET['type'] ?? '';
$sourceFilter = $_GET['source'] ?? '';
$projectFilter = trim($_GET['project'] ?? '');
$folderFilter = trim($_GET['folder'] ?? '');
$searchFilter = trim($_GET['q'] ?? '');

$where = ['1=1'];
$params = [];

if ($typeFilter) {
    if ($typeFilter === 'docs_zips') {
        $where[] = "a.asset_type IN ('document', 'archive')";
    } else {
        $where[] = 'a.asset_type = :type';
        $params[':type'] = $typeFilter;
    }
}
if ($sourceFilter) {
    $where[] = 'a.source = :source';
    $params[':source'] = $sourceFilter;
}
if ($projectFilter) {
    $where[] = 'a.project = :project';
    $params[':project'] = $projectFilter;
}
if ($folderFilter) {
    $cleanFolder = rtrim(str_replace('\\', '/', $folderFilter), '/');
    $where[] = '(a.file_path LIKE :folder_prefix OR a.file_path = :folder_exact)';
    $params[':folder_prefix'] = $cleanFolder . '/%';
    $params[':folder_exact'] = $cleanFolder;
}
if ($searchFilter) {
    $where[] = '(a.title LIKE :sq1 OR a.file_name LIKE :sq2 OR a.id LIKE :sq3)';
    $params[':sq1'] = '%' . $searchFilter . '%';
    $params[':sq2'] = '%' . $searchFilter . '%';
    $params[':sq3'] = '%' . $searchFilter . '%';
}

$whereSql = implode(' AND ', $where);
$assetsQuery = $pdo->prepare("
    SELECT a.*, c.name AS category_name 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    WHERE $whereSql 
    ORDER BY a.created_at DESC
");
$assetsQuery->execute($params);
$assetsList = $assetsQuery->fetchAll();

// Fetch all distinct folders from assets table & saved folder visibility rules
$allFoldersStmt = $pdo->query("
    SELECT a.*, c.name AS category_name 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    ORDER BY a.file_path ASC, a.created_at DESC
");
$allFolders = [];
$folderAssets = [];
while ($aRow = $allFoldersStmt->fetch()) {
    $fPath = str_replace('\\', '/', $aRow['file_path']);
    $dir = dirname($fPath);
    if ($dir === '.' || $dir === '') $dir = 'assets';
    if (!isset($allFolders[$dir])) {
        $allFolders[$dir] = [
            'folder_path'   => $dir,
            'display_name'  => preg_replace('#^assets/#', '', $dir),
            'asset_type'    => $aRow['asset_type'],
            'project'       => $aRow['project'] ?? '',
            'total_files'   => 0,
            'total_size'    => 0,
            'count_private' => 0,
            'count_unlisted'=> 0,
            'count_public'  => 0,
            'rule_vis'      => null,
        ];
    }
    $allFolders[$dir]['total_files']++;
    $allFolders[$dir]['total_size'] += (float)$aRow['file_size'];
    $v = (int)$aRow['visibility'];
    if ($v === 0) $allFolders[$dir]['count_private']++;
    elseif ($v === 2) $allFolders[$dir]['count_public']++;
    else $allFolders[$dir]['count_unlisted']++;

    $folderAssets[$dir][] = $aRow;
}

try {
    $rStmt = $pdo->query("SELECT folder_path, visibility FROM folder_visibility");
    while ($r = $rStmt->fetch()) {
        $normPath = str_replace('\\', '/', $r['folder_path']);
        if (isset($allFolders[$normPath])) {
            $allFolders[$normPath]['rule_vis'] = (int)$r['visibility'];
        }
    }
} catch (Exception $e) {}

ksort($allFolders);

// Fetch all playlists and their items
$playlistsStmt = $pdo->query("
    SELECT p.*, COUNT(pi.id) AS video_count,
           (SELECT a.thumbnail_path FROM playlist_items pi2 
            JOIN assets a ON pi2.asset_id = a.id 
            WHERE pi2.playlist_id = p.id 
            ORDER BY pi2.position ASC, pi2.created_at ASC LIMIT 1) AS first_thumbnail,
           (SELECT pi3.asset_id FROM playlist_items pi3 
            WHERE pi3.playlist_id = p.id 
            ORDER BY pi3.position ASC, pi3.created_at ASC LIMIT 1) AS first_asset_id,
           (SELECT a4.slug FROM playlist_items pi4 
            JOIN assets a4 ON pi4.asset_id = a4.id 
            WHERE pi4.playlist_id = p.id 
            ORDER BY pi4.position ASC, pi4.created_at ASC LIMIT 1) AS first_asset_slug
    FROM playlists p
    LEFT JOIN playlist_items pi ON p.id = pi.playlist_id
    GROUP BY p.id
    ORDER BY p.updated_at DESC
");
$allPlaylists = $playlistsStmt ? $playlistsStmt->fetchAll() : [];

$playlistDetails = [];
if (!empty($allPlaylists)) {
    $itemQuery = $pdo->query("
        SELECT pi.*, a.title AS video_title, a.duration_seconds, a.thumbnail_path, a.slug AS video_slug 
        FROM playlist_items pi 
        JOIN assets a ON pi.asset_id = a.id 
        ORDER BY pi.playlist_id, pi.position ASC, pi.created_at ASC
    ");
    while ($it = $itemQuery->fetch()) {
        $playlistDetails[$it['playlist_id']][] = $it;
    }
}

$pageTitle = "Creator Studio";
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">

  <!-- Header Banner -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-light-subtle">
    <div>
      <h1 class="h3 text-dark mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-cpu-fill text-danger"></i>
        <span>Creator Studio & Asset Manager</span>
      </h1>
      <p class="text-secondary small mb-0">Dual upload engine: direct browser upload or FTP file drop with one-click filesystem sync.</p>
    </div>

    <!-- Studio Header Actions -->
    <div class="d-flex align-items-center gap-2 flex-wrap">
      <a href="<?= $baseUrl ?>/settings" class="btn btn-outline-secondary d-flex align-items-center gap-2 px-3 py-2 fw-semibold shadow-sm" title="Security & API settings">
        <i class="bi bi-gear-fill"></i>
        <span class="d-none d-sm-inline">Settings</span>
      </a>
      <a href="<?= $baseUrl ?>/settings#danger-zone" class="btn btn-outline-danger d-flex align-items-center gap-2 px-3 py-2 fw-semibold shadow-sm" title="Global Reset: purge database & empty assets">
        <i class="bi bi-trash3"></i>
        <span class="d-none d-sm-inline">Global Reset</span>
      </a>
    </div>
  </div>

  <!-- Key Metrics Row -->
  <div class="row row-cols-2 row-cols-md-4 g-3 mb-4">
    <div class="col">
      <div class="card bg-white border-light-subtle shadow-sm p-3">
        <div class="text-secondary small text-uppercase fw-bold">Total Assets</div>
        <div class="h3 text-dark mb-0 mt-1"><?= number_format((int)$studioStats['total_assets']) ?></div>
        <div class="small text-secondary mt-1">
          <span class="text-danger fw-bold"><?= (int)$studioStats['total_videos'] ?> vids</span> • 
          <span class="text-info fw-bold"><?= (int)$studioStats['total_images'] ?> imgs</span> • 
          <span class="text-warning fw-bold"><?= (int)$studioStats['total_docs_zips'] ?> docs/zips</span>
        </div>
      </div>
    </div>

    <div class="col">
      <div class="card bg-white border-light-subtle shadow-sm p-3">
        <div class="text-secondary small text-uppercase fw-bold">Vault Storage</div>
        <div class="h3 text-dark mb-0 mt-1"><?= formatBytes((float)$studioStats['total_size']) ?></div>
        <div class="small text-secondary mt-1">Live Disk Usage</div>
      </div>
    </div>

    <div class="col">
      <div class="card bg-white border-light-subtle shadow-sm p-3">
        <div class="text-secondary small text-uppercase fw-bold">Total Views</div>
        <div class="h3 text-dark mb-0 mt-1"><?= number_format((int)$studioStats['total_views']) ?></div>
        <div class="small text-secondary mt-1">Streaming & Embed Impressions</div>
      </div>
    </div>

    <div class="col">
      <div class="card bg-white border-light-subtle shadow-sm p-3">
        <div class="text-secondary small text-uppercase fw-bold">CDN Status</div>
        <div class="h3 text-success mb-0 mt-1 d-flex align-items-center gap-1">
          <span style="font-size: 1rem;">●</span> Active
        </div>
        <div class="small text-secondary mt-1">HTTP 206 Byte-Range Ready</div>
      </div>
    </div>
  </div>

  <!-- Dual Upload & Sync Section -->
  <div class="card bg-white border-light-subtle shadow-sm mb-4">
    <div class="card-header border-bottom border-light-subtle bg-light">
      <ul class="nav nav-tabs card-header-tabs border-0 flex-nowrap overflow-x-auto" id="uploadTabs" role="tablist" style="scrollbar-width: none;">
        <li class="nav-item flex-shrink-0" role="presentation">
          <button class="nav-link active text-dark fw-semibold border-bottom-0" id="web-upload-tab" data-bs-toggle="tab" data-bs-target="#web-upload" type="button" role="tab">
            <i class="bi bi-cloud-arrow-up-fill text-danger me-1"></i> Web UI Uploader
          </button>
        </li>
        <li class="nav-item flex-shrink-0" role="presentation">
          <button class="nav-link text-secondary fw-semibold border-0" id="ftp-sync-tab" data-bs-toggle="tab" data-bs-target="#ftp-sync" type="button" role="tab">
            <i class="bi bi-folder-symlink-fill text-success me-1"></i> Large File Drop & FTP Sync
          </button>
        </li>
        <li class="nav-item flex-shrink-0" role="presentation">
          <button class="nav-link text-secondary fw-semibold border-0" id="playlists-tab" data-bs-toggle="tab" data-bs-target="#playlists-panel" type="button" role="tab">
            <i class="bi bi-collection-play-fill text-danger me-1"></i> Playlists Manager
            <span class="badge bg-danger-subtle text-danger border border-danger-subtle ms-1" id="playlistTabBadge"><?= count($allPlaylists) ?></span>
          </button>
        </li>
        <li class="nav-item flex-shrink-0" role="presentation">
          <button class="nav-link text-secondary fw-semibold border-0" id="folders-tab" data-bs-toggle="tab" data-bs-target="#folders-panel" type="button" role="tab">
            <i class="bi bi-folder-fill text-warning me-1"></i> Folder Visibility
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle ms-1" id="foldersTabBadge"><?= count($allFolders) ?></span>
          </button>
        </li>
      </ul>
    </div>

    <div class="card-body p-4">
      <div class="tab-content" id="uploadTabsContent">

        <!-- TAB 1: WEB UI UPLOADER -->
        <div class="tab-pane fade show active" id="web-upload" role="tabpanel">
          <form id="webUploadForm" enctype="multipart/form-data">
            <div class="row g-3">
              <!-- Drag and drop zone -->
              <div class="col-12">
                <div class="p-4 border border-secondary-subtle border-dashed rounded-3 text-center bg-light" id="dropZone" style="cursor: pointer; border-style: dashed !important;">
                  <i class="bi bi-cloud-arrow-up display-4 text-danger mb-2 d-block"></i>
                  <h5 class="text-dark mb-1">Choose or drag & drop files to upload</h5>
                  <p class="text-secondary small mb-2">Supports MP4, WebM, PNG, JPG, WebP, PDF, DOCX, ZIP files</p>
                  <input type="file" id="fileInput" name="file" class="d-none" required>
                  <button type="button" class="btn btn-outline-danger btn-sm" onclick="document.getElementById('fileInput').click()">
                    Browse Files
                  </button>
                  <div id="selectedFileInfo" class="mt-2 text-danger fw-bold small"></div>
                </div>
              </div>

              <!-- Metadata fields -->
              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">Title (optional, auto-derived if blank)</label>
                <input type="text" name="title" id="uploadTitle" class="form-control bg-light text-dark border-secondary-subtle" placeholder="e.g. Episode 1: The Beginning">
              </div>

              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">Category</label>
                <select name="category_id" id="uploadCategory" class="form-select bg-light text-dark border-secondary-subtle">
                  <option value="">Auto-Detect from File Type</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>

              <div class="col-12">
                <label class="form-label text-secondary small fw-bold">Description & Timestamps (Optional)</label>
                <textarea name="description" id="uploadDesc" class="form-control bg-light text-dark border-secondary-subtle" rows="2" placeholder="Asset description or chapters like 00:00 Intro, 01:30 Scene 1"></textarea>
              </div>

              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">
                  <i class="bi bi-c-circle me-1"></i>Copyright & License Info (Optional)
                </label>
                <input type="text" name="copyright_info" class="form-control bg-light text-dark border-secondary-subtle" placeholder="e.g. © 2026 Runit. All rights reserved.">
              </div>

              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">Visibility</label>
                <select name="visibility" class="form-select bg-light text-dark border-secondary-subtle">
                  <option value="1" selected>🔗 Unlisted — anyone with link (Default)</option>
                  <option value="2">🌍 Public — visible in feed</option>
                  <option value="0">🔒 Private — admin only</option>
                </select>
              </div>

              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">Project Group (Optional)</label>
                <input type="text" name="project" list="projectsDatalist" class="form-control bg-light text-dark border-secondary-subtle" placeholder="e.g. ScripVault, Motion Comics">
                <datalist id="projectsDatalist">
                  <?php foreach ($allProjects as $p): ?>
                    <option value="<?= h($p) ?>">
                  <?php endforeach; ?>
                </datalist>
              </div>

              <div class="col-md-6">
                <label class="form-label text-secondary small fw-bold">Custom Thumbnail (Optional for videos/docs)</label>
                <input type="file" name="thumbnail" accept="image/*" class="form-control bg-light text-dark border-secondary-subtle">
              </div>

              <div class="col-12">
                <label class="form-label text-secondary small fw-bold">Tags (Comma-separated)</label>
                <input type="text" name="tags" class="form-control bg-light text-dark border-secondary-subtle" placeholder="motion-comic, 4k, portfolio">
              </div>

              <!-- Progress bar -->
              <div class="col-12 d-none" id="uploadProgressContainer">
                <div class="progress bg-light border" style="height: 12px;">
                  <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" id="uploadProgressBar" style="width: 0%;"></div>
                </div>
                <div class="small text-secondary mt-1 text-end" id="uploadProgressText">0%</div>
              </div>

              <div class="col-12 text-end">
                <button type="submit" class="btn btn-danger px-4" id="uploadSubmitBtn">
                  <i class="bi bi-upload me-1"></i> Start Upload
                </button>
              </div>
            </div>
          </form>
        </div>

        <!-- TAB 2: LARGE FILE DROP & FTP SYNC -->
        <div class="tab-pane fade" id="ftp-sync" role="tabpanel">
          <div class="row align-items-center g-4">
            <div class="col-lg-7">
              <h5 class="text-dark mb-2"><i class="bi bi-hdd-network text-success me-2"></i>Direct File Drop & FTP Workflow</h5>
              <p class="text-secondary small mb-3">
                For massive videos (100MB - 10GB+) or multi-gigabyte zip archives, upload them directly via FTP or paste them directly into the local folders on the server:
              </p>
              <ul class="list-group list-group-flush bg-transparent small mb-3">
                <li class="list-group-item bg-transparent text-secondary border-light-subtle">
                  <strong class="text-dark">Videos:</strong> <code>assets/videos/</code>
                </li>
                <li class="list-group-item bg-transparent text-secondary border-light-subtle">
                  <strong class="text-dark">Images:</strong> <code>assets/images/</code> or <code>assets/motion-comics/</code>
                </li>
                <li class="list-group-item bg-transparent text-secondary border-light-subtle">
                  <strong class="text-dark">Documents & Archives:</strong> <code>assets/documents/</code> or <code>assets/archives/</code>
                </li>
              </ul>
              <p class="text-secondary small mb-0">
                After placing files, click <strong>"Scan & Sync Filesystem"</strong> below. The system will index every new asset, assign unique IDs, register them in MySQL, and make them streamable immediately!
              </p>
            </div>

            <div class="col-lg-5 text-center p-4 border border-light-subtle rounded-3 bg-light">
              <div class="display-4 text-success mb-3"><i class="bi bi-arrow-repeat"></i></div>
              <h5 class="text-dark mb-2">Ready to Synchronize?</h5>
              <p class="text-secondary small mb-3">Runs the file scanner to detect all newly added, modified, or removed files.</p>
              <button class="btn btn-success btn-lg w-100 fw-bold shadow-sm" id="ftpSyncActionBtn">
                <i class="bi bi-play-circle me-1"></i> Scan & Sync Filesystem Now
              </button>
              <div id="syncReportBox" class="mt-3 text-start small font-monospace d-none p-2 bg-white rounded border border-light-subtle text-dark" style="max-height: 140px; overflow-y: auto;"></div>
            </div>
          </div>
        </div>

        <!-- TAB 3: PLAYLISTS MANAGER -->
        <div class="tab-pane fade" id="playlists-panel" role="tabpanel">
          <div class="row g-4">
            <!-- Left: Create New Playlist -->
            <div class="col-lg-4">
              <div class="p-3 border border-light-subtle rounded-3 bg-light">
                <h6 class="fw-bold text-dark mb-2 d-flex align-items-center gap-2">
                  <i class="bi bi-plus-circle-fill text-danger"></i>
                  <span>Create New Playlist</span>
                </h6>
                <p class="text-secondary small mb-3">Group your videos into series or continuous watch queues.</p>

                <form id="createPlaylistForm">
                  <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Playlist Title *</label>
                    <input type="text" name="title" id="plTitleInput" class="form-control form-control-sm bg-white" placeholder="e.g. Motion Comic Series, Remix Vault" required>
                  </div>

                  <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Description (Optional)</label>
                    <textarea name="description" id="plDescInput" class="form-control form-control-sm bg-white" rows="2" placeholder="Brief description of this playlist"></textarea>
                  </div>

                  <div class="mb-3">
                    <label class="form-label small fw-bold text-dark">Visibility</label>
                    <select name="visibility" id="plVisInput" class="form-select form-select-sm bg-white">
                      <option value="1" selected>🔗 Unlisted — anyone with link (Default)</option>
                      <option value="2">🌍 Public — visible in showcase feed</option>
                      <option value="0">🔒 Private — admin only</option>
                    </select>
                  </div>

                  <button type="submit" class="btn btn-danger btn-sm w-100 fw-semibold" id="createPlaylistBtn">
                    <i class="bi bi-plus-lg me-1"></i> Create Playlist
                  </button>
                </form>
              </div>
            </div>

            <!-- Right: Existing Playlists List -->
            <div class="col-lg-8">
              <div class="d-flex align-items-center justify-content-between mb-3">
                <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
                  <i class="bi bi-collection-play text-danger"></i>
                  <span>Active Playlists (<?= count($allPlaylists) ?>)</span>
                </h6>
              </div>

              <?php if (empty($allPlaylists)): ?>
                <div class="p-4 rounded-3 border border-light-subtle bg-light text-center text-secondary">
                  <i class="bi bi-collection-play display-6 d-block mb-2 text-muted"></i>
                  <p class="small mb-0">No playlists created yet. Use the form on the left to create your first playlist.</p>
                </div>
              <?php else: ?>
                <div class="d-flex flex-column gap-3" id="playlistsContainer">
                  <?php foreach ($allPlaylists as $pl): 
                    $plItems = $playlistDetails[$pl['id']] ?? [];
                    $vCount = count($plItems);
                    $firstAid = $pl['first_asset_id'];
                    $firstSlug = $pl['first_asset_slug'] ?? ($plItems[0]['video_slug'] ?? $firstAid);
                    $plSlug = $pl['slug'] ?: $pl['id'];
                    $playUrl = $firstAid ? "{$baseUrl}/watch/{$firstSlug}?list={$plSlug}" : "#";
                    $viewUrl = "{$baseUrl}/playlist/{$plSlug}";
                    $visInfo = getVisibilityLabel((int)$pl['visibility']);
                  ?>
                    <div class="card border-light-subtle shadow-sm bg-white overflow-hidden playlist-card-item" data-playlist-id="<?= $pl['id'] ?>">
                      <div class="card-header bg-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom border-light-subtle">
                        <div class="d-flex align-items-center gap-2 min-w-0">
                          <i class="bi bi-collection-play-fill text-danger fs-5"></i>
                          <div>
                            <span class="fw-bold text-dark"><?= h($pl['title']) ?></span>
                            <span class="badge <?= $visInfo['badge'] ?> border ms-1 small"><?= $visInfo['label'] ?></span>
                          </div>
                        </div>
                        <div class="d-flex align-items-center gap-1 gap-sm-2 flex-wrap">
                          <a href="<?= $viewUrl ?>" target="_blank" class="btn btn-sm btn-outline-dark px-2 py-1 small" title="Open Playlist Page">
                            <i class="bi bi-box-arrow-up-right me-1"></i>View
                          </a>
                          <?php if ($vCount > 0): ?>
                            <a href="<?= $playUrl ?>" target="_blank" class="btn btn-sm btn-outline-danger px-2 py-1 small" title="Play Playlist">
                              <i class="bi bi-play-fill me-1"></i>Play All (<?= $vCount ?>)
                            </a>
                          <?php endif; ?>
                          <button class="btn btn-sm btn-outline-secondary px-2 py-1 small edit-playlist-btn" 
                                  data-id="<?= $pl['id'] ?>" 
                                  data-title="<?= h($pl['title']) ?>" 
                                  data-desc="<?= h($pl['description'] ?? '') ?>" 
                                  data-vis="<?= $pl['visibility'] ?>" 
                                  title="Edit Playlist Details">
                            <i class="bi bi-pencil-square me-1"></i>Edit
                          </button>
                          <button class="btn btn-sm btn-outline-secondary px-2 py-1 small" data-bs-toggle="collapse" data-bs-target="#plCollapse-<?= $pl['id'] ?>" title="Show/Hide Videos">
                            <i class="bi bi-list-nested me-1"></i>Videos (<?= $vCount ?>)
                          </button>
                          <button class="btn btn-sm btn-outline-danger px-2 py-1 small delete-playlist-btn" data-id="<?= $pl['id'] ?>" data-title="<?= h($pl['title']) ?>" title="Delete Playlist">
                            <i class="bi bi-trash"></i>
                          </button>
                        </div>
                      </div>

                      <!-- Collapsible Video List -->
                      <div class="collapse <?= $vCount > 0 ? '' : 'show' ?>" id="plCollapse-<?= $pl['id'] ?>">
                        <div class="card-body p-3 bg-light">
                          <?php if (empty($plItems)): ?>
                            <div class="text-secondary small text-center py-2">
                              No videos in this playlist yet. Add videos from the <strong>Manage Assets</strong> table below by clicking the <i class="bi bi-collection-play text-danger"></i> icon!
                            </div>
                          <?php else: ?>
                            <div class="d-flex flex-column gap-2">
                              <?php foreach ($plItems as $pIdx => $pItem): 
                                $pThumb = $pItem['thumbnail_path'] ? "{$baseUrl}/" . ltrim($pItem['thumbnail_path'], '/') : "{$baseUrl}/assets/thumbnails/thumb-flowcv.png";
                              ?>
                                <div class="d-flex align-items-center justify-content-between gap-2 p-2 bg-white rounded border border-light-subtle playlist-item-row" data-asset-id="<?= $pItem['asset_id'] ?>">
                                  <div class="d-flex align-items-center gap-2 min-w-0">
                                    <span class="text-secondary small fw-bold font-monospace" style="width: 22px;"><?= $pIdx + 1 ?></span>
                                    <div style="width: 50px; height: 30px; border-radius: 4px; overflow: hidden; background: #000; flex-shrink: 0;">
                                      <img src="<?= $pThumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    </div>
                                    <a href="<?= $baseUrl ?>/watch/<?= urlencode($pItem['video_slug'] ?: $pItem['asset_id']) ?>?list=<?= urlencode($plSlug) ?>" target="_blank" class="small fw-semibold text-dark text-truncate text-decoration-none">
                                      <?= h($pItem['video_title']) ?>
                                    </a>
                                  </div>
                                  <div class="d-flex align-items-center gap-2 flex-shrink-0">
                                    <span class="small text-secondary font-monospace" style="font-size: 0.75rem;"><?= formatDuration((int)$pItem['duration_seconds']) ?></span>
                                    <div class="btn-group btn-group-sm" role="group">
                                      <button class="btn btn-outline-secondary btn-sm py-0 px-1 reorder-playlist-item-btn" data-playlist-id="<?= $pl['id'] ?>" data-asset-id="<?= $pItem['asset_id'] ?>" data-dir="up" <?= ($pIdx === 0) ? 'disabled' : '' ?> title="Move Up">
                                        <i class="bi bi-chevron-up"></i>
                                      </button>
                                      <button class="btn btn-outline-secondary btn-sm py-0 px-1 reorder-playlist-item-btn" data-playlist-id="<?= $pl['id'] ?>" data-asset-id="<?= $pItem['asset_id'] ?>" data-dir="down" <?= ($pIdx === $vCount - 1) ? 'disabled' : '' ?> title="Move Down">
                                        <i class="bi bi-chevron-down"></i>
                                      </button>
                                    </div>
                                    <button class="btn btn-sm btn-link text-danger p-0 remove-from-playlist-btn" data-playlist-id="<?= $pl['id'] ?>" data-asset-id="<?= $pItem['asset_id'] ?>" title="Remove from playlist">
                                      <i class="bi bi-x-circle fs-6"></i>
                                    </button>
                                  </div>
                                </div>
                              <?php endforeach; ?>
                            </div>
                          <?php endif; ?>
                        </div>
                      </div>
                    </div>
                  <?php endforeach; ?>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- TAB 4: FOLDER VISIBILITY & COLLAPSIBLE FILE EXPLORER -->
        <div class="tab-pane fade" id="folders-panel" role="tabpanel">
          
          <!-- Top Action & Filter Toolbar -->
          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-3 pb-3 border-bottom border-light-subtle">
            <div>
              <h5 class="fw-bold text-dark mb-1 d-flex align-items-center gap-2">
                <i class="bi bi-folder-fill text-warning"></i>
                <span>Folder Visibility & File Explorer</span>
              </h5>
              <p class="text-secondary small mb-0">
                Click any folder to expand and inspect its files. Use the 1-click bulk buttons to update the entire folder, or change any file individually with the inline dropdown.
              </p>
            </div>

            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div class="input-group input-group-sm" style="width: 220px;">
                <span class="input-group-text bg-light text-secondary border-secondary-subtle"><i class="bi bi-search"></i></span>
                <input type="text" id="folderSearchInput" class="form-control bg-white text-dark border-secondary-subtle" placeholder="Filter folders...">
              </div>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="expandAllFoldersBtn" title="Expand all folders">
                <i class="bi bi-arrows-expand me-1"></i> Expand All
              </button>
              <button type="button" class="btn btn-outline-secondary btn-sm" id="collapseAllFoldersBtn" title="Collapse all folders">
                <i class="bi bi-arrows-collapse me-1"></i> Collapse All
              </button>
            </div>
          </div>

          <?php if (empty($allFolders)): ?>
            <div class="p-4 rounded-3 border border-light-subtle bg-light text-center text-secondary">
              <i class="bi bi-folder-x display-6 d-block mb-2 text-muted"></i>
              <p class="small mb-0">No media folders detected yet. Place folders inside <code>assets/</code> (e.g. <code>assets/videos/MyProject</code>) and run FTP sync.</p>
            </div>
          <?php else: ?>
            <div class="d-flex flex-column gap-2" id="folderAccordionContainer">
              <?php 
              $folderIndex = 0;
              foreach ($allFolders as $dir => $f): 
                $folderIndex++;
                $collapseId = "folderCollapse_" . md5($dir);
                $tot = $f['total_files'];
                $fItems = $folderAssets[$dir] ?? [];

                if ($tot === 0) {
                  $statusBadge = 'bg-secondary-subtle text-secondary';
                  $statusLabel = 'Empty';
                } elseif ($f['count_public'] === $tot) {
                  $statusBadge = 'bg-success-subtle text-success';
                  $statusLabel = '🌍 All Public';
                } elseif ($f['count_private'] === $tot) {
                  $statusBadge = 'bg-danger-subtle text-danger';
                  $statusLabel = '🔒 All Private';
                } elseif ($f['count_unlisted'] === $tot) {
                  $statusBadge = 'bg-warning-subtle text-dark';
                  $statusLabel = '🔗 All Unlisted';
                } else {
                  $statusBadge = 'bg-info-subtle text-info';
                  $statusLabel = "⚡ Mixed ({$f['count_public']} Pub, {$f['count_unlisted']} Unl, {$f['count_private']} Prv)";
                }

                $iconClass = match($f['asset_type']) {
                  'video'    => 'bi-camera-video-fill text-danger',
                  'image'    => 'bi-image-fill text-info',
                  'document', 'archive' => 'bi-file-earmark-text-fill text-warning',
                  default    => 'bi-folder-fill text-warning',
                };
              ?>
                <div class="card border-light-subtle shadow-sm bg-white overflow-hidden folder-item-card" data-folder="<?= h($dir) ?>" data-search="<?= strtolower(h($f['display_name'])) ?>">
                  
                  <!-- Folder Header Bar -->
                  <div class="card-header bg-white py-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 border-bottom-0 folder-header-row" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#<?= $collapseId ?>" aria-expanded="false">
                    
                    <!-- Left: Chevron + Folder Icon + Name + Counts -->
                    <div class="d-flex align-items-center gap-2 min-w-0 flex-grow-1">
                      <i class="bi bi-chevron-right text-secondary folder-chevron transition-icon fs-6" style="transition: transform 0.2s;"></i>
                      <div class="p-1 px-2 rounded-2 bg-light border border-light-subtle flex-shrink-0">
                        <i class="bi <?= $iconClass ?> fs-5"></i>
                      </div>
                      <div class="min-w-0">
                        <span class="fw-bold text-dark text-truncate d-inline-block align-middle me-2" style="font-size: 0.95rem;">
                          <?= h($f['display_name']) ?>
                        </span>
                        <span class="small text-secondary font-monospace">
                          (<span class="folder-file-count"><?= $tot ?></span> files • <?= formatBytes((float)$f['total_size']) ?>)
                        </span>
                      </div>
                    </div>

                    <!-- Right: Status Badge + 1-Click Bulk Action Buttons -->
                    <div class="d-flex align-items-center gap-2 flex-wrap flex-shrink-0" onclick="event.stopPropagation();">
                      <span class="badge <?= $statusBadge ?> border small folder-status-badge py-1 px-2">
                        <?= $statusLabel ?>
                      </span>

                      <div class="btn-group btn-group-sm" role="group" title="Set visibility for ALL files in this folder">
                        <button type="button" class="btn btn-outline-success folder-bulk-btn py-1 px-2" data-folder="<?= h($dir) ?>" data-vis="2" data-name="<?= h($f['display_name']) ?>">
                          <i class="bi bi-globe2 me-1"></i>Public
                        </button>
                        <button type="button" class="btn btn-outline-warning text-dark folder-bulk-btn py-1 px-2" data-folder="<?= h($dir) ?>" data-vis="1" data-name="<?= h($f['display_name']) ?>">
                          <i class="bi bi-link-45deg me-1"></i>Unlisted
                        </button>
                        <button type="button" class="btn btn-outline-danger folder-bulk-btn py-1 px-2" data-folder="<?= h($dir) ?>" data-vis="0" data-name="<?= h($f['display_name']) ?>">
                          <i class="bi bi-lock-fill me-1"></i>Private
                        </button>
                      </div>
                    </div>

                  </div>

                  <!-- Collapsible File List Body -->
                  <div class="collapse folder-collapse-body" id="<?= $collapseId ?>">
                    <div class="card-body p-0 border-top border-light-subtle bg-light">
                      <?php if (empty($fItems)): ?>
                        <div class="p-3 text-center text-secondary small">No files inside this folder.</div>
                      <?php else: ?>
                        <div class="table-responsive bg-white">
                          <table class="table table-hover table-sm mb-0 align-middle">
                            <thead class="table-light text-secondary small text-uppercase" style="font-size: 0.72rem;">
                              <tr>
                                <th style="width: 36px;" class="ps-3">
                                  <input type="checkbox" class="form-check-input folder-select-all" data-target="<?= $collapseId ?>" title="Select all in this folder">
                                </th>
                                <th style="width: 60px;">Preview</th>
                                <th>Title & Filename</th>
                                <th style="width: 100px;">Size</th>
                                <th style="width: 140px;">Visibility</th>
                                <th style="width: 110px;" class="text-end pe-3">Actions</th>
                              </tr>
                            </thead>
                            <tbody>
                              <?php foreach ($fItems as $item): 
                                $isVid = ($item['asset_type'] === 'video');
                                $isImg = ($item['asset_type'] === 'image');
                                $tSlug = $item['slug'] ?: $item['id'];
                                $wUrl = "{$baseUrl}/watch/{$tSlug}";
                                $sUrl = "{$baseUrl}/stream/{$tSlug}";
                                $rUrl = "{$baseUrl}/" . ltrim($item['file_path'], '/');
                                $iThumb = $item['thumbnail_path'] ? "{$baseUrl}/" . ltrim($item['thumbnail_path'], '/') : ($isImg ? $rUrl : '');
                                if (!$iThumb) {
                                  $iThumb = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='45' viewBox='0 0 80 45'><rect width='80' height='45' fill='%23f0f0f0'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23666' font-size='10'>" . strtoupper($item['extension']) . "</text></svg>";
                                }
                                $iVis = (int)$item['visibility'];
                              ?>
                                <tr class="folder-file-row" data-id="<?= $item['id'] ?>" data-folder="<?= h($dir) ?>">
                                  <td class="ps-3">
                                    <input type="checkbox" class="form-check-input asset-chk" value="<?= $item['id'] ?>">
                                  </td>
                                  <td>
                                    <a href="<?= $wUrl ?>" target="_blank" style="display: block; width: 48px; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden; background: #000;">
                                      <img src="<?= $iThumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                                    </a>
                                  </td>
                                  <td>
                                    <div class="fw-semibold text-dark text-truncate" style="max-width: 340px; font-size: 0.88rem;">
                                      <a href="<?= $wUrl ?>" target="_blank" class="text-dark text-decoration-none hover-danger">
                                        <?= h($item['title']) ?>
                                      </a>
                                    </div>
                                    <div class="small text-secondary font-monospace" style="font-size: 0.73rem;">
                                      <?= h($item['file_name']) ?>
                                    </div>
                                  </td>
                                  <td>
                                    <span class="small font-monospace text-secondary" style="font-size: 0.78rem;">
                                      <?= formatBytes((float)$item['file_size']) ?>
                                    </span>
                                  </td>
                                  <td>
                                    <select class="form-select form-select-sm inline-vis-select py-1" data-id="<?= $item['id'] ?>" data-folder="<?= h($dir) ?>" style="font-size: 0.78rem; width: 125px;">
                                      <option value="2" <?= $iVis === 2 ? 'selected' : '' ?>>🌍 Public</option>
                                      <option value="1" <?= $iVis === 1 ? 'selected' : '' ?>>🔗 Unlisted</option>
                                      <option value="0" <?= $iVis === 0 ? 'selected' : '' ?>>🔒 Private</option>
                                    </select>
                                  </td>
                                  <td class="text-end pe-3">
                                    <div class="btn-group btn-group-sm">
                                      <a href="<?= $wUrl ?>" target="_blank" class="btn btn-outline-secondary p-1 px-2" title="View / Play">
                                        <i class="bi bi-box-arrow-up-right"></i>
                                      </a>
                                      <button type="button" class="btn btn-outline-danger delete-asset-btn p-1 px-2" data-id="<?= $item['id'] ?>" data-title="<?= h($item['title']) ?>" title="Delete">
                                        <i class="bi bi-trash"></i>
                                      </button>
                                    </div>
                                  </td>
                                </tr>
                              <?php endforeach; ?>
                            </tbody>
                          </table>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>

      </div>
    </div>
  </div>

  <!-- Asset Management Table -->
  <div class="card bg-white border-light-subtle shadow-sm">
    <div class="card-header border-bottom border-light-subtle bg-light d-flex flex-wrap align-items-center justify-content-between gap-2 p-3">
      <h5 class="text-dark mb-0 d-flex align-items-center gap-2">
        <i class="bi bi-collection-play-fill text-danger"></i>
        <span>Manage Assets</span>
        <span class="badge bg-secondary-subtle text-dark small border"><?= count($assetsList) ?> Items</span>
      </h5>

      <!-- Filters Form -->
      <form method="GET" action="" class="d-flex flex-wrap gap-2">
        <input type="text" name="q" class="form-control form-control-sm bg-white text-dark border-secondary-subtle" placeholder="Search title or ID..." value="<?= h($searchFilter) ?>" style="width: 170px;">
        
        <select name="type" class="form-select form-select-sm bg-white text-dark border-secondary-subtle" style="width: 145px;" onchange="this.form.submit()">
          <option value="">All Categories</option>
          <option value="video" <?= $typeFilter === 'video' ? 'selected' : '' ?>>Videos</option>
          <option value="image" <?= $typeFilter === 'image' ? 'selected' : '' ?>>Images</option>
          <option value="docs_zips" <?= $typeFilter === 'docs_zips' ? 'selected' : '' ?>>Documents & Archives</option>
        </select>

        <select name="folder" class="form-select form-select-sm bg-white text-dark border-secondary-subtle" style="width: 155px;" onchange="this.form.submit()">
          <option value="">All Folders</option>
          <?php foreach ($allFolders as $fPath => $fData): ?>
            <option value="<?= h($fPath) ?>" <?= ($folderFilter === $fPath) ? 'selected' : '' ?>><?= h($fData['display_name']) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="project" class="form-select form-select-sm bg-white text-dark border-secondary-subtle" style="width: 140px;" onchange="this.form.submit()">
          <option value="">All Projects</option>
          <?php foreach ($allProjects as $p): ?>
            <option value="<?= h($p) ?>" <?= $projectFilter === $p ? 'selected' : '' ?>><?= h($p) ?></option>
          <?php endforeach; ?>
        </select>

        <select name="source" class="form-select form-select-sm bg-white text-dark border-secondary-subtle" style="width: 125px;" onchange="this.form.submit()">
          <option value="">All Sources</option>
          <option value="web_upload" <?= $sourceFilter === 'web_upload' ? 'selected' : '' ?>>Web Upload</option>
          <option value="ftp_sync" <?= $sourceFilter === 'ftp_sync' ? 'selected' : '' ?>>FTP Sync</option>
        </select>

        <?php if ($typeFilter || $folderFilter || $sourceFilter || $projectFilter || $searchFilter): ?>
          <a href="<?= $baseUrl ?>/studio" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </form>
    </div>

    <!-- Bulk Visibility Toolbar (appears when 1 or more assets are checked) -->
    <div id="bulkToolbar" class="d-none bg-danger-subtle border-bottom border-danger-subtle p-2 px-3 d-flex flex-wrap align-items-center justify-content-between gap-2 shadow-sm">
      <div class="d-flex align-items-center gap-2">
        <i class="bi bi-check2-square text-danger fs-5"></i>
        <span class="fw-bold text-dark small" id="bulkCountLabel">0 assets selected</span>
      </div>
      <div class="d-flex align-items-center gap-2 flex-wrap">
        <span class="small text-secondary fw-semibold">Set Visibility:</span>
        <select id="bulkVisSelect" class="form-select form-select-sm bg-white border-secondary-subtle" style="width: 160px;">
          <option value="1">🔗 Unlisted (Default)</option>
          <option value="2">🌍 Public</option>
          <option value="0">🔒 Private</option>
        </select>
        <button type="button" class="btn btn-sm btn-danger fw-semibold px-3" id="applyBulkAssetVisBtn">
          <i class="bi bi-check2-circle me-1"></i> Apply to Selected
        </button>
        <button type="button" class="btn btn-sm btn-outline-secondary" id="deselectAllBtn">
          Deselect All
        </button>
      </div>
    </div>

    <!-- Mobile Card View (< 768px) -->
    <div class="d-md-none p-3 d-flex flex-column gap-3 bg-light">
      <?php if (empty($assetsList)): ?>
        <div class="text-center py-4 text-secondary bg-white rounded-3 border p-3">
          No assets found matching the criteria. Use the uploader or run sync above.
        </div>
      <?php else: ?>
        <?php foreach ($assetsList as $asset): 
          $isVideo = ($asset['asset_type'] === 'video');
          $isImage = ($asset['asset_type'] === 'image');
          $rawUrl  = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
          $targetSlug = $asset['slug'] ?: $asset['id'];
          $watchUrl = "{$baseUrl}/watch/{$targetSlug}";
          $streamUrl = "{$baseUrl}/stream/{$targetSlug}";
          $thumb = $asset['thumbnail_path'] ? "{$baseUrl}/" . ltrim($asset['thumbnail_path'], '/') : ($isImage ? $rawUrl : '');
          if (!$thumb) {
            $thumb = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='45' viewBox='0 0 80 45'><rect width='80' height='45' fill='%23f0f0f0'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23666' font-size='10'>" . strtoupper($asset['extension']) . "</text></svg>";
          }

          $embedTag = $isVideo 
            ? "<iframe src=\"{$baseUrl}/embed/{$targetSlug}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>"
            : ($isImage ? "<img src=\"{$rawUrl}\" alt=\"" . h($asset['title']) . "\" />" : "<a href=\"{$rawUrl}\" download>Download " . h($asset['title']) . "</a>");
          
          $shareData = json_encode([
            'id'          => $asset['id'],
            'title'       => $asset['title'],
            'watchUrl'    => $watchUrl,
            'cdnUrl'      => $isVideo ? $streamUrl : $rawUrl,
            'embedTag'    => $embedTag,
            'markdownTag' => "![" . h($asset['title']) . "]({$rawUrl})"
          ]);

          $tStmt = $pdo->prepare("SELECT tag FROM asset_tags WHERE asset_id = :id");
          $tStmt->execute([':id' => $asset['id']]);
          $assetTags = implode(', ', $tStmt->fetchAll(PDO::FETCH_COLUMN));

          $editData = json_encode([
            'id'             => $asset['id'],
            'title'          => $asset['title'],
            'description'    => $asset['description'],
            'copyright_info' => $asset['copyright_info'] ?? '',
            'category_id'    => $asset['category_id'],
            'project'        => $asset['project'],
            'duration'       => $asset['duration_seconds'],
            'visibility'     => (int)($asset['visibility'] ?? ($asset['is_public'] ? 2 : 0)),
            'tags'           => $assetTags,
          ]);

          $visVal = (int)($asset['visibility'] ?? ($asset['is_public'] >= 1 ? 2 : 0));
          $visInfo = getVisibilityLabel($visVal);
        ?>
          <div class="card bg-white border-light-subtle shadow-sm rounded-3 overflow-hidden asset-row" data-id="<?= $asset['id'] ?>" data-folder="<?= h(dirname(str_replace('\\', '/', $asset['file_path']))) ?>">
            <div class="card-body p-3">
              <div class="d-flex gap-2 mb-2 align-items-start">
                <div class="pt-1">
                  <input type="checkbox" class="form-check-input asset-chk" value="<?= $asset['id'] ?>">
                </div>
                <a href="<?= $watchUrl ?>" target="_blank" style="width: 80px; aspect-ratio: 16/9; border-radius: 6px; overflow: hidden; background: #000; flex-shrink: 0; display: block;">
                  <img src="<?= $thumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                </a>
                <div class="min-w-0 flex-grow-1">
                  <div class="fw-bold text-dark text-truncate mb-1" style="font-size: 0.95rem;"><?= h($asset['title']) ?></div>
                  <div class="small text-secondary font-monospace text-truncate" style="font-size: 0.75rem;">ID: <?= $asset['id'] ?></div>
                  <div class="small text-muted text-truncate" style="font-size: 0.75rem;"><?= h($asset['file_name']) ?></div>
                </div>
              </div>

              <!-- Badges Row -->
              <div class="d-flex flex-wrap gap-1 align-items-center mb-3">
                <span class="badge bg-light text-dark border small"><?= h($asset['category_name'] ?: 'General') ?></span>
                <?php if (!empty($asset['project'])): ?>
                  <span class="badge bg-primary-subtle text-primary border border-primary-subtle small"><?= h($asset['project']) ?></span>
                <?php endif; ?>
                <span class="badge <?= $visInfo['badge'] ?> border small">
                  <i class="bi <?= $visInfo['icon'] ?> me-1"></i><?= $visInfo['label'] ?>
                </span>
                <span class="badge bg-secondary-subtle text-dark border small font-monospace"><?= formatBytes((float)$asset['file_size']) ?></span>
                <span class="badge bg-light text-secondary border small"><i class="bi bi-eye me-1"></i><?= number_format((int)$asset['views_count']) ?></span>
                <span class="text-secondary small ms-auto font-monospace" style="font-size: 0.72rem;"><?= timeAgo($asset['created_at']) ?></span>
              </div>

              <!-- Action Buttons Row -->
              <div class="d-flex align-items-center justify-content-between pt-2 border-top border-light-subtle">
                <div class="btn-group btn-group-sm">
                  <?php if ($isVideo): ?>
                    <button class="btn btn-outline-danger add-to-playlist-btn px-2" data-id="<?= $asset['id'] ?>" data-title="<?= h($asset['title']) ?>" title="Add to Playlist">
                      <i class="bi bi-collection-play"></i>
                    </button>
                  <?php endif; ?>
                  <button class="btn btn-outline-warning edit-asset-btn px-2" data-asset='<?= htmlspecialchars($editData, ENT_QUOTES) ?>' title="Edit">
                    <i class="bi bi-pencil-square"></i> Edit
                  </button>
                  <button class="btn btn-outline-info px-2" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>' title="Share">
                    <i class="bi bi-share"></i> Share
                  </button>
                </div>

                <div class="d-flex gap-1">
                  <a href="<?= $watchUrl ?>" target="_blank" class="btn btn-sm btn-outline-secondary px-2" title="Watch">
                    <i class="bi bi-box-arrow-up-right"></i>
                  </a>
                  <button class="btn btn-sm btn-outline-danger delete-asset-btn px-2" data-id="<?= $asset['id'] ?>" data-title="<?= h($asset['title']) ?>" title="Delete">
                    <i class="bi bi-trash"></i>
                  </button>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>

    <!-- Desktop Table (>= 768px) -->
    <div class="d-none d-md-block table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr class="text-secondary small text-uppercase">
            <th scope="col" style="width: 38px;">
              <input type="checkbox" id="selectAllDesktop" class="form-check-input asset-master-chk" title="Select All">
            </th>
            <th scope="col" style="width: 70px;">Asset</th>
            <th scope="col">Title & ID</th>
            <th scope="col">Category</th>
            <th scope="col">Project</th>
            <th scope="col">Size</th>
            <th scope="col">Source</th>
            <th scope="col">Visibility</th>
            <th scope="col">Views</th>
            <th scope="col">Date</th>
            <th scope="col" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($assetsList)): ?>
            <tr>
              <td colspan="11" class="text-center py-4 text-secondary">
                No assets found matching the criteria. Use the uploader or run sync above.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($assetsList as $asset): 
              $isVideo = ($asset['asset_type'] === 'video');
              $isImage = ($asset['asset_type'] === 'image');
              $rawUrl  = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
              $targetSlug = $asset['slug'] ?: $asset['id'];
              $watchUrl = "{$baseUrl}/watch/{$targetSlug}";
              $streamUrl = "{$baseUrl}/stream/{$targetSlug}";
              $thumb = $asset['thumbnail_path'] ? "{$baseUrl}/" . ltrim($asset['thumbnail_path'], '/') : ($isImage ? $rawUrl : '');
              if (!$thumb) {
                $thumb = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='45' viewBox='0 0 80 45'><rect width='80' height='45' fill='%23f0f0f0'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23666' font-size='10'>" . strtoupper($asset['extension']) . "</text></svg>";
              }

              $embedTag = $isVideo 
                ? "<iframe src=\"{$baseUrl}/embed/{$targetSlug}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>"
                : ($isImage ? "<img src=\"{$rawUrl}\" alt=\"" . h($asset['title']) . "\" />" : "<a href=\"{$rawUrl}\" download>Download " . h($asset['title']) . "</a>");
              
              $shareData = json_encode([
                'id'          => $asset['id'],
                'title'       => $asset['title'],
                'watchUrl'    => $watchUrl,
                'cdnUrl'      => $isVideo ? $streamUrl : $rawUrl,
                'embedTag'    => $embedTag,
                'markdownTag' => "![" . h($asset['title']) . "]({$rawUrl})"
              ]);

              // Fetch tags for edit modal
              $tStmt = $pdo->prepare("SELECT tag FROM asset_tags WHERE asset_id = :id");
              $tStmt->execute([':id' => $asset['id']]);
              $assetTags = implode(', ', $tStmt->fetchAll(PDO::FETCH_COLUMN));

              $editData = json_encode([
                'id'             => $asset['id'],
                'title'          => $asset['title'],
                'description'    => $asset['description'],
                'copyright_info' => $asset['copyright_info'] ?? '',
                'category_id'    => $asset['category_id'],
                'project'        => $asset['project'],
                'duration'       => $asset['duration_seconds'],
                'visibility'     => (int)($asset['visibility'] ?? ($asset['is_public'] ? 2 : 0)),
                'tags'           => $assetTags,
              ]);
            ?>
              <tr class="asset-row" data-id="<?= $asset['id'] ?>" data-folder="<?= h(dirname(str_replace('\\', '/', $asset['file_path']))) ?>">
                <td>
                  <input type="checkbox" class="form-check-input asset-chk" value="<?= $asset['id'] ?>">
                </td>
                <td>
                  <a href="<?= $watchUrl ?>" target="_blank" style="display: block; width: 64px; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden; background: #e0e0e0;">
                    <img src="<?= $thumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  </a>
                </td>
                <td>
                  <div class="fw-bold text-dark"><?= h($asset['title']) ?></div>
                  <div class="small text-secondary font-monospace">ID: <?= $asset['id'] ?> • <?= h($asset['file_name']) ?></div>
                </td>
                <td><span class="badge bg-light text-dark border"><?= h($asset['category_name'] ?: 'General') ?></span></td>
                <td>
                  <?php if (!empty($asset['project'])): ?>
                    <span class="badge bg-primary-subtle text-primary border border-primary-subtle"><i class="bi bi-folder2 me-1"></i><?= h($asset['project']) ?></span>
                  <?php else: ?>
                    <span class="text-secondary small">—</span>
                  <?php endif; ?>
                </td>
                <td><span class="small font-monospace"><?= formatBytes((float)$asset['file_size']) ?></span></td>
                <td>
                  <?php if ($asset['source'] === 'web_upload'): ?>
                    <span class="badge bg-danger-subtle text-danger"><i class="bi bi-cloud-arrow-up"></i> Web</span>
                  <?php else: ?>
                    <span class="badge bg-success-subtle text-success"><i class="bi bi-folder-symlink"></i> FTP Sync</span>
                  <?php endif; ?>
                </td>
                <td>
                  <?php 
                  $visVal = (int)($asset['visibility'] ?? ($asset['is_public'] >= 1 ? 2 : 0));
                  $visInfo = getVisibilityLabel($visVal);
                  ?>
                  <span class="badge <?= $visInfo['badge'] ?> border" title="<?= $visInfo['label'] ?>">
                    <i class="bi <?= $visInfo['icon'] ?>"></i>
                    <span class="d-none d-xl-inline ms-1"><?= $visInfo['label'] ?></span>
                  </span>
                </td>
                <td><span class="small"><?= number_format((int)$asset['views_count']) ?></span></td>
                <td><span class="small text-secondary"><?= timeAgo($asset['created_at']) ?></span></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
                    <?php if ($isVideo): ?>
                      <button class="btn btn-outline-danger add-to-playlist-btn" data-id="<?= $asset['id'] ?>" data-title="<?= h($asset['title']) ?>" title="Add to Playlist">
                        <i class="bi bi-collection-play"></i>
                      </button>
                    <?php endif; ?>
                    <button class="btn btn-outline-warning edit-asset-btn" data-asset='<?= htmlspecialchars($editData, ENT_QUOTES) ?>' title="Edit Metadata">
                      <i class="bi bi-pencil-square"></i>
                    </button>
                    <button class="btn btn-outline-info" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>' title="Share / Embed">
                      <i class="bi bi-share"></i>
                    </button>
                    <a href="<?= $watchUrl ?>" target="_blank" class="btn btn-outline-secondary" title="Watch / View">
                      <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                    <button class="btn btn-outline-danger delete-asset-btn" data-id="<?= $asset['id'] ?>" data-title="<?= h($asset['title']) ?>" title="Delete">
                      <i class="bi bi-trash"></i>
                    </button>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<!-- EDIT ASSET MODAL -->
<div class="modal fade" id="editAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-white text-dark border-light-subtle shadow">
      <form id="editAssetForm" enctype="multipart/form-data">
        <div class="modal-header border-bottom border-light-subtle">
          <h5 class="modal-title d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square text-warning"></i>
            <span>Edit Asset Metadata</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id" id="editAssetId">

          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Title</label>
            <input type="text" name="title" id="editTitle" class="form-control bg-light text-dark border-secondary-subtle" required>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-4">
              <label class="form-label text-secondary small fw-bold">Category</label>
              <select name="category_id" id="editCategory" class="form-select bg-light text-dark border-secondary-subtle">
                <option value="">None / General</option>
                <?php foreach ($categories as $cat): ?>
                  <option value="<?= $cat['id'] ?>"><?= h($cat['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="col-md-4">
              <label class="form-label text-secondary small fw-bold">Project</label>
              <input type="text" name="project" id="editProject" list="projectsDatalist" class="form-control bg-light text-dark border-secondary-subtle" placeholder="e.g. ScripVault">
            </div>

            <div class="col-md-4">
              <label class="form-label text-secondary small fw-bold">Visibility</label>
              <select name="visibility" id="editVisibility" class="form-select bg-light text-dark border-secondary-subtle">
                <option value="2">🌍 Public &mdash; visible in feed</option>
                <option value="1">🔗 Unlisted &mdash; anyone with link</option>
                <option value="0">🔒 Private &mdash; admin only</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Description (Timestamps like 01:23 will become clickable chapters)</label>
            <textarea name="description" id="editDesc" class="form-control bg-light text-dark border-secondary-subtle" rows="4"></textarea>
          </div>

          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">
              <i class="bi bi-c-circle me-1"></i>Copyright & License Info
            </label>
            <textarea name="copyright_info" id="editCopyrightInfo" class="form-control bg-light text-dark border-secondary-subtle" rows="2" placeholder="e.g. © 2026 Runit. All rights reserved. / CC BY 4.0"></textarea>
            <div class="form-text text-secondary" style="font-size: 0.75rem;">Shown below the description on the watch page.</div>
          </div>

          <div class="row g-3 mb-3">
            <div class="col-md-6">
              <label class="form-label text-secondary small fw-bold">Custom Thumbnail (Replace existing)</label>
              <input type="file" name="thumbnail" accept="image/*" class="form-control bg-light text-dark border-secondary-subtle">
            </div>

            <div class="col-md-6">
              <label class="form-label text-secondary small fw-bold">Tags (Comma-separated)</label>
              <input type="text" name="tags" id="editTags" class="form-control bg-light text-dark border-secondary-subtle">
            </div>
          </div>
        </div>
        <div class="modal-footer border-top border-light-subtle">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm" id="saveEditBtn">
            <i class="bi bi-save me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- DELETE CONFIRMATION MODAL -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark border-danger-subtle shadow">
      <div class="modal-header border-danger-subtle">
        <h5 class="modal-title text-danger"><i class="bi bi-trash-fill me-2"></i>Delete Asset</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <p>Are you sure you want to remove <strong id="deleteAssetTitle"></strong>?</p>
        <div class="form-check">
          <input class="form-check-input" type="checkbox" id="deletePhysicalCheck">
          <label class="form-check-label text-secondary small" for="deletePhysicalCheck">
            Also permanently delete physical file from server disk
          </label>
        </div>
      </div>
      <div class="modal-footer border-top border-light-subtle">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <button type="button" class="btn btn-danger btn-sm" id="confirmDeleteBtn">Delete</button>
      </div>
    </div>
  </div>
</div>

<!-- ADD TO PLAYLIST MODAL -->
<div class="modal fade" id="addToPlaylistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark border-light-subtle shadow">
      <div class="modal-header border-bottom border-light-subtle">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <i class="bi bi-collection-play-fill text-danger"></i>
          <span>Add Video to Playlist</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <input type="hidden" id="addPlaylistAssetId">
        <p class="small text-secondary mb-3">Adding video: <strong id="addPlaylistAssetTitle" class="text-dark"></strong></p>
        
        <?php if (empty($allPlaylists)): ?>
          <div class="alert alert-warning small mb-0">
            No playlists found. Please create a playlist first in the <strong>Playlists Manager</strong> tab above.
          </div>
        <?php else: ?>
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Select Target Playlist</label>
            <select class="form-select bg-light text-dark border-secondary-subtle" id="targetPlaylistSelect">
              <?php foreach ($allPlaylists as $pl): ?>
                <option value="<?= $pl['id'] ?>"><?= h($pl['title']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-top border-light-subtle">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
        <?php if (!empty($allPlaylists)): ?>
          <button type="button" class="btn btn-danger btn-sm" id="confirmAddToPlaylistBtn">
            <i class="bi bi-plus-circle me-1"></i> Add to Playlist
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- EDIT PLAYLIST MODAL -->
<div class="modal fade" id="editPlaylistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark border-light-subtle shadow">
      <div class="modal-header border-bottom border-light-subtle">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <i class="bi bi-pencil-square text-danger"></i>
          <span>Edit Playlist Details</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <form id="editPlaylistForm">
        <input type="hidden" name="id" id="editPlId">
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Playlist Title *</label>
            <input type="text" name="title" id="editPlTitle" class="form-control bg-light text-dark border-secondary-subtle" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Description (Optional)</label>
            <textarea name="description" id="editPlDesc" class="form-control bg-light text-dark border-secondary-subtle" rows="3"></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Visibility</label>
            <select name="visibility" id="editPlVis" class="form-select bg-light text-dark border-secondary-subtle">
              <option value="1">🔗 Unlisted — anyone with link (Default)</option>
              <option value="2">🌍 Public — visible in showcase feed</option>
              <option value="0">🔒 Private — admin only</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top border-light-subtle">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm fw-semibold" id="saveEditPlaylistBtn">
            <i class="bi bi-check-circle me-1"></i> Save Changes
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// File drop zone UX
const fileInput = document.getElementById('fileInput');
const dropZone = document.getElementById('dropZone');
const selectedFileInfo = document.getElementById('selectedFileInfo');
const uploadTitle = document.getElementById('uploadTitle');

fileInput.addEventListener('change', () => {
  if (fileInput.files.length > 0) {
    const file = fileInput.files[0];
    selectedFileInfo.textContent = `Selected: ${file.name} (${(file.size / 1024 / 1024).toFixed(2)} MB)`;
    if (!uploadTitle.value) {
      uploadTitle.value = file.name.replace(/\.[^/.]+$/, "").replace(/[-_]/g, " ");
    }
  }
});

dropZone.addEventListener('dragover', (e) => {
  e.preventDefault();
  dropZone.classList.add('bg-secondary-subtle');
});

dropZone.addEventListener('dragleave', () => {
  dropZone.classList.remove('bg-secondary-subtle');
});

dropZone.addEventListener('drop', (e) => {
  e.preventDefault();
  dropZone.classList.remove('bg-secondary-subtle');
  if (e.dataTransfer.files.length > 0) {
    fileInput.files = e.dataTransfer.files;
    fileInput.dispatchEvent(new Event('change'));
  }
});

// Web UI Upload Submission via AJAX
const webUploadForm = document.getElementById('webUploadForm');
const uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
const uploadProgressContainer = document.getElementById('uploadProgressContainer');
const uploadProgressBar = document.getElementById('uploadProgressBar');
const uploadProgressText = document.getElementById('uploadProgressText');

webUploadForm.addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = new FormData(webUploadForm);

  uploadSubmitBtn.disabled = true;
  uploadProgressContainer.classList.remove('d-none');
  uploadProgressBar.style.width = '0%';
  uploadProgressText.textContent = '0%';

  const xhr = new XMLHttpRequest();
  xhr.open('POST', 'api/upload.php', true);

  xhr.upload.onprogress = (e) => {
    if (e.lengthComputable) {
      const pct = Math.round((e.loaded / e.total) * 100);
      uploadProgressBar.style.width = pct + '%';
      uploadProgressText.textContent = pct + '%';
    }
  };

  xhr.onload = () => {
    uploadSubmitBtn.disabled = false;
    try {
      const resp = JSON.parse(xhr.responseText);
      if (resp.success) {
        showToast('Asset uploaded successfully!');
        setTimeout(() => window.location.reload(), 1000);
      } else {
        showToast(resp.error || 'Upload failed', false);
      }
    } catch (err) {
      showToast('Server response error: ' + xhr.responseText, false);
    }
  };

  xhr.onerror = () => {
    uploadSubmitBtn.disabled = false;
    showToast('Network error during upload', false);
  };

  xhr.send(formData);
});

// FTP & Filesystem Sync Trigger
function executeSync(btn, reportBoxId = null) {
  const origHtml = btn.innerHTML;
  btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scanning Filesystem...';
  btn.disabled = true;

  fetch('api/sync.php')
    .then(res => res.json())
    .then(data => {
      btn.innerHTML = origHtml;
      btn.disabled = false;
      if (data.success) {
        const stats = data.stats;
        showToast(`Sync complete! Scanned: ${stats.scanned}, Added: ${stats.added}, Updated: ${stats.updated}`);
        
        if (reportBoxId) {
          const reportBox = document.getElementById(reportBoxId);
          reportBox.classList.remove('d-none');
          if (stats.details.length > 0) {
            reportBox.innerHTML = stats.details.map(d => `<div>● ${d}</div>`).join('');
          } else {
            reportBox.innerHTML = '<span class="text-success">Everything is already up-to-date!</span>';
          }
        }
        setTimeout(() => window.location.reload(), 2000);
      } else {
        showToast(data.error || 'Sync failed', false);
      }
    })
    .catch(err => {
      btn.innerHTML = origHtml;
      btn.disabled = false;
      showToast('Sync request error: ' + err.message, false);
    });
}

document.getElementById('studioSyncBtn')?.addEventListener('click', function() {
  executeSync(this);
});

document.getElementById('ftpSyncActionBtn')?.addEventListener('click', function() {
  executeSync(this, 'syncReportBox');
});

// Edit Asset Modal
document.querySelectorAll('.edit-asset-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const asset = JSON.parse(btn.getAttribute('data-asset'));
    document.getElementById('editAssetId').value = asset.id;
    document.getElementById('editTitle').value = asset.title;
    document.getElementById('editDesc').value = asset.description || '';
    document.getElementById('editCopyrightInfo').value = asset.copyright_info || '';
    document.getElementById('editCategory').value = asset.category_id || '';
    document.getElementById('editProject').value = asset.project || '';
    document.getElementById('editVisibility').value = String(asset.visibility ?? 2);
    document.getElementById('editTags').value = asset.tags || '';

    const modal = new bootstrap.Modal(document.getElementById('editAssetModal'));
    modal.show();
  });
});

document.getElementById('editAssetForm')?.addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const saveBtn = document.getElementById('saveEditBtn');
  saveBtn.disabled = true;

  fetch('api/update-asset.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      saveBtn.disabled = false;
      if (data.success) {
        showToast('Asset metadata saved!');
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Failed to save', false);
      }
    })
    .catch(err => {
      saveBtn.disabled = false;
      showToast('Update failed: ' + err.message, false);
    });
});

// Delete Asset Modal
let activeDeleteId = null;
document.querySelectorAll('.delete-asset-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    activeDeleteId = btn.getAttribute('data-id');
    document.getElementById('deleteAssetTitle').textContent = btn.getAttribute('data-title');
    const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
    modal.show();
  });
});

document.getElementById('confirmDeleteBtn')?.addEventListener('click', () => {
  if (!activeDeleteId) return;
  const deletePhysical = document.getElementById('deletePhysicalCheck').checked ? 1 : 0;
  const formData = new FormData();
  formData.append('id', activeDeleteId);
  formData.append('delete_physical', deletePhysical);

  fetch('api/delete-asset.php', { method: 'POST', body: formData })
    .then(res => res.json())
    .then(data => {
      if (data.success) {
        showToast('Asset deleted successfully');
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Delete failed', false);
      }
    });
});

// Playlists Manager Handlers
document.getElementById('createPlaylistForm')?.addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const btn = document.getElementById('createPlaylistBtn') || document.getElementById('createPlaylistSubmitBtn');
  if (btn) btn.disabled = true;

  fetch('api/playlists.php?action=create', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (btn) btn.disabled = false;
      if (data.success) {
        showToast('Playlist created successfully!');
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Failed to create playlist', false);
      }
    })
    .catch(err => {
      if (btn) btn.disabled = false;
      showToast('Error: ' + err.message, false);
    });
});

let editPlaylistModalInstance = null;
document.querySelectorAll('.edit-playlist-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title') || '';
    const desc = btn.getAttribute('data-desc') || '';
    const vis = btn.getAttribute('data-vis') || '1';

    document.getElementById('editPlId').value = id;
    document.getElementById('editPlTitle').value = title;
    document.getElementById('editPlDesc').value = desc;
    document.getElementById('editPlVis').value = vis;

    const modalEl = document.getElementById('editPlaylistModal');
    editPlaylistModalInstance = new bootstrap.Modal(modalEl);
    editPlaylistModalInstance.show();
  });
});

document.getElementById('editPlaylistForm')?.addEventListener('submit', (e) => {
  e.preventDefault();
  const formData = new FormData(e.target);
  const saveBtn = document.getElementById('saveEditPlaylistBtn');
  if (saveBtn) saveBtn.disabled = true;

  fetch('api/playlists.php?action=update', { method: 'POST', body: formData })
    .then(r => r.json())
    .then(data => {
      if (saveBtn) saveBtn.disabled = false;
      if (data.success) {
        showToast('Playlist updated successfully!');
        if (editPlaylistModalInstance) editPlaylistModalInstance.hide();
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Failed to update playlist', false);
      }
    })
    .catch(err => {
      if (saveBtn) saveBtn.disabled = false;
      showToast('Error: ' + err.message, false);
    });
});

document.querySelectorAll('.reorder-playlist-item-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const playlistId = btn.getAttribute('data-playlist-id');
    const assetId = btn.getAttribute('data-asset-id');
    const direction = btn.getAttribute('data-dir');
    if (!playlistId || !assetId || !direction) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('playlist_id', playlistId);
    fd.append('asset_id', assetId);
    fd.append('direction', direction);

    fetch('api/playlists.php?action=reorder', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Playlist order updated');
          setTimeout(() => window.location.reload(), 400);
        } else {
          btn.disabled = false;
          showToast(data.error || 'Failed to reorder', false);
        }
      })
      .catch(err => {
        btn.disabled = false;
        showToast('Error: ' + err.message, false);
      });
  });
});

document.querySelectorAll('.delete-playlist-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    if (!confirm(`Are you sure you want to delete playlist "${title}"? (Videos inside will not be deleted)`)) return;

    const fd = new FormData();
    fd.append('id', id);

    fetch('api/playlists.php?action=delete', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Playlist deleted');
          setTimeout(() => window.location.reload(), 800);
        } else {
          showToast(data.error || 'Failed to delete playlist', false);
        }
      });
  });
});

document.querySelectorAll('.remove-from-playlist-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const playlistId = btn.getAttribute('data-playlist-id');
    const assetId = btn.getAttribute('data-asset-id');
    if (!confirm('Remove this video from the playlist?')) return;

    const fd = new FormData();
    fd.append('playlist_id', playlistId);
    fd.append('asset_id', assetId);

    fetch('api/playlists.php?action=remove_item', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        if (data.success) {
          showToast('Video removed from playlist');
          setTimeout(() => window.location.reload(), 800);
        } else {
          showToast(data.error || 'Failed to remove', false);
        }
      });
  });
});

let addToPlaylistModalInstance = null;
document.querySelectorAll('.add-to-playlist-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const id = btn.getAttribute('data-id');
    const title = btn.getAttribute('data-title');
    document.getElementById('addPlaylistAssetId').value = id;
    document.getElementById('addPlaylistAssetTitle').textContent = title;
    const modalEl = document.getElementById('addToPlaylistModal');
    addToPlaylistModalInstance = new bootstrap.Modal(modalEl);
    addToPlaylistModalInstance.show();
  });
});

document.getElementById('confirmAddToPlaylistBtn')?.addEventListener('click', () => {
  const assetId = document.getElementById('addPlaylistAssetId').value;
  const playlistId = document.getElementById('targetPlaylistSelect')?.value;
  if (!assetId || !playlistId) return;

  const fd = new FormData();
  fd.append('playlist_id', playlistId);
  fd.append('asset_id', assetId);

  const btn = document.getElementById('confirmAddToPlaylistBtn');
  btn.disabled = true;

  fetch('api/playlists.php?action=add_item', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      if (data.success) {
        showToast('Video added to playlist!');
        if (addToPlaylistModalInstance) addToPlaylistModalInstance.hide();
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Failed to add video', false);
      }
    })
    .catch(err => {
      btn.disabled = false;
      showToast('Error: ' + err.message, false);
    });
});

// ============================================================
// Folder-Level Visibility & Collapsible Explorer Controls
// ============================================================

// 1. One-Click Folder Bulk Visibility Buttons
document.querySelectorAll('.folder-bulk-btn').forEach(btn => {
  btn.addEventListener('click', (e) => {
    e.stopPropagation();
    const folder = btn.getAttribute('data-folder');
    const folderName = btn.getAttribute('data-name') || folder;
    const vis = parseInt(btn.getAttribute('data-vis'), 10);
    const visLabel = (vis === 2) ? 'Public' : ((vis === 0) ? 'Private' : 'Unlisted');

    if (!confirm(`Set ALL files in folder "${folderName}" to ${visLabel}?`)) return;

    btn.disabled = true;
    const fd = new FormData();
    fd.append('folder_path', folder);
    fd.append('visibility', vis);

    fetch('api/folder-visibility.php?action=update_folder', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        btn.disabled = false;
        if (data.success) {
          showToast(data.message || `Folder updated to ${visLabel}!`);

          // Update folder card header badge
          const card = document.querySelector(`.folder-item-card[data-folder="${CSS.escape(folder)}"]`);
          if (card) {
            const badge = card.querySelector('.folder-status-badge');
            if (badge) {
              badge.className = 'badge border small folder-status-badge py-1 px-2 ' + 
                (vis === 2 ? 'bg-success-subtle text-success' : (vis === 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-dark'));
              badge.textContent = (vis === 2 ? '🌍 All Public' : (vis === 0 ? '🔒 All Private' : '🔗 All Unlisted'));
            }
            // Update all inline dropdowns inside this folder
            card.querySelectorAll('.inline-vis-select').forEach(sel => {
              sel.value = vis;
            });
          }

          // Update any visible table rows in Manage Assets table belonging to this folder
          document.querySelectorAll(`.asset-row[data-folder^="${CSS.escape(folder)}"]`).forEach(row => {
            const visBadge = row.querySelector('td:nth-child(8) .badge, .badge[title]');
            if (visBadge) {
              visBadge.className = 'badge border ' + (vis === 2 ? 'bg-success-subtle text-success' : (vis === 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning'));
              visBadge.setAttribute('title', visLabel);
              const icon = visBadge.querySelector('i');
              if (icon) icon.className = 'bi ' + (vis === 2 ? 'bi-globe2' : (vis === 0 ? 'bi-lock-fill' : 'bi-link-45deg'));
              const txt = visBadge.querySelector('span');
              if (txt) txt.textContent = visLabel;
            }
          });
        } else {
          showToast(data.error || 'Failed to update folder', false);
        }
      })
      .catch(err => {
        btn.disabled = false;
        showToast('Error: ' + err.message, false);
      });
  });
});

// 2. Chevron Rotation & Collapse Animation
document.querySelectorAll('.folder-collapse-body').forEach(collapseEl => {
  collapseEl.addEventListener('show.bs.collapse', () => {
    const card = collapseEl.closest('.folder-item-card');
    card?.querySelector('.folder-chevron')?.classList.add('rotate-down');
    card?.querySelector('.folder-header-row')?.classList.add('bg-light');
  });
  collapseEl.addEventListener('hide.bs.collapse', () => {
    const card = collapseEl.closest('.folder-item-card');
    card?.querySelector('.folder-chevron')?.classList.remove('rotate-down');
    card?.querySelector('.folder-header-row')?.classList.remove('bg-light');
  });
});

// 3. Expand All & Collapse All Folders
document.getElementById('expandAllFoldersBtn')?.addEventListener('click', () => {
  document.querySelectorAll('.folder-collapse-body').forEach(el => {
    bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).show();
  });
});
document.getElementById('collapseAllFoldersBtn')?.addEventListener('click', () => {
  document.querySelectorAll('.folder-collapse-body').forEach(el => {
    bootstrap.Collapse.getOrCreateInstance(el, { toggle: false }).hide();
  });
});

// 4. Real-Time Folder Search Filtering
document.getElementById('folderSearchInput')?.addEventListener('input', (e) => {
  const term = e.target.value.toLowerCase().trim();
  document.querySelectorAll('.folder-item-card').forEach(card => {
    const searchStr = card.getAttribute('data-search') || '';
    card.style.display = (!term || searchStr.includes(term)) ? '' : 'none';
  });
});

// 5. Individual File Inline Visibility Dropdown
document.querySelectorAll('.inline-vis-select').forEach(sel => {
  sel.addEventListener('change', () => {
    const assetId = sel.getAttribute('data-id');
    const folder = sel.getAttribute('data-folder');
    const newVis = parseInt(sel.value, 10);
    const visLabel = (newVis === 2) ? 'Public' : ((newVis === 0) ? 'Private' : 'Unlisted');

    sel.disabled = true;
    const fd = new FormData();
    fd.append('asset_ids[]', assetId);
    fd.append('visibility', newVis);

    fetch('api/folder-visibility.php?action=bulk_assets', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(data => {
        sel.disabled = false;
        if (data.success) {
          showToast(`File visibility set to ${visLabel}!`);

          // Recalculate parent folder status badge live
          const folderCard = document.querySelector(`.folder-item-card[data-folder="${CSS.escape(folder)}"]`);
          if (folderCard) {
            const selects = folderCard.querySelectorAll('.inline-vis-select');
            let pub = 0, unl = 0, prv = 0;
            selects.forEach(s => {
              const v = parseInt(s.value, 10);
              if (v === 2) pub++;
              else if (v === 0) prv++;
              else unl++;
            });
            const tot = selects.length;
            const badge = folderCard.querySelector('.folder-status-badge');
            if (badge) {
              if (pub === tot) {
                badge.className = 'badge border small folder-status-badge py-1 px-2 bg-success-subtle text-success';
                badge.textContent = '🌍 All Public';
              } else if (prv === tot) {
                badge.className = 'badge border small folder-status-badge py-1 px-2 bg-danger-subtle text-danger';
                badge.textContent = '🔒 All Private';
              } else if (unl === tot) {
                badge.className = 'badge border small folder-status-badge py-1 px-2 bg-warning-subtle text-dark';
                badge.textContent = '🔗 All Unlisted';
              } else {
                badge.className = 'badge border small folder-status-badge py-1 px-2 bg-info-subtle text-info';
                badge.textContent = `⚡ Mixed (${pub} Pub, ${unl} Unl, ${prv} Prv)`;
              }
            }
          }

          // Also update corresponding Manage Assets table row if visible
          document.querySelectorAll(`.asset-row[data-id="${CSS.escape(assetId)}"]`).forEach(row => {
            const b = row.querySelector('td:nth-child(8) .badge, .badge[title]');
            if (b) {
              b.className = 'badge border ' + (newVis === 2 ? 'bg-success-subtle text-success' : (newVis === 0 ? 'bg-danger-subtle text-danger' : 'bg-warning-subtle text-warning'));
              b.setAttribute('title', visLabel);
              const icon = b.querySelector('i');
              if (icon) icon.className = 'bi ' + (newVis === 2 ? 'bi-globe2' : (newVis === 0 ? 'bi-lock-fill' : 'bi-link-45deg'));
              const txt = b.querySelector('span');
              if (txt) txt.textContent = visLabel;
            }
          });
        } else {
          showToast(data.error || 'Update failed', false);
        }
      })
      .catch(err => {
        sel.disabled = false;
        showToast('Error: ' + err.message, false);
      });
  });
});

// 6. Folder Select All Checkbox (Inside Folder Table)
document.querySelectorAll('.folder-select-all').forEach(master => {
  master.addEventListener('change', () => {
    const targetId = master.getAttribute('data-target');
    const container = document.getElementById(targetId);
    if (container) {
      container.querySelectorAll('.asset-chk').forEach(c => {
        c.checked = master.checked;
      });
      updateBulkToolbar();
    }
  });
});

// 2. Checkboxes & Multi-Asset Bulk Visibility Toolbar
function updateBulkToolbar() {
  const checked = document.querySelectorAll('.asset-chk:checked');
  const count = checked.length;
  const toolbar = document.getElementById('bulkToolbar');
  const countLabel = document.getElementById('bulkCountLabel');
  const masterDesktop = document.getElementById('selectAllDesktop');

  if (toolbar && countLabel) {
    if (count > 0) {
      toolbar.classList.remove('d-none');
      countLabel.textContent = `${count} asset${count === 1 ? '' : 's'} selected`;
    } else {
      toolbar.classList.add('d-none');
    }
  }

  if (masterDesktop) {
    const all = document.querySelectorAll('.asset-chk');
    masterDesktop.checked = (all.length > 0 && checked.length === all.length);
    masterDesktop.indeterminate = (count > 0 && count < all.length);
  }
}

document.querySelectorAll('.asset-chk').forEach(chk => {
  chk.addEventListener('change', updateBulkToolbar);
});

document.getElementById('selectAllDesktop')?.addEventListener('change', (e) => {
  const isChecked = e.target.checked;
  document.querySelectorAll('.asset-chk').forEach(chk => {
    chk.checked = isChecked;
  });
  updateBulkToolbar();
});

document.getElementById('deselectAllBtn')?.addEventListener('click', () => {
  document.querySelectorAll('.asset-chk').forEach(chk => {
    chk.checked = false;
  });
  updateBulkToolbar();
});

document.getElementById('applyBulkAssetVisBtn')?.addEventListener('click', () => {
  const checked = document.querySelectorAll('.asset-chk:checked');
  const ids = Array.from(checked).map(c => c.value);
  if (ids.length === 0) return;

  const vis = parseInt(document.getElementById('bulkVisSelect').value, 10);
  const visLabel = (vis === 2) ? 'Public' : ((vis === 0) ? 'Private' : 'Unlisted');

  if (!confirm(`Apply "${visLabel}" visibility to ${ids.length} selected asset(s)?`)) return;

  const btn = document.getElementById('applyBulkAssetVisBtn');
  btn.disabled = true;

  const fd = new FormData();
  ids.forEach(id => fd.append('asset_ids[]', id));
  fd.append('visibility', vis);

  fetch('api/folder-visibility.php?action=bulk_assets', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      btn.disabled = false;
      if (data.success) {
        showToast(data.message || `Updated ${ids.length} assets to ${visLabel}!`);
        setTimeout(() => window.location.reload(), 800);
      } else {
        showToast(data.error || 'Bulk update failed', false);
      }
    })
    .catch(err => {
      btn.disabled = false;
      showToast('Error: ' + err.message, false);
    });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
