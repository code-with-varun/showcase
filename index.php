<?php
/**
 * Showcase - Professional Media Vault & Streaming Application
 * Clean, focused category views:
 * - Videos (Default)
 * - Images (Project-wise: ScripVault & Motion Comics)
 * - Documents & Archives
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

// Active category: 'video' (default), 'image', 'document'
$activeType = $_GET['type'] ?? 'video';
if ($activeType === 'all' || !in_array($activeType, ['video', 'image', 'document', 'archive', 'docs_zips'])) {
    $activeType = 'video';
}
if ($activeType === 'docs_zips' || $activeType === 'archive') {
    $activeType = 'document';
}

$searchQ = trim($_GET['q'] ?? '');
$selectedProject = trim($_GET['project'] ?? '');

// Category counts for the navigation switcher
$countsStmt = $pdo->query("
    SELECT 
        SUM(CASE WHEN asset_type = 'video' THEN 1 ELSE 0 END) AS count_videos,
        SUM(CASE WHEN asset_type = 'image' THEN 1 ELSE 0 END) AS count_images,
        SUM(CASE WHEN asset_type IN ('document', 'archive') THEN 1 ELSE 0 END) AS count_docs
    FROM assets WHERE is_public = 1
");
$catCounts = $countsStmt->fetch() ?: ['count_videos' => 0, 'count_images' => 0, 'count_docs' => 0];

// Query active category assets
$where = ['a.is_public = 1'];
$params = [];

if ($activeType === 'video') {
    $where[] = "a.asset_type = 'video'";
} elseif ($activeType === 'image') {
    $where[] = "a.asset_type = 'image'";
} else {
    $where[] = "a.asset_type IN ('document', 'archive')";
}

if ($searchQ !== '') {
    $where[] = '(a.title LIKE :sq1 OR a.file_name LIKE :sq2 OR a.description LIKE :sq3)';
    $params[':sq1'] = '%' . $searchQ . '%';
    $params[':sq2'] = '%' . $searchQ . '%';
    $params[':sq3'] = '%' . $searchQ . '%';
}

$whereSql = implode(' AND ', $where);
$sql = "SELECT a.*, c.name AS category_name, c.slug AS category_slug 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE $whereSql 
        ORDER BY a.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$assetsList = $stmt->fetchAll();

// If in Images view, group assets project-wise
$imagesByProject = [];
if ($activeType === 'image') {
    foreach ($assetsList as $img) {
        $p = !empty($img['project']) ? trim($img['project']) : 'General Showcase';
        $imagesByProject[$p][] = $img;
    }
    uksort($imagesByProject, function($a, $b) {
        if ($a === 'ScripVault') return -1;
        if ($b === 'ScripVault') return 1;
        if ($a === 'Motion Comics') return -1;
        if ($b === 'Motion Comics') return 1;
        return strcasecmp($a, $b);
    });
}

$pageTitle = $searchQ ? "Search: {$searchQ}" : match($activeType) {
    'video'    => 'Videos',
    'image'    => 'Images & Artwork',
    'document' => 'Documents & Archives',
    default    => 'Videos'
};

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">

  <!-- Category Navigation Switcher (Videos, Images, Documents) -->
  <div class="yt-chip-bar mb-4">
    <a href="<?= $baseUrl ?>/index.php?type=video<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'video') ? 'active' : '' ?>">
      <i class="bi bi-play-circle-fill text-danger me-1"></i> Videos
      <span class="badge bg-secondary ms-1"><?= (int)$catCounts['count_videos'] ?></span>
    </a>

    <a href="<?= $baseUrl ?>/index.php?type=image<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'image') ? 'active' : '' ?>">
      <i class="bi bi-images text-primary me-1"></i> Images
      <span class="badge bg-secondary ms-1"><?= (int)$catCounts['count_images'] ?></span>
    </a>

    <a href="<?= $baseUrl ?>/index.php?type=document<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'document') ? 'active' : '' ?>">
      <i class="bi bi-archive-fill text-warning me-1"></i> Documents & Archives
      <span class="badge bg-secondary ms-1"><?= (int)$catCounts['count_docs'] ?></span>
    </a>
  </div>

  <!-- Search Results Header (if searching) -->
  <?php if ($searchQ !== ''): ?>
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-light-subtle">
      <div>
        <h4 class="text-dark mb-0">Search results for <span class="text-danger">"<?= h($searchQ) ?>"</span></h4>
        <small class="text-secondary"><?= count($assetsList) ?> matching items in <?= h($pageTitle) ?></small>
      </div>
      <a href="<?= $baseUrl ?>/index.php?type=<?= urlencode($activeType) ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-circle me-1"></i> Clear Search
      </a>
    </div>
  <?php endif; ?>

  <!-- ====================================================================== -->
  <!-- 1. VIDEOS VIEW -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'video'): ?>
    <section class="mb-5" id="videosSection">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="text-dark mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-play-btn-fill text-danger"></i>
          <span>Videos</span>
          <span class="badge bg-danger text-white small"><?= count($assetsList) ?></span>
        </h4>
      </div>

      <?php if (empty($assetsList)): ?>
        <div class="p-4 rounded-3 border border-light-subtle bg-white text-center text-secondary shadow-sm">
          <i class="bi bi-camera-video display-6 d-block mb-2 text-muted"></i>
          <div>No videos found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Upload via <a href="<?= $baseUrl ?>/studio.php" class="text-danger">Studio</a> or drop MP4/WebM files into <code>assets/videos/</code> and click Sync.</div>
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-xl-4">
          <?php foreach ($assetsList as $vid): 
            $watchUrl  = "{$baseUrl}/watch.php?v={$vid['id']}";
            $streamUrl = "{$baseUrl}/stream.php?v={$vid['id']}";
            $thumbUrl  = $vid['thumbnail_path'] ? "{$baseUrl}/" . ltrim($vid['thumbnail_path'], '/') : '';
            if (!$thumbUrl) {
                $thumbUrl = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'><rect width='640' height='360' fill='%23e0e0e0'/><circle cx='320' cy='180' r='48' fill='%23cc0000'/><polygon points='310,160 340,180 310,200' fill='%23ffffff'/></svg>";
            }

            $embedTag = "<iframe src=\"{$baseUrl}/embed.php?v={$vid['id']}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>";
            $shareData = json_encode([
              'id'          => $vid['id'],
              'title'       => $vid['title'],
              'watchUrl'    => $watchUrl,
              'cdnUrl'      => $streamUrl,
              'embedTag'    => $embedTag,
              'markdownTag' => "![" . h($vid['title']) . "]({$streamUrl})"
            ]);
          ?>
            <div class="col">
              <div class="yt-card">
                <a href="<?= $watchUrl ?>" class="yt-thumb-wrapper">
                  <img src="<?= $thumbUrl ?>" alt="<?= h($vid['title']) ?>" loading="lazy">
                  <!-- Hover silent video preview -->
                  <video class="hover-preview" src="<?= $streamUrl ?>" muted preload="none"></video>
                  <span class="yt-duration-badge">
                    <?= $vid['duration_seconds'] > 0 ? formatDuration((int)$vid['duration_seconds']) : formatBytes((float)$vid['file_size']) ?>
                  </span>
                  <span class="yt-type-badge bg-danger">VIDEO</span>
                </a>

                <div class="yt-card-body">
                  <div class="yt-author-avatar overflow-hidden">
                    <img src="<?= $baseUrl ?>/public/Runit%20logo%20pack/app-icon-red.png" alt="Runit" style="width: 100%; height: 100%; object-fit: cover;">
                  </div>
                  <div class="yt-card-info">
                    <a href="<?= $watchUrl ?>" class="text-decoration-none">
                      <h6 class="yt-card-title" title="<?= h($vid['title']) ?>"><?= h($vid['title']) ?></h6>
                    </a>
                    <div class="yt-card-meta">
                      <div><?= h($vid['category_name'] ?: 'Videos') ?></div>
                      <div class="d-flex align-items-center justify-content-between mt-1">
                        <span><?= number_format((int)$vid['views_count']) ?> views • <?= timeAgo($vid['created_at']) ?></span>
                        
                        <div class="dropdown">
                          <button class="btn btn-link text-secondary p-0" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="bi bi-three-dots-vertical"></i>
                          </button>
                          <ul class="dropdown-menu dropdown-menu-end shadow-sm border-light-subtle">
                            <li><a class="dropdown-item" href="<?= $watchUrl ?>"><i class="bi bi-play-btn me-2"></i>Watch Video</a></li>
                            <li><a class="dropdown-item" href="#" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>'><i class="bi bi-share me-2"></i>Share & Embed</a></li>
                            <li><a class="dropdown-item" href="<?= $streamUrl ?>" download><i class="bi bi-download me-2"></i>Download MP4</a></li>
                            <?php if (isAdminLoggedIn()): ?>
                              <li><hr class="dropdown-divider"></li>
                              <li><a class="dropdown-item text-warning" href="<?= $baseUrl ?>/studio.php?edit=<?= $vid['id'] ?>"><i class="bi bi-pencil-square me-2"></i>Edit in Studio</a></li>
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
    </section>
  <?php endif; ?>

  <!-- ====================================================================== -->
  <!-- 2. IMAGES VIEW (PROJECT-WISE) -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'image'): ?>
    <section class="mb-5" id="imagesSection">
      <div class="d-flex flex-wrap align-items-center justify-content-between gap-2 mb-3">
        <div>
          <h4 class="text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-images text-info"></i>
            <span>Images & Artwork</span>
            <span class="badge bg-info-subtle text-info border border-info-subtle small"><?= count($assetsList) ?> Total</span>
            <span class="badge bg-secondary-subtle text-dark border small"><?= count($imagesByProject) ?> Projects</span>
          </h4>
          <small class="text-secondary">Organized project-wise with high-resolution lightbox & CDN embedding.</small>
        </div>
      </div>

      <?php if (empty($assetsList)): ?>
        <div class="p-4 rounded-3 border border-light-subtle bg-white text-center text-secondary shadow-sm">
          <i class="bi bi-image display-6 d-block mb-2 text-muted"></i>
          <div>No images found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Drop image files into project folders under <code>assets/images/</code> or <code>assets/motion-comics/</code> and click Sync.</div>
        </div>
      <?php else: ?>

        <!-- Project Filter Sub-Pills -->
        <div class="d-flex flex-wrap gap-2 mb-4 align-items-center bg-light p-2 rounded-3 border border-light-subtle">
          <span class="text-secondary small fw-bold ms-1 me-1"><i class="bi bi-folder2-open text-danger me-1"></i>Projects:</span>
          
          <button type="button" class="btn btn-sm <?= (empty($selectedProject) || $selectedProject === 'all') ? 'btn-dark active' : 'btn-outline-secondary' ?> rounded-pill px-3 project-filter-pill" data-project="all" onclick="filterProjectDisplay('all')">
            <i class="bi bi-collection me-1"></i> All Projects (<?= count($assetsList) ?>)
          </button>

          <?php foreach ($imagesByProject as $pName => $pItems): 
            $pillIcon = ($pName === 'ScripVault') ? 'bi-window-stack text-primary' : 'bi-film text-danger';
            $isActive = ($selectedProject === $pName);
          ?>
            <button type="button" class="btn btn-sm <?= $isActive ? 'btn-dark active' : 'btn-outline-secondary' ?> rounded-pill px-3 project-filter-pill" data-project="<?= h($pName) ?>" onclick="filterProjectDisplay('<?= addslashes(h($pName)) ?>')">
              <i class="bi <?= $pillIcon ?> me-1"></i> <?= h($pName) ?> <span class="badge bg-white text-dark border ms-1"><?= count($pItems) ?></span>
            </button>
          <?php endforeach; ?>
        </div>

        <!-- Project Groups Container -->
        <div id="projectGroupsContainer">
          <?php foreach ($imagesByProject as $pName => $pItems): 
            $pSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $pName));
            $isScripVault = ($pName === 'ScripVault');
            $pIcon = $isScripVault ? 'bi-window-stack text-primary' : 'bi-film text-danger';
            $pAvatarBg = $isScripVault ? 'bg-primary-subtle' : 'bg-danger-subtle';
            $pSubtitle = $isScripVault 
              ? 'Financial platform screenshot gallery • Responsive UI flows & dashboards' 
              : 'Graphic novel story panels & concept artwork illustrations';
            $pBadgeText = $isScripVault ? 'Web App' : 'Storyboards & Panels';
            $pLink = "{$baseUrl}/index.php?type=image&project=" . urlencode($pName);
          ?>
            <div class="project-card-container mb-4 project-group-box" data-project-name="<?= h($pName) ?>" id="project-<?= $pSlug ?>">
              <!-- Project Header Bar -->
              <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 mb-3 border-bottom border-light-subtle">
                <div class="d-flex align-items-center gap-3">
                  <div class="project-avatar <?= $pAvatarBg ?>">
                    <i class="bi <?= $pIcon ?>"></i>
                  </div>
                  <div>
                    <div class="d-flex align-items-center gap-2">
                      <h5 class="text-dark fw-bold mb-0"><?= h($pName) ?></h5>
                      <span class="badge bg-light text-dark border small"><?= count($pItems) ?> images</span>
                      <span class="badge bg-secondary-subtle text-secondary small border"><?= $pBadgeText ?></span>
                    </div>
                    <small class="text-secondary"><?= $pSubtitle ?></small>
                  </div>
                </div>

                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-outline-secondary btn-sm copy-btn" data-copy="<?= $pLink ?>" title="Copy direct link to this project">
                    <i class="bi bi-link-45deg me-1"></i> Copy Project Link
                  </button>
                  <button type="button" class="btn btn-outline-dark btn-sm" onclick="filterProjectDisplay('<?= addslashes(h($pName)) ?>')">
                    <i class="bi bi-funnel me-1"></i> Focus Project
                  </button>
                </div>
              </div>

              <!-- Project Thumbnails Grid -->
              <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
                <?php foreach ($pItems as $idx => $img): 
                  $rawUrl = "{$baseUrl}/" . ltrim($img['file_path'], '/');
                  $shareData = json_encode([
                    'id'          => $img['id'],
                    'title'       => $img['title'],
                    'project'     => $pName,
                    'watchUrl'    => $rawUrl,
                    'cdnUrl'      => $rawUrl,
                    'embedTag'    => "<img src=\"{$rawUrl}\" alt=\"" . h($img['title']) . "\" loading=\"lazy\" />",
                    'markdownTag' => "![" . h($img['title']) . "]({$rawUrl})"
                  ]);
                ?>
                  <div class="col">
                    <div class="card bg-white border-light-subtle h-100 overflow-hidden shadow-sm image-thumb-card">
                      <div class="position-relative" style="aspect-ratio: 16/9; background: #f0f0f0; overflow: hidden; cursor: pointer;" onclick="openProjectLightbox('<?= addslashes($pName) ?>', <?= $idx ?>)">
                        <img src="<?= $rawUrl ?>" alt="<?= h($img['title']) ?>" class="w-100 h-100" style="object-fit: cover;" loading="lazy">
                        <span class="badge bg-black bg-opacity-75 text-light position-absolute bottom-0 end-0 m-2 small font-monospace">
                          <?= strtoupper($img['extension']) ?> • <?= formatBytes((float)$img['file_size']) ?>
                        </span>
                        <div class="position-absolute top-0 start-0 m-2">
                          <span class="badge bg-dark bg-opacity-50 text-white rounded-pill px-2 py-1 small">
                            #<?= $idx + 1 ?>
                          </span>
                        </div>
                      </div>
                      <div class="card-body p-2 d-flex align-items-center justify-content-between">
                        <div class="text-truncate me-2">
                          <small class="fw-bold text-dark d-block text-truncate" title="<?= h($img['title']) ?>"><?= h($img['title']) ?></small>
                          <small class="text-secondary" style="font-size: 0.75rem;"><?= timeAgo($img['created_at']) ?></small>
                        </div>
                        <div class="btn-group btn-group-sm">
                          <button class="btn btn-outline-secondary" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>' title="Share / Copy Link">
                            <i class="bi bi-share"></i>
                          </button>
                          <a href="<?= $rawUrl ?>" download class="btn btn-outline-secondary" title="Download High-Res">
                            <i class="bi bi-download"></i>
                          </a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          <?php endforeach; ?>
        </div>

      <?php endif; ?>
    </section>
  <?php endif; ?>

  <!-- ====================================================================== -->
  <!-- 3. DOCUMENTS & ARCHIVES VIEW -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'document'): ?>
    <section class="mb-5" id="docsZipsSection">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="text-dark mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-archive-fill text-warning"></i>
          <span>Documents & Archives</span>
          <span class="badge bg-warning text-dark small"><?= count($assetsList) ?></span>
        </h4>
      </div>

      <?php if (empty($assetsList)): ?>
        <div class="p-4 rounded-3 border border-light-subtle bg-white text-center text-secondary shadow-sm">
          <i class="bi bi-folder-x display-6 d-block mb-2 text-muted"></i>
          <div>No documents or archive bundles found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Place files in <code>assets/documents/</code> or <code>assets/archives/</code> and click Sync.</div>
        </div>
      <?php else: ?>
        <div class="card bg-white border-light-subtle shadow-sm">
          <div class="table-responsive">
            <table class="table table-hover mb-0 align-middle">
              <thead class="table-light">
                <tr class="text-secondary small text-uppercase">
                  <th scope="col" style="width: 50px;">Format</th>
                  <th scope="col">Title & Filename</th>
                  <th scope="col">File Size</th>
                  <th scope="col">Uploaded</th>
                  <th scope="col" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($assetsList as $doc): 
                  $isPdf = (strtolower($doc['extension']) === 'pdf');
                  $rawUrl = "{$baseUrl}/" . ltrim($doc['file_path'], '/');
                  $iconClass = match($doc['extension']) {
                    'pdf' => 'bi-file-earmark-pdf-fill text-danger',
                    'doc', 'docx' => 'bi-file-earmark-word-fill text-primary',
                    'xls', 'xlsx', 'csv' => 'bi-file-earmark-excel-fill text-success',
                    'zip', 'rar', '7z' => 'bi-file-earmark-zip-fill text-warning',
                    default => 'bi-file-earmark-text-fill text-info'
                  };
                  
                  $shareData = json_encode([
                    'id'          => $doc['id'],
                    'title'       => $doc['title'],
                    'watchUrl'    => $rawUrl,
                    'cdnUrl'      => $rawUrl,
                    'embedTag'    => $isPdf ? "<iframe src=\"{$rawUrl}\" width=\"100%\" height=\"600\"></iframe>" : "<a href=\"{$rawUrl}\" download>Download " . h($doc['title']) . "</a>",
                    'markdownTag' => "[" . h($doc['title']) . "]({$rawUrl})"
                  ]);
                ?>
                  <tr>
                    <td class="text-center">
                      <i class="bi <?= $iconClass ?>" style="font-size: 1.5rem;"></i>
                    </td>
                    <td>
                      <div class="fw-bold text-dark"><?= h($doc['title']) ?></div>
                      <div class="small text-secondary font-monospace"><?= h($doc['file_name']) ?></div>
                    </td>
                    <td><span class="badge bg-light text-dark border"><?= formatBytes((float)$doc['file_size']) ?></span></td>
                    <td><span class="text-secondary small"><?= timeAgo($doc['created_at']) ?></span></td>
                    <td class="text-end">
                      <div class="btn-group btn-group-sm">
                        <?php if ($isPdf): ?>
                          <button class="btn btn-outline-info preview-pdf-btn" data-url="<?= $rawUrl ?>" data-title="<?= h($doc['title']) ?>" title="Inline Preview">
                            <i class="bi bi-eye"></i> Preview
                          </button>
                        <?php endif; ?>
                        <button class="btn btn-outline-secondary" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>' title="Copy Link / Embed">
                          <i class="bi bi-share"></i>
                        </button>
                        <a href="<?= $rawUrl ?>" download class="btn btn-outline-danger" title="Download">
                          <i class="bi bi-download"></i>
                        </a>
                      </div>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      <?php endif; ?>
    </section>
  <?php endif; ?>

</div>

<!-- Lightbox Modal for Images with Project Gallery & Next/Prev Controls -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-white text-dark border-light-subtle shadow position-relative">
      
      <!-- Prev & Next Overlay Navigation Buttons -->
      <button type="button" class="lightbox-nav-btn lightbox-prev" id="lightboxPrevBtn" onclick="navProjectLightbox(-1)" title="Previous Image (Left Arrow)">
        <i class="bi bi-chevron-left"></i>
      </button>
      <button type="button" class="lightbox-nav-btn lightbox-next" id="lightboxNextBtn" onclick="navProjectLightbox(1)" title="Next Image (Right Arrow)">
        <i class="bi bi-chevron-right"></i>
      </button>

      <div class="modal-header border-bottom border-light-subtle p-2 px-3 d-flex align-items-center justify-content-between">
        <div class="d-flex align-items-center gap-2 text-truncate me-2">
          <span class="badge bg-primary-subtle text-primary border" id="lightboxProjectBadge">Project</span>
          <h6 class="modal-title text-dark mb-0 text-truncate" id="lightboxTitle">Image Preview</h6>
          <span class="badge bg-secondary-subtle text-dark border small" id="lightboxCounter">1 / 1</span>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>

      <div class="modal-body p-0 text-center bg-light d-flex align-items-center justify-content-center" style="min-height: 65vh; position: relative;">
        <img id="lightboxImg" src="" alt="Preview" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
      </div>

      <div class="modal-footer border-top border-light-subtle d-flex justify-content-between align-items-center">
        <span class="text-secondary small font-monospace" id="lightboxSize"></span>
        <div class="d-flex gap-2">
          <button class="btn btn-outline-info btn-sm copy-btn" id="lightboxCopyBtn" data-copy="">
            <i class="bi bi-clipboard me-1"></i> Copy Direct CDN Link
          </button>
          <a id="lightboxDownloadBtn" href="" download class="btn btn-danger btn-sm">
            <i class="bi bi-download me-1"></i> Download High-Res
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Inline PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-white">
      <div class="modal-header border-bottom border-light-subtle p-2 px-3">
        <h6 class="modal-title text-dark" id="pdfModalTitle">PDF Preview</h6>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="pdfFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
const projectGalleries = <?= json_encode($imagesByProject) ?>;
const appBaseUrl = <?= json_encode($baseUrl) ?>;
let activeGalleryName = null;
let activeGalleryIndex = 0;
let lightboxModalInstance = null;

function openProjectLightbox(pName, index) {
  if (!projectGalleries[pName] || !projectGalleries[pName][index]) return;
  activeGalleryName = pName;
  activeGalleryIndex = index;
  renderLightboxCurrent();

  if (!lightboxModalInstance) {
    lightboxModalInstance = new bootstrap.Modal(document.getElementById('imageLightboxModal'));
  }
  lightboxModalInstance.show();
}

function navProjectLightbox(direction) {
  if (!activeGalleryName || !projectGalleries[activeGalleryName]) return;
  const list = projectGalleries[activeGalleryName];
  let nextIdx = activeGalleryIndex + direction;
  if (nextIdx < 0) nextIdx = list.length - 1;
  else if (nextIdx >= list.length) nextIdx = 0;
  activeGalleryIndex = nextIdx;
  renderLightboxCurrent();
}

function renderLightboxCurrent() {
  const list = projectGalleries[activeGalleryName];
  const item = list[activeGalleryIndex];
  const fullUrl = appBaseUrl + '/' + item.file_path.replace(/^\/+/, '');

  document.getElementById('lightboxProjectBadge').textContent = activeGalleryName;
  document.getElementById('lightboxTitle').textContent = item.title;
  document.getElementById('lightboxCounter').textContent = (activeGalleryIndex + 1) + ' / ' + list.length;
  document.getElementById('lightboxImg').src = fullUrl;
  document.getElementById('lightboxSize').textContent = (item.extension || '').toUpperCase() + ' • ' + formatBytesJS(item.file_size);
  document.getElementById('lightboxCopyBtn').setAttribute('data-copy', fullUrl);
  document.getElementById('lightboxDownloadBtn').href = fullUrl;
}

function formatBytesJS(bytes) {
  if (!bytes || bytes === 0) return '0 B';
  const k = 1024;
  const sizes = ['B', 'KB', 'MB', 'GB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// Keyboard navigation in lightbox (ArrowLeft, ArrowRight)
document.addEventListener('keydown', (e) => {
  const modalEl = document.getElementById('imageLightboxModal');
  if (modalEl && modalEl.classList.contains('show')) {
    if (e.key === 'ArrowLeft') navProjectLightbox(-1);
    else if (e.key === 'ArrowRight') navProjectLightbox(1);
  }
});

// Interactive Project Filter
function filterProjectDisplay(targetProject) {
  // Update filter pills
  document.querySelectorAll('.project-filter-pill').forEach(btn => {
    const p = btn.getAttribute('data-project');
    if (p === targetProject) {
      btn.classList.add('btn-dark', 'active');
      btn.classList.remove('btn-outline-secondary');
    } else {
      btn.classList.remove('btn-dark', 'active');
      btn.classList.add('btn-outline-secondary');
    }
  });

  // Toggle project container visibility
  document.querySelectorAll('.project-group-box').forEach(box => {
    const boxProj = box.getAttribute('data-project-name');
    if (targetProject === 'all' || boxProj === targetProject) {
      box.style.display = 'block';
    } else {
      box.style.display = 'none';
    }
  });

  // Update URL without reload
  const url = new URL(window.location.href);
  if (targetProject === 'all') {
    url.searchParams.delete('project');
  } else {
    url.searchParams.set('project', targetProject);
  }
  window.history.replaceState({}, '', url.toString());
}

// Check on load if specific project is in URL
window.addEventListener('DOMContentLoaded', () => {
  const urlParams = new URLSearchParams(window.location.search);
  const initialProject = urlParams.get('project');
  if (initialProject && initialProject !== 'all') {
    filterProjectDisplay(initialProject);
  }
});

document.querySelectorAll('.preview-pdf-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const url = btn.getAttribute('data-url');
    const title = btn.getAttribute('data-title');
    document.getElementById('pdfModalTitle').textContent = title;
    document.getElementById('pdfFrame').src = url;
    const modal = new bootstrap.Modal(document.getElementById('pdfPreviewModal'));
    modal.show();
  });
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
