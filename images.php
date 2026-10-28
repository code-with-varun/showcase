<?php
/**
 * Images & Motion Comics Panels Gallery
 * Showcase Tube & Media CDN Vault
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

$stmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    WHERE a.asset_type = 'image' AND a.is_public = 1 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$images = $stmt->fetchAll();

$pageTitle = "Images & Comic Panels Gallery";
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
    <div>
      <h2 class="h4 text-white mb-1"><i class="bi bi-images text-info me-2"></i>Images & Artwork Panels</h2>
      <p class="text-secondary small mb-0">High-resolution motion comic art, screenshots, and graphics with instant CDN links.</p>
    </div>
    <div class="d-flex gap-2">
      <span class="badge bg-dark border border-secondary text-light align-self-center"><?= count($images) ?> Images</span>
      <?php if (isAdminLoggedIn()): ?>
        <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-info btn-sm"><i class="bi bi-upload me-1"></i> Upload Image</a>
      <?php endif; ?>
    </div>
  </div>

  <?php if (empty($images)): ?>
    <div class="text-center py-5 my-5">
      <div class="display-1 text-secondary mb-3"><i class="bi bi-card-image"></i></div>
      <h4 class="text-white">No Images Found</h4>
      <p class="text-secondary">Drop image files into <code>assets/images/</code> and run Sync Filesystem.</p>
    </div>
  <?php else: ?>
    <div class="row row-cols-1 row-cols-sm-2 row-cols-md-3 row-cols-lg-4 g-3">
      <?php foreach ($images as $img): 
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
          <div class="card bg-dark border-secondary h-100 overflow-hidden shadow-sm" style="transition: transform 0.2s ease;">
            <div class="position-relative" style="aspect-ratio: 16/9; background: #000; overflow: hidden; cursor: pointer;" onclick="openImageLightbox('<?= $rawUrl ?>', '<?= addslashes(h($img['title'])) ?>', '<?= formatBytes((float)$img['file_size']) ?>')">
              <img src="<?= $rawUrl ?>" alt="<?= h($img['title']) ?>" class="w-100 h-100" style="object-fit: cover; transition: transform 0.3s ease;">
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
</div>

<!-- Image Lightbox Modal -->
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
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
