<?php
/**
 * Showcase Tube - YouTube-style Media Vault & Streaming App
 * Structured into 3 clear categories:
 * 1. Videos (Video first!)
 * 2. Images & Panels
 * 3. Documents & Zips
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

// Filter: 'all', 'video', 'image', 'docs_zips'
$activeType = $_GET['type'] ?? 'all';
$searchQ    = trim($_GET['q'] ?? '');

// Build Query
$where = ['a.is_public = 1'];
$params = [];

if ($activeType === 'video') {
    $where[] = "a.asset_type = 'video'";
} elseif ($activeType === 'image') {
    $where[] = "a.asset_type = 'image'";
} elseif ($activeType === 'docs_zips' || $activeType === 'document' || $activeType === 'archive') {
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
$allAssets = $stmt->fetchAll();

// Group into the 3 clean categories: 1. Videos, 2. Images, 3. Documents & Zips
$videosList   = [];
$imagesList   = [];
$docsZipsList = [];

foreach ($allAssets as $item) {
    if ($item['asset_type'] === 'video') {
        $videosList[] = $item;
    } elseif ($item['asset_type'] === 'image') {
        $imagesList[] = $item;
    } else {
        $docsZipsList[] = $item;
    }
}

$pageTitle = $searchQ ? "Search: {$searchQ}" : match($activeType) {
    'video'     => 'Videos',
    'image'     => 'Images & Panels',
    'docs_zips' => 'Documents & Zips',
    default     => 'Home'
};

include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">

  <!-- Simple 3-Category Filter Bar -->
  <div class="yt-chip-bar mb-4">
    <a href="<?= $baseUrl ?>/index.php<?= $searchQ ? '?q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'all') ? 'active' : '' ?>">
      <i class="bi bi-grid-fill"></i> All (3 Sections)
    </a>

    <a href="<?= $baseUrl ?>/index.php?type=video<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'video') ? 'active' : '' ?>">
      <i class="bi bi-play-circle-fill text-danger"></i> 1. Videos
      <span class="badge bg-secondary ms-1"><?= count($videosList) + ($activeType !== 'all' && $activeType !== 'video' ? 0 : 0) ?></span>
    </a>

    <a href="<?= $baseUrl ?>/index.php?type=image<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'image') ? 'active' : '' ?>">
      <i class="bi bi-images text-info"></i> 2. Images
      <span class="badge bg-secondary ms-1"><?= count($imagesList) ?></span>
    </a>

    <a href="<?= $baseUrl ?>/index.php?type=docs_zips<?= $searchQ ? '&q=' . urlencode($searchQ) : '' ?>" class="yt-chip <?= ($activeType === 'docs_zips') ? 'active' : '' ?>">
      <i class="bi bi-archive-fill text-warning"></i> 3. Documents & Zips
      <span class="badge bg-secondary ms-1"><?= count($docsZipsList) ?></span>
    </a>
  </div>

  <!-- Search Results Header (if searching) -->
  <?php if ($searchQ !== ''): ?>
    <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
      <div>
        <h4 class="text-white mb-0">Search results for <span class="text-danger">"<?= h($searchQ) ?>"</span></h4>
        <small class="text-secondary"><?= count($allAssets) ?> total matching items found</small>
      </div>
      <a href="<?= $baseUrl ?>/index.php<?= $activeType !== 'all' ? '?type=' . urlencode($activeType) : '' ?>" class="btn btn-outline-secondary btn-sm">
        <i class="bi bi-x-circle me-1"></i> Clear Search
      </a>
    </div>
  <?php endif; ?>

  <!-- ====================================================================== -->
  <!-- 1. VIDEOS (VIDEO FIRST!) -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'all' || $activeType === 'video'): ?>
    <section class="mb-5" id="videosSection">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="text-white mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-play-btn-fill text-danger"></i>
          <span>Videos</span>
          <span class="badge bg-danger-subtle text-danger small"><?= count($videosList) ?></span>
        </h4>
        <?php if ($activeType === 'all' && count($videosList) > 0): ?>
          <a href="<?= $baseUrl ?>/index.php?type=video" class="text-secondary text-decoration-none small">View only videos &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if (empty($videosList)): ?>
        <div class="p-4 rounded-3 border border-secondary bg-dark text-center text-secondary">
          <i class="bi bi-camera-video display-6 d-block mb-2 text-secondary"></i>
          <div>No videos found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Upload via <a href="<?= $baseUrl ?>/studio.php" class="text-danger">Studio</a> or drop MP4/WebM files into <code>assets/videos/</code> and click Sync.</div>
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3 g-xl-4">
          <?php foreach ($videosList as $vid): 
            $watchUrl  = "{$baseUrl}/watch.php?v={$vid['id']}";
            $streamUrl = "{$baseUrl}/stream.php?v={$vid['id']}";
            $rawCdnUrl = "{$baseUrl}/" . ltrim($vid['file_path'], '/');
            $thumbUrl  = $vid['thumbnail_path'] ? "{$baseUrl}/" . ltrim($vid['thumbnail_path'], '/') : '';
            if (!$thumbUrl) {
                $thumbUrl = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='640' height='360' viewBox='0 0 640 360'><rect width='640' height='360' fill='%237f1d1d'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23ffffff' font-size='48' font-family='sans-serif'>VIDEO</text></svg>";
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
                  <div class="yt-author-avatar">
                    <i class="bi bi-play-fill text-danger"></i>
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
                          <ul class="dropdown-menu dropdown-menu-end shadow">
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
  <!-- 2. IMAGES & ARTWORK PANELS -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'all' || $activeType === 'image'): ?>
    <section class="mb-5" id="imagesSection">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="text-white mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-images text-info"></i>
          <span>Images & Artwork</span>
          <span class="badge bg-info-subtle text-info small"><?= count($imagesList) ?></span>
        </h4>
        <?php if ($activeType === 'all' && count($imagesList) > 0): ?>
          <a href="<?= $baseUrl ?>/index.php?type=image" class="text-secondary text-decoration-none small">View only images &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if (empty($imagesList)): ?>
        <div class="p-4 rounded-3 border border-secondary bg-dark text-center text-secondary">
          <i class="bi bi-image display-6 d-block mb-2 text-secondary"></i>
          <div>No images found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Drop image files into <code>assets/images/</code> or <code>assets/motion-comics/</code> and click Sync.</div>
        </div>
      <?php else: ?>
        <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
          <?php foreach ($imagesList as $img): 
            $rawUrl = "{$baseUrl}/" . ltrim($img['file_path'], '/');
            $shareData = json_encode([
              'id'          => $img['id'],
              'title'       => $img['title'],
              'watchUrl'    => $rawUrl,
              'cdnUrl'      => $rawUrl,
              'embedTag'    => "<img src=\"{$rawUrl}\" alt=\"" . h($img['title']) . "\" loading=\"lazy\" />",
              'markdownTag' => "![" . h($img['title']) . "]({$rawUrl})"
            ]);
          ?>
            <div class="col">
              <div class="card bg-dark border-secondary h-100 overflow-hidden shadow-sm">
                <div class="position-relative" style="aspect-ratio: 16/9; background: #000; overflow: hidden; cursor: pointer;" onclick="openImageLightbox('<?= $rawUrl ?>', '<?= addslashes(h($img['title'])) ?>', '<?= formatBytes((float)$img['file_size']) ?>')">
                  <img src="<?= $rawUrl ?>" alt="<?= h($img['title']) ?>" class="w-100 h-100" style="object-fit: cover;">
                  <span class="badge bg-black bg-opacity-75 text-light position-absolute bottom-0 end-0 m-2 small font-monospace">
                    <?= strtoupper($img['extension']) ?> • <?= formatBytes((float)$img['file_size']) ?>
                  </span>
                </div>
                <div class="card-body p-2 d-flex align-items-center justify-content-between">
                  <div class="text-truncate me-2">
                    <small class="fw-bold text-white d-block text-truncate" title="<?= h($img['title']) ?>"><?= h($img['title']) ?></small>
                    <small class="text-secondary" style="font-size: 0.75rem;"><?= timeAgo($img['created_at']) ?></small>
                  </div>
                  <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-secondary" data-share-asset='<?= htmlspecialchars($shareData, ENT_QUOTES) ?>' title="Share / Copy Link">
                      <i class="bi bi-share"></i>
                    </button>
                    <a href="<?= $rawUrl ?>" download class="btn btn-outline-secondary" title="Download">
                      <i class="bi bi-download"></i>
                    </a>
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
  <!-- 3. DOCUMENTS & ZIPS -->
  <!-- ====================================================================== -->
  <?php if ($activeType === 'all' || $activeType === 'docs_zips' || $activeType === 'document' || $activeType === 'archive'): ?>
    <section class="mb-5" id="docsZipsSection">
      <div class="d-flex align-items-center justify-content-between mb-3">
        <h4 class="text-white mb-0 d-flex align-items-center gap-2">
          <i class="bi bi-archive-fill text-warning"></i>
          <span>Documents & Zips</span>
          <span class="badge bg-warning-subtle text-warning small"><?= count($docsZipsList) ?></span>
        </h4>
        <?php if ($activeType === 'all' && count($docsZipsList) > 0): ?>
          <a href="<?= $baseUrl ?>/index.php?type=docs_zips" class="text-secondary text-decoration-none small">View only documents & zips &rarr;</a>
        <?php endif; ?>
      </div>

      <?php if (empty($docsZipsList)): ?>
        <div class="p-4 rounded-3 border border-secondary bg-dark text-center text-secondary">
          <i class="bi bi-folder-x display-6 d-block mb-2 text-secondary"></i>
          <div>No documents or zip bundles found <?= $searchQ ? 'matching "' . h($searchQ) . '"' : 'yet' ?>.</div>
          <div class="small mt-1">Place files in <code>assets/documents/</code> or <code>assets/archives/</code> and click Sync.</div>
        </div>
      <?php else: ?>
        <div class="card bg-dark border-secondary">
          <div class="table-responsive">
            <table class="table table-dark table-hover mb-0 align-middle">
              <thead>
                <tr class="border-secondary text-secondary small text-uppercase">
                  <th scope="col" style="width: 50px;">Format</th>
                  <th scope="col">Title & Filename</th>
                  <th scope="col">File Size</th>
                  <th scope="col">Uploaded</th>
                  <th scope="col" class="text-end">Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($docsZipsList as $doc): 
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
                  <tr class="border-secondary">
                    <td class="text-center">
                      <i class="bi <?= $iconClass ?>" style="font-size: 1.5rem;"></i>
                    </td>
                    <td>
                      <div class="fw-bold text-white"><?= h($doc['title']) ?></div>
                      <div class="small text-secondary font-monospace"><?= h($doc['file_name']) ?></div>
                    </td>
                    <td><span class="badge bg-secondary-subtle text-light"><?= formatBytes((float)$doc['file_size']) ?></span></td>
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

<!-- Lightbox Modal for Images -->
<div class="modal fade" id="imageLightboxModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-xl">
    <div class="modal-content bg-black border-secondary">
      <div class="modal-header border-secondary p-2 px-3">
        <h6 class="modal-title text-white" id="lightboxTitle">Image Preview</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0 text-center bg-black d-flex align-items-center justify-content-center" style="min-height: 60vh;">
        <img id="lightboxImg" src="" alt="Preview" style="max-width: 100%; max-height: 80vh; object-fit: contain;">
      </div>
      <div class="modal-footer border-secondary d-flex justify-content-between">
        <span class="text-secondary small" id="lightboxSize"></span>
        <div>
          <button class="btn btn-outline-info btn-sm copy-btn" id="lightboxCopyBtn" data-copy="">Copy Direct CDN Link</button>
          <a id="lightboxDownloadBtn" href="" download class="btn btn-danger btn-sm">Download High-Res</a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Inline PDF Preview Modal -->
<div class="modal fade" id="pdfPreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content bg-black">
      <div class="modal-header border-secondary p-2 px-3">
        <h6 class="modal-title text-white" id="pdfModalTitle">PDF Preview</h6>
        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-0">
        <iframe id="pdfFrame" src="" style="width: 100%; height: 100%; border: none;"></iframe>
      </div>
    </div>
  </div>
</div>

<script>
function openImageLightbox(url, title, size) {
  document.getElementById('lightboxTitle').textContent = title;
  document.getElementById('lightboxImg').src = url;
  document.getElementById('lightboxSize').textContent = 'File Size: ' + size;
  document.getElementById('lightboxCopyBtn').setAttribute('data-copy', url);
  document.getElementById('lightboxDownloadBtn').href = url;
  const modal = new bootstrap.Modal(document.getElementById('imageLightboxModal'));
  modal.show();
}

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
