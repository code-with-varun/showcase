<?php
/**
 * Dedicated Playlist Overview & Tracklist Page (Light Theme)
 * Showcase Tube & Media CDN Vault
 * Fully SEO-Optimized with OpenGraph, Twitter Cards, Schema.org ItemList JSON-LD
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();
$isAdmin = (function_exists('isAdminLoggedIn') && isAdminLoggedIn());

$playlistId = trim($_GET['list'] ?? $_GET['id'] ?? $_GET['p'] ?? '');
if (!$playlistId) {
    header("Location: {$baseUrl}/playlists");
    exit;
}

// Fetch playlist by ID or slug
$stmt = $pdo->prepare("SELECT * FROM playlists WHERE id = :id OR slug = :slug LIMIT 1");
$stmt->execute([':id' => $playlistId, ':slug' => $playlistId]);
$playlist = $stmt->fetch();

if (!$playlist) {
    http_response_code(404);
    $pageTitle = "Playlist Not Found";
    include __DIR__ . '/includes/header.php';
    ?>
    <div class="container py-5 text-center">
      <div class="p-5 bg-white border border-light-subtle rounded-4 shadow-sm" style="max-width: 560px; margin: 0 auto;">
        <i class="bi bi-collection-play display-1 text-danger mb-3 d-block"></i>
        <h3 class="fw-bold text-dark">Playlist Not Found</h3>
        <p class="text-secondary small mb-4">This playlist may have been deleted, set to private, or does not exist.</p>
        <a href="<?= $baseUrl ?>/playlists" class="btn btn-danger rounded-pill px-4">
          <i class="bi bi-arrow-left me-1"></i> Browse Playlists
        </a>
      </div>
    </div>
    <?php
    include __DIR__ . '/includes/footer.php';
    exit;
}

// Privacy enforcement
$visibility = (int)$playlist['visibility'];
if ($visibility === 0 && !$isAdmin) {
    header("Location: {$baseUrl}/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}

// Fetch playlist items
$itemStmt = $pdo->prepare("
    SELECT a.id, a.title, a.slug, a.duration_seconds, a.thumbnail_path, a.file_name, a.views_count, a.created_at, a.asset_type,
           pi.id AS item_id, pi.position 
    FROM playlist_items pi 
    JOIN assets a ON pi.asset_id = a.id 
    WHERE pi.playlist_id = :pid 
    ORDER BY pi.position ASC, pi.created_at ASC
");
$itemStmt->execute([':pid' => $playlist['id']]);
$items = $itemStmt->fetchAll();

$videoCount = count($items);
$totalSeconds = (int)array_sum(array_column($items, 'duration_seconds'));

// Format total duration
function formatTotalDuration(int $sec): string {
    if ($sec <= 0) return '0 mins';
    $h = floor($sec / 3600);
    $m = floor(($sec % 3600) / 60);
    if ($h > 0) {
        return "{$h} hr " . ($m > 0 ? "{$m} min" : '');
    }
    return "{$m} mins";
}

$firstVideo = $items[0] ?? null;
$firstVideoUrl = $firstVideo 
    ? "{$baseUrl}/watch/" . ($firstVideo['slug'] ?: $firstVideo['id']) . "?list=" . ($playlist['slug'] ?: $playlist['id'])
    : "#";

$hasCover = !empty($playlist['thumbnail_path']) || ($firstVideo && !empty($firstVideo['thumbnail_path']));
$playlistCover = !empty($playlist['thumbnail_path']) 
    ? "{$baseUrl}/" . ltrim($playlist['thumbnail_path'], '/') 
    : ($firstVideo && !empty($firstVideo['thumbnail_path']) 
        ? "{$baseUrl}/" . ltrim($firstVideo['thumbnail_path'], '/') 
        : "");

$canonicalUrl = "{$baseUrl}/playlist/" . ($playlist['slug'] ?: $playlist['id']);
$visInfo = getVisibilityLabel($visibility);

// SEO Configuration
$pageTitle = $playlist['title'] . ' (Playlist)';
$seoDescription = $playlist['description'] 
    ? mb_substr(strip_tags($playlist['description']), 0, 160) 
    : "Watch the '{$playlist['title']}' playlist with {$videoCount} videos on Runit Showcase.";
$ogImage = $playlistCover;
$ogUrl = $canonicalUrl;

include __DIR__ . '/includes/header.php';
?>

<!-- Schema.org ItemList JSON-LD -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "ItemList",
  "name": <?= json_encode($playlist['title']) ?>,
  "description": <?= json_encode($seoDescription) ?>,
  "url": <?= json_encode($canonicalUrl) ?>,
  "numberOfItems": <?= $videoCount ?>,
  "itemListElement": [
    <?php foreach ($items as $idx => $it): 
      $vUrl = "{$baseUrl}/watch/" . ($it['slug'] ?: $it['id']) . "?list=" . ($playlist['slug'] ?: $playlist['id']);
    ?>
    {
      "@type": "ListItem",
      "position": <?= $idx + 1 ?>,
      "name": <?= json_encode($it['title']) ?>,
      "url": <?= json_encode($vUrl) ?>
    }<?= ($idx < $videoCount - 1) ? ',' : '' ?>
    <?php endforeach; ?>
  ]
}
</script>

<div class="container-fluid px-0">
  <div class="row g-4">
    
    <!-- LEFT COLUMN: PLAYLIST HERO & ACTION SIDEBAR -->
    <div class="col-lg-4 col-xl-3">
      <div class="card border-light-subtle shadow-sm bg-white p-3 p-xl-4 rounded-4 position-sticky" style="top: calc(var(--topbar-height) + 1rem);">
        <!-- Playlist Cover -->
        <div class="position-relative rounded-3 overflow-hidden shadow-sm mb-3" style="aspect-ratio: 16/9; background: linear-gradient(135deg, #1e1e24 0%, #2a2a36 100%);">
          <?php if ($hasCover && $playlistCover): ?>
            <img src="<?= $playlistCover ?>" alt="<?= h($playlist['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
          <?php else: ?>
            <div class="d-flex flex-column align-items-center justify-content-center h-100 text-white-50 p-3">
              <i class="bi bi-collection-play-fill display-5 text-danger opacity-75 mb-1"></i>
              <span class="small font-monospace text-uppercase" style="font-size: 0.75rem; letter-spacing: 1px;">Playlist</span>
            </div>
          <?php endif; ?>
          <div class="position-absolute bottom-0 start-0 end-0 p-2 text-white d-flex align-items-center justify-content-between" style="background: linear-gradient(transparent, rgba(0,0,0,0.85));">
            <span class="small font-monospace"><i class="bi bi-collection-play me-1"></i><?= $videoCount ?> videos</span>
            <span class="small font-monospace"><?= formatTotalDuration($totalSeconds) ?></span>
          </div>
        </div>

        <!-- Playlist Title & Details -->
        <h4 class="fw-bold text-dark mb-2"><?= h($playlist['title']) ?></h4>
        
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
          <span class="badge <?= $visInfo['badge'] ?> border small">
            <i class="bi <?= $visInfo['icon'] ?> me-1"></i><?= $visInfo['label'] ?>
          </span>
          <span class="text-secondary small">• Updated <?= timeAgo($playlist['updated_at']) ?></span>
        </div>

        <?php if (!empty($playlist['description'])): ?>
          <p class="text-secondary small mb-4" style="line-height: 1.5; white-space: pre-line;"><?= h($playlist['description']) ?></p>
        <?php endif; ?>

        <!-- Primary Action Buttons -->
        <div class="d-flex flex-column gap-2 mb-3">
          <?php if ($videoCount > 0): ?>
            <a href="<?= $firstVideoUrl ?>" class="btn btn-danger py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 shadow-sm rounded-pill">
              <i class="bi bi-play-fill fs-5"></i>
              <span>Play All</span>
            </a>
          <?php endif; ?>

          <button type="button" class="btn btn-outline-secondary py-2 fw-semibold d-flex align-items-center justify-content-center gap-2 rounded-pill copy-btn" data-copy="<?= $canonicalUrl ?>">
            <i class="bi bi-share"></i>
            <span>Share Playlist</span>
          </button>
        </div>

        <!-- Superadmin Controls -->
        <?php if ($isAdmin): ?>
          <div class="pt-3 border-top border-light-subtle d-flex flex-wrap gap-2 justify-content-between">
            <button class="btn btn-sm btn-outline-warning rounded-pill px-3 edit-playlist-meta-btn" data-id="<?= $playlist['id'] ?>" data-title="<?= h($playlist['title']) ?>" data-desc="<?= h($playlist['description'] ?? '') ?>" data-vis="<?= $playlist['visibility'] ?>">
              <i class="bi bi-pencil-square me-1"></i>Edit
            </button>
            <button class="btn btn-sm btn-outline-primary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#addVideoModal">
              <i class="bi bi-plus-lg me-1"></i>Add Videos
            </button>
            <button class="btn btn-sm btn-outline-danger rounded-pill px-3 delete-playlist-main-btn" data-id="<?= $playlist['id'] ?>" data-title="<?= h($playlist['title']) ?>">
              <i class="bi bi-trash"></i>
            </button>
          </div>
        <?php endif; ?>

      </div>
    </div>

    <!-- RIGHT COLUMN: VIDEO TRACKLIST -->
    <div class="col-lg-8 col-xl-9">
      <div class="card border-light-subtle shadow-sm bg-white rounded-4 overflow-hidden">
        <div class="card-header bg-white py-3 px-4 d-flex align-items-center justify-content-between border-bottom border-light-subtle">
          <div class="d-flex align-items-center gap-2">
            <i class="bi bi-list-nested text-danger fs-5"></i>
            <h5 class="fw-bold text-dark mb-0">Playlist Tracks</h5>
            <span class="badge bg-secondary-subtle text-dark border small ms-1"><?= $videoCount ?></span>
          </div>
          <span class="text-secondary small font-monospace">Total Runtime: <?= formatTotalDuration($totalSeconds) ?></span>
        </div>

        <?php if (empty($items)): ?>
          <div class="p-5 text-center text-secondary">
            <i class="bi bi-film display-4 d-block mb-3 text-muted"></i>
            <h5 class="text-dark">This playlist is currently empty</h5>
            <p class="small mb-3">Add videos to this playlist to begin continuous sequence playback.</p>
            <?php if ($isAdmin): ?>
              <button class="btn btn-danger rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#addVideoModal">
                <i class="bi bi-plus-circle me-1"></i> Add Videos Now
              </button>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="list-group list-group-flush" id="playlistTracksList">
            <?php foreach ($items as $idx => $it): 
              $vWatchUrl = "{$baseUrl}/watch/" . ($it['slug'] ?: $it['id']) . "?list=" . ($playlist['slug'] ?: $playlist['id']);
              $vThumb = $it['thumbnail_path'] ? "{$baseUrl}/" . ltrim($it['thumbnail_path'], '/') : "{$baseUrl}/assets/thumbnails/thumb-flowcv.png";
            ?>
              <div class="list-group-item list-group-item-action p-3 d-flex align-items-center justify-content-between gap-3 border-bottom border-light-subtle">
                <div class="d-flex align-items-center gap-3 min-w-0 flex-grow-1">
                  <!-- Position Index -->
                  <span class="text-secondary small fw-bold font-monospace" style="width: 24px; text-align: center;"><?= $idx + 1 ?></span>

                  <!-- Thumbnail -->
                  <a href="<?= $vWatchUrl ?>" style="width: 120px; aspect-ratio: 16/9; border-radius: 8px; overflow: hidden; background: #000; flex-shrink: 0; display: block; position: relative;" class="text-decoration-none">
                    <img src="<?= $vThumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                    <span class="yt-duration-badge" style="font-size: 0.7rem; bottom: 4px; right: 4px; padding: 1px 4px;">
                      <?= formatDuration((int)$it['duration_seconds']) ?>
                    </span>
                  </a>

                  <!-- Title & Meta -->
                  <div class="min-w-0 flex-grow-1">
                    <a href="<?= $vWatchUrl ?>" class="text-decoration-none text-dark">
                      <h6 class="fw-bold mb-1 text-truncate" title="<?= h($it['title']) ?>"><?= h($it['title']) ?></h6>
                    </a>
                    <div class="text-secondary small font-monospace" style="font-size: 0.75rem;">
                      <span><?= number_format((int)$it['views_count']) ?> views</span> • <span><?= timeAgo($it['created_at']) ?></span>
                    </div>
                  </div>
                </div>

                <!-- Admin Track Reorder & Actions -->
                <div class="d-flex align-items-center gap-1 flex-shrink-0">
                  <a href="<?= $vWatchUrl ?>" class="btn btn-sm btn-outline-secondary px-2" title="Play Video in Playlist">
                    <i class="bi bi-play-fill fs-6"></i>
                  </a>

                  <?php if ($isAdmin): ?>
                    <!-- Move Up -->
                    <button class="btn btn-sm btn-outline-secondary px-2 reorder-item-btn" data-playlist-id="<?= $playlist['id'] ?>" data-asset-id="<?= $it['id'] ?>" data-dir="up" title="Move Up" <?= ($idx === 0) ? 'disabled' : '' ?>>
                      <i class="bi bi-arrow-up"></i>
                    </button>
                    <!-- Move Down -->
                    <button class="btn btn-sm btn-outline-secondary px-2 reorder-item-btn" data-playlist-id="<?= $playlist['id'] ?>" data-asset-id="<?= $it['id'] ?>" data-dir="down" title="Move Down" <?= ($idx === $videoCount - 1) ? 'disabled' : '' ?>>
                      <i class="bi bi-arrow-down"></i>
                    </button>
                    <!-- Remove -->
                    <button class="btn btn-sm btn-outline-danger px-2 remove-track-btn" data-playlist-id="<?= $playlist['id'] ?>" data-asset-id="<?= $it['id'] ?>" title="Remove from Playlist">
                      <i class="bi bi-trash"></i>
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>

      </div>
    </div>

  </div>
</div>

<!-- Superadmin: Quick Add Video Modal -->
<?php if ($isAdmin): 
  $allVidsStmt = $pdo->query("
    SELECT id, title, duration_seconds, thumbnail_path 
    FROM assets 
    WHERE asset_type = 'video' 
      AND id NOT IN (SELECT asset_id FROM playlist_items WHERE playlist_id = " . $pdo->quote($playlist['id']) . ")
    ORDER BY created_at DESC
  ");
  $availableVideos = $allVidsStmt ? $allVidsStmt->fetchAll() : [];
?>
<div class="modal fade" id="addVideoModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered modal-lg">
    <div class="modal-content bg-white text-dark border-light-subtle shadow">
      <div class="modal-header border-bottom border-light-subtle">
        <h5 class="modal-title d-flex align-items-center gap-2">
          <i class="bi bi-plus-circle text-danger"></i>
          <span>Add Videos to "<?= h($playlist['title']) ?>"</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body p-3">
        <?php if (empty($availableVideos)): ?>
          <div class="p-4 text-center text-secondary">
            <i class="bi bi-check-circle display-5 text-success d-block mb-2"></i>
            All uploaded videos are already in this playlist!
          </div>
        <?php else: ?>
          <div class="input-group mb-3">
            <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
            <input type="text" id="filterAvailableVidsInput" class="form-control" placeholder="Search videos to add...">
          </div>
          <div class="d-flex flex-column gap-2" style="max-height: 400px; overflow-y: auto;" id="availableVidsContainer">
            <?php foreach ($availableVideos as $av): 
              $avThumb = $av['thumbnail_path'] ? "{$baseUrl}/" . ltrim($av['thumbnail_path'], '/') : "{$baseUrl}/assets/thumbnails/thumb-flowcv.png";
            ?>
              <div class="d-flex align-items-center justify-content-between p-2 bg-light rounded-3 border border-light-subtle available-vid-row" data-search="<?= strtolower(h($av['title'])) ?>">
                <div class="d-flex align-items-center gap-3 min-w-0">
                  <div style="width: 70px; aspect-ratio: 16/9; border-radius: 4px; overflow: hidden; background: #000; flex-shrink: 0;">
                    <img src="<?= $avThumb ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  </div>
                  <div class="min-w-0">
                    <div class="fw-bold text-dark text-truncate small"><?= h($av['title']) ?></div>
                    <span class="text-secondary font-monospace" style="font-size: 0.7rem;"><?= formatDuration((int)$av['duration_seconds']) ?></span>
                  </div>
                </div>
                <button class="btn btn-sm btn-danger px-3 quick-add-btn" data-playlist-id="<?= $playlist['id'] ?>" data-asset-id="<?= $av['id'] ?>">
                  <i class="bi bi-plus-lg me-1"></i> Add
                </button>
              </div>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      </div>
      <div class="modal-footer border-top border-light-subtle">
        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- Edit Playlist Modal -->
<div class="modal fade" id="editPlaylistModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark border-light-subtle shadow">
      <form id="editPlaylistForm">
        <input type="hidden" name="id" value="<?= $playlist['id'] ?>">
        <div class="modal-header border-bottom border-light-subtle">
          <h5 class="modal-title d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square text-warning"></i>
            <span>Edit Playlist Metadata</span>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Playlist Title</label>
            <input type="text" name="title" id="editPlTitle" class="form-control" value="<?= h($playlist['title']) ?>" required>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Description</label>
            <textarea name="description" id="editPlDesc" class="form-control" rows="3"><?= h($playlist['description'] ?? '') ?></textarea>
          </div>
          <div class="mb-3">
            <label class="form-label text-secondary small fw-bold">Visibility</label>
            <select name="visibility" id="editPlVis" class="form-select">
              <option value="1" <?= $playlist['visibility'] == 1 ? 'selected' : '' ?>>🔗 Unlisted (Anyone with link)</option>
              <option value="2" <?= $playlist['visibility'] == 2 ? 'selected' : '' ?>>🌍 Public (Visible in feed)</option>
              <option value="0" <?= $playlist['visibility'] == 0 ? 'selected' : '' ?>>🔒 Private (Admin only)</option>
            </select>
          </div>
        </div>
        <div class="modal-footer border-top border-light-subtle">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-danger btn-sm" id="saveEditPlBtn">Save Changes</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
// Filter available videos in modal
document.getElementById('filterAvailableVidsInput')?.addEventListener('input', (e) => {
  const term = e.target.value.toLowerCase().trim();
  document.querySelectorAll('.available-vid-row').forEach(row => {
    const text = row.getAttribute('data-search') || '';
    row.style.display = text.includes(term) ? 'flex' : 'none';
  });
});

// Quick Add Video to Playlist
document.querySelectorAll('.quick-add-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const pid = btn.getAttribute('data-playlist-id');
    const aid = btn.getAttribute('data-asset-id');
    const row = btn.closest('.available-vid-row');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';

    const fd = new FormData();
    fd.append('playlist_id', pid);
    fd.append('asset_id', aid);

    fetch('<?= $baseUrl ?>/api/playlists.php?action=add_item', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          showToast('Video added to playlist!');
          row.style.opacity = '0.5';
          btn.innerHTML = '<i class="bi bi-check2"></i> Added';
          setTimeout(() => window.location.reload(), 600);
        } else {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-plus-lg me-1"></i> Add';
          showToast(d.error || 'Failed to add', false);
        }
      });
  });
});

// Reorder Track (Move Up / Move Down)
document.querySelectorAll('.reorder-item-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const pid = btn.getAttribute('data-playlist-id');
    const aid = btn.getAttribute('data-asset-id');
    const dir = btn.getAttribute('data-dir');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('playlist_id', pid);
    fd.append('asset_id', aid);
    fd.append('direction', dir);

    fetch('<?= $baseUrl ?>/api/playlists.php?action=reorder', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          showToast('Track reordered!');
          setTimeout(() => window.location.reload(), 300);
        } else {
          btn.disabled = false;
          showToast(d.error || 'Reorder failed', false);
        }
      });
  });
});

// Remove Track
document.querySelectorAll('.remove-track-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    if (!confirm('Remove this video from the playlist?')) return;
    const pid = btn.getAttribute('data-playlist-id');
    const aid = btn.getAttribute('data-asset-id');
    btn.disabled = true;

    const fd = new FormData();
    fd.append('playlist_id', pid);
    fd.append('asset_id', aid);

    fetch('<?= $baseUrl ?>/api/playlists.php?action=remove_item', { method: 'POST', body: fd })
      .then(r => r.json())
      .then(d => {
        if (d.success) {
          showToast('Video removed from playlist');
          setTimeout(() => window.location.reload(), 400);
        } else {
          btn.disabled = false;
          showToast(d.error || 'Failed to remove', false);
        }
      });
  });
});

// Edit Playlist Modal Trigger & Submit
document.querySelector('.edit-playlist-meta-btn')?.addEventListener('click', () => {
  const modal = new bootstrap.Modal(document.getElementById('editPlaylistModal'));
  modal.show();
});

document.getElementById('editPlaylistForm')?.addEventListener('submit', (e) => {
  e.preventDefault();
  const fd = new FormData(e.target);
  const btn = document.getElementById('saveEditPlBtn');
  btn.disabled = true;

  fetch('<?= $baseUrl ?>/api/playlists.php?action=update', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      btn.disabled = false;
      if (d.success) {
        showToast('Playlist updated!');
        setTimeout(() => window.location.reload(), 500);
      } else {
        showToast(d.error || 'Failed to update', false);
      }
    });
});

// Delete Playlist
document.querySelector('.delete-playlist-main-btn')?.addEventListener('click', (e) => {
  const id = e.currentTarget.getAttribute('data-id');
  const title = e.currentTarget.getAttribute('data-title');
  if (!confirm(`Are you sure you want to delete playlist "${title}"? (Videos inside will not be deleted)`)) return;

  const fd = new FormData();
  fd.append('id', id);

  fetch('<?= $baseUrl ?>/api/playlists.php?action=delete', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(d => {
      if (d.success) {
        showToast('Playlist deleted');
        setTimeout(() => window.location.href = '<?= $baseUrl ?>/playlists', 600);
      } else {
        showToast(d.error || 'Failed to delete', false);
      }
    });
});
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
