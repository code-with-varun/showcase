<?php
/**
 * Shared Footer & Modals Template (Light Theme)
 */
?>
  </main>

  <!-- Global CDN & Embed Hub Modal -->
  <div class="modal fade" id="cdnHubModal" tabindex="-1" aria-labelledby="cdnHubModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-white text-dark border-light-subtle shadow">
        <div class="modal-header border-bottom border-light-subtle">
          <h5 class="modal-title d-flex align-items-center gap-2" id="cdnHubModalLabel">
            <i class="bi bi-broadcast text-danger"></i>
            <span>Universal CDN & Embedding Engine</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <p class="text-secondary small mb-3">
            Use these base endpoints to embed your videos, artwork, documents, and assets into external projects, portfolios, client portals, and blogs.
          </p>

          <div class="mb-3">
            <label class="form-label small text-secondary fw-semibold">Base CDN URL</label>
            <div class="input-group">
              <input type="text" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" readonly value="<?= $baseUrl ?>">
              <button class="btn btn-outline-danger copy-btn" data-copy="<?= $baseUrl ?>">Copy</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small text-secondary fw-semibold">Standard Responsive Iframe Embed Snippet</label>
            <div class="input-group">
              <textarea class="form-control bg-light text-dark border-secondary-subtle font-monospace small" rows="2" readonly><iframe src="<?= $baseUrl ?>/embed.php?v=VIDEO_ID" width="100%" height="450" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe></textarea>
              <button class="btn btn-outline-danger copy-btn" data-copy='<iframe src="<?= $baseUrl ?>/embed.php?v=VIDEO_ID" width="100%" height="450" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>'>Copy</button>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small text-secondary fw-semibold">High-Speed Partial-Content Streaming URL</label>
            <div class="input-group">
              <input type="text" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" readonly value="<?= $baseUrl ?>/stream.php?v=VIDEO_ID">
              <button class="btn btn-outline-danger copy-btn" data-copy="<?= $baseUrl ?>/stream.php?v=VIDEO_ID">Copy</button>
            </div>
            <div class="form-text text-secondary" style="font-size: 0.75rem;">Supports HTTP 206 Partial Content byte ranges for instant scrubbing and mobile streaming.</div>
          </div>
        </div>
        <div class="modal-footer border-top border-light-subtle">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Universal Share & Embed Modal for Specific Assets -->
  <?php if (empty($hideFooterShareModal)): ?>
  <div class="modal fade" id="shareAssetModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
      <div class="modal-content bg-white text-dark border-light-subtle shadow">
        <div class="modal-header border-bottom border-light-subtle">
          <h5 class="modal-title d-flex align-items-center gap-2">
            <i class="bi bi-share-fill text-danger"></i>
            <span id="shareModalTitle">Share & Embed Asset</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label small text-secondary fw-semibold">Direct Watch / Preview URL</label>
            <div class="input-group">
              <input type="text" id="shareWatchUrl" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" readonly>
              <button class="btn btn-outline-danger copy-btn" id="copyShareWatchBtn">Copy</button>
            </div>
          </div>

          <div class="mb-3">
            <label class="form-label small text-secondary fw-semibold">Direct CDN Link (Raw File / Stream)</label>
            <div class="input-group">
              <input type="text" id="shareCdnUrl" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" readonly>
              <button class="btn btn-outline-danger copy-btn" id="copyShareCdnBtn">Copy</button>
            </div>
          </div>

          <div class="mb-3" id="shareEmbedRow">
            <label class="form-label small text-secondary fw-semibold">HTML Embed Snippet</label>
            <div class="input-group">
              <textarea id="shareEmbedSnippet" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" rows="2" readonly></textarea>
              <button class="btn btn-outline-danger copy-btn" id="copyShareEmbedBtn">Copy</button>
            </div>
          </div>

          <div class="mb-2">
            <label class="form-label small text-secondary fw-semibold">Markdown Link</label>
            <div class="input-group">
              <input type="text" id="shareMarkdownSnippet" class="form-control bg-light text-dark border-secondary-subtle font-monospace small" readonly>
              <button class="btn btn-outline-danger copy-btn" id="copyShareMdBtn">Copy</button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- Toast Notification Container -->
  <div class="toast-container position-fixed bottom-0 end-0 p-3" style="z-index: 1090;">
    <div id="appToast" class="toast align-items-center text-bg-light border shadow" role="alert" aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body d-flex align-items-center gap-2" id="toastMessage">
          <i class="bi bi-check-circle-fill text-success"></i> Action completed!
        </div>
        <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
      </div>
    </div>
  </div>

  <!-- Scripts -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="<?= $baseUrl ?>/js/app.js"></script>
</body>
</html>
