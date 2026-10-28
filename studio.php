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

    <!-- Quick Sync CTA -->
    <button class="btn btn-danger d-flex align-items-center gap-2 px-3 py-2 fw-semibold" id="studioSyncBtn">
      <i class="bi bi-arrow-repeat"></i>
      <span>Sync Filesystem / FTP Drops</span>
    </button>
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
      <ul class="nav nav-tabs card-header-tabs border-0" id="uploadTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active text-dark fw-semibold border-bottom-0" id="web-upload-tab" data-bs-toggle="tab" data-bs-target="#web-upload" type="button" role="tab">
            <i class="bi bi-cloud-arrow-up-fill text-danger me-1"></i> Web UI Uploader
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link text-secondary fw-semibold border-0" id="ftp-sync-tab" data-bs-toggle="tab" data-bs-target="#ftp-sync" type="button" role="tab">
            <i class="bi bi-folder-symlink-fill text-success me-1"></i> Large File Drop & FTP Sync
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

        <?php if ($typeFilter || $sourceFilter || $projectFilter || $searchFilter): ?>
          <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-secondary btn-sm" title="Clear Filters">
            <i class="bi bi-x-lg"></i>
          </a>
        <?php endif; ?>
      </form>
    </div>

    <div class="table-responsive">
      <table class="table table-hover mb-0 align-middle">
        <thead class="table-light">
          <tr class="text-secondary small text-uppercase">
            <th scope="col" style="width: 70px;">Asset</th>
            <th scope="col">Title & ID</th>
            <th scope="col">Category</th>
            <th scope="col">Project</th>
            <th scope="col">Size</th>
            <th scope="col">Source</th>
            <th scope="col">Views</th>
            <th scope="col">Date</th>
            <th scope="col" class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($assetsList)): ?>
            <tr>
              <td colspan="9" class="text-center py-4 text-secondary">
                No assets found matching the criteria. Use the uploader or run sync above.
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($assetsList as $asset): 
              $isVideo = ($asset['asset_type'] === 'video');
              $isImage = ($asset['asset_type'] === 'image');
              $rawUrl  = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
              $watchUrl = "{$baseUrl}/watch.php?v={$asset['id']}";
              $streamUrl = "{$baseUrl}/stream.php?v={$asset['id']}";
              $thumb = $asset['thumbnail_path'] ? "{$baseUrl}/" . ltrim($asset['thumbnail_path'], '/') : ($isImage ? $rawUrl : '');
              if (!$thumb) {
                $thumb = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='80' height='45' viewBox='0 0 80 45'><rect width='80' height='45' fill='%23f0f0f0'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23666' font-size='10'>" . strtoupper($asset['extension']) . "</text></svg>";
              }

              $embedTag = $isVideo 
                ? "<iframe src=\"{$baseUrl}/embed.php?v={$asset['id']}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>"
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
                'id'          => $asset['id'],
                'title'       => $asset['title'],
                'description' => $asset['description'],
                'category_id' => $asset['category_id'],
                'project'     => $asset['project'],
                'duration'    => $asset['duration_seconds'],
                'is_public'   => $asset['is_public'],
                'tags'        => $assetTags,
              ]);
            ?>
              <tr>
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
                <td><span class="small"><?= number_format((int)$asset['views_count']) ?></span></td>
                <td><span class="small text-secondary"><?= timeAgo($asset['created_at']) ?></span></td>
                <td class="text-end">
                  <div class="btn-group btn-group-sm">
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
              <select name="is_public" id="editVisibility" class="form-select bg-light text-dark border-secondary-subtle">
                <option value="1">Public (Visible in Showcase Feed)</option>
                <option value="0">Unlisted (Direct Link / Embed Only)</option>
              </select>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Description (Timestamps like 01:23 will become clickable chapters)</label>
            <textarea name="description" id="editDesc" class="form-control bg-light text-dark border-secondary-subtle" rows="4"></textarea>
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
    document.getElementById('editCategory').value = asset.category_id || '';
    document.getElementById('editProject').value = asset.project || '';
    document.getElementById('editVisibility').value = asset.is_public;
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
