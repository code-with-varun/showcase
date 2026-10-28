<?php
/**
 * Documents & Archive Vault View
 * Showcase Tube & Media CDN Vault
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

// Fetch all documents and archives
$stmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    WHERE a.asset_type IN ('document', 'archive') AND a.is_public = 1 
    ORDER BY a.created_at DESC
");
$stmt->execute();
$docs = $stmt->fetchAll();

$pageTitle = "Documents & Digital Vault";
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">
  <div class="d-flex align-items-center justify-content-between mb-4 pb-2 border-bottom border-secondary">
    <div>
      <h2 class="h4 text-white mb-1"><i class="bi bi-file-earmark-text-fill text-primary me-2"></i>Documents & File Repository</h2>
      <p class="text-secondary small mb-0">PDFs, spreadsheets, presentations, and archive bundles for CDN distribution.</p>
    </div>
    <?php if (isAdminLoggedIn()): ?>
      <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-primary btn-sm"><i class="bi bi-upload me-1"></i> Upload Document</a>
    <?php endif; ?>
  </div>

  <?php if (empty($docs)): ?>
    <div class="text-center py-5 my-5">
      <div class="display-1 text-secondary mb-3"><i class="bi bi-folder2-open"></i></div>
      <h4 class="text-white">No Documents Found</h4>
      <p class="text-secondary">Place documents in <code>assets/documents/</code> and click Sync Filesystem.</p>
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
              <th scope="col">Category</th>
              <th scope="col">Uploaded</th>
              <th scope="col" class="text-end">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($docs as $doc): 
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
                <td><span class="text-secondary small"><?= h($doc['category_name'] ?: 'Documents') ?></span></td>
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
