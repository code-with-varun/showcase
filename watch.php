<?php
/**
 * YouTube-style Watch Page
 * Showcase Tube & Media CDN Vault
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

$assetId = $_GET['v'] ?? $_GET['id'] ?? null;
if (!$assetId) {
    header("Location: {$baseUrl}/index.php");
    exit;
}

// Fetch current asset
$stmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name, c.slug AS category_slug 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    WHERE a.id = :id OR a.slug = :slug 
    LIMIT 1
");
$stmt->execute([':id' => $assetId, ':slug' => $assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    header("Location: {$baseUrl}/index.php");
    exit;
}

// Fetch chapters if any
$chapStmt = $pdo->prepare("SELECT * FROM video_chapters WHERE asset_id = :id ORDER BY timestamp_seconds ASC");
$chapStmt->execute([':id' => $asset['id']]);
$chapters = $chapStmt->fetchAll();

// Fetch tags
$tagStmt = $pdo->prepare("SELECT tag FROM asset_tags WHERE asset_id = :id");
$tagStmt->execute([':id' => $asset['id']]);
$tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Up Next / Related videos
$relStmt = $pdo->prepare("
    SELECT a.*, c.name AS category_name 
    FROM assets a 
    LEFT JOIN categories c ON a.category_id = c.id 
    WHERE a.id != :id AND a.is_public = 1 
    ORDER BY (a.category_id = :cat) DESC, a.created_at DESC 
    LIMIT 10
");
$relStmt->execute([':id' => $asset['id'], ':cat' => $asset['category_id']]);
$related = $relStmt->fetchAll();

$isVideo = ($asset['asset_type'] === 'video');
$isImage = ($asset['asset_type'] === 'image');
$isDoc   = ($asset['asset_type'] === 'document');

$streamUrl = "{$baseUrl}/stream.php?v={$asset['id']}";
$rawCdnUrl = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
$embedTag  = "<iframe src=\"{$baseUrl}/embed.php?v={$asset['id']}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>";

$pageTitle = $asset['title'];
include __DIR__ . '/includes/header.php';

// Helper to convert timestamps in description into clickable links
function formatDescriptionTimestamps(string $desc): string {
    $escaped = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
    // Convert 01:23 or 1:23:45 to clickable link
    $pattern = '/\b(?:(\d+):)?(\d{1,2}):(\d{2})\b/';
    return preg_replace_callback($pattern, function($matches) {
        if (!empty($matches[1])) {
            $seconds = ((int)$matches[1] * 3600) + ((int)$matches[2] * 60) + (int)$matches[3];
        } else {
            $seconds = ((int)$matches[2] * 60) + (int)$matches[3];
        }
        $timeStr = $matches[0];
        return "<a href=\"#\" class=\"timestamp-link\" data-seek=\"{$seconds}\">{$timeStr}</a>";
    }, nl2br($escaped));
}
?>

<div class="watch-container">
  <div class="watch-layout">

    <div class="row g-4">
      <!-- Left Column: Video Player & Main Details -->
      <div class="col-lg-8 col-xl-9">
        
        <!-- YouTube Custom Player -->
        <div class="yt-player-container" id="ytPlayerContainer">
          <?php if ($isVideo): ?>
            <video id="mainVideo" src="<?= $streamUrl ?>" poster="<?= $asset['thumbnail_path'] ? $baseUrl . '/' . ltrim($asset['thumbnail_path'], '/') : '' ?>" playsinline preload="metadata"></video>
            
            <!-- Controls Overlay -->
            <div class="yt-controls-overlay">
              <!-- Scrubber Bar -->
              <div class="yt-progress-container">
                <div class="yt-progress-bg">
                  <div class="yt-progress-buffer"></div>
                  <div class="yt-progress-played">
                    <div class="yt-progress-scrubber"></div>
                  </div>
                </div>
                <div class="yt-scrub-tooltip">0:00</div>
              </div>

              <!-- Controls Buttons -->
              <div class="yt-controls-row">
                <div class="d-flex align-items-center gap-2">
                  <button class="yt-ctrl-btn yt-play-btn" title="Play/Pause (Space/K)">
                    <i class="bi bi-play-fill"></i>
                  </button>
                  <div class="yt-volume-wrapper">
                    <button class="yt-ctrl-btn yt-volume-btn" title="Mute (M)">
                      <i class="bi bi-volume-up-fill"></i>
                    </button>
                    <input type="range" class="form-range yt-volume-slider" min="0" max="1" step="0.05" value="1" title="Volume">
                  </div>
                  <div class="yt-time-display">0:00 / 0:00</div>
                </div>

                <div class="d-flex align-items-center gap-1">
                  <!-- Playback Speed -->
                  <select class="form-select form-select-sm yt-speed-select bg-dark text-white border-secondary" style="width: auto; font-size: 0.8rem;" title="Playback Speed">
                    <option value="0.25">0.25x</option>
                    <option value="0.5">0.5x</option>
                    <option value="0.75">0.75x</option>
                    <option value="1" selected>Normal</option>
                    <option value="1.25">1.25x</option>
                    <option value="1.5">1.5x</option>
                    <option value="1.75">1.75x</option>
                    <option value="2">2.0x</option>
                  </select>

                  <button class="yt-ctrl-btn yt-pip-btn" title="Picture in Picture">
                    <i class="bi bi-pip"></i>
                  </button>
                  <button class="yt-ctrl-btn yt-theater-btn" title="Theater Mode (T)">
                    <i class="bi bi-aspect-ratio"></i>
                  </button>
                  <button class="yt-ctrl-btn yt-fullscreen-btn" title="Fullscreen (F)">
                    <i class="bi bi-fullscreen"></i>
                  </button>
                </div>
              </div>
            </div>

          <?php elseif ($isImage): ?>
            <div class="d-flex align-items-center justify-content-center h-100 bg-black">
              <img src="<?= $rawCdnUrl ?>" alt="<?= h($asset['title']) ?>" style="max-height: 80vh; max-width: 100%; object-fit: contain;">
            </div>
          <?php elseif ($isDoc && $asset['extension'] === 'pdf'): ?>
            <iframe src="<?= $rawCdnUrl ?>" style="width: 100%; height: 75vh; border: none;"></iframe>
          <?php else: ?>
            <div class="text-center p-5 text-white">
              <div class="display-1 text-warning mb-3"><i class="bi bi-file-earmark-zip"></i></div>
              <h3><?= h($asset['file_name']) ?></h3>
              <p class="text-secondary"><?= formatBytes((float)$asset['file_size']) ?> • <?= strtoupper($asset['extension']) ?> File</p>
              <a href="<?= $rawCdnUrl ?>" download class="btn btn-danger btn-lg mt-3"><i class="bi bi-download me-2"></i>Download Asset</a>
            </div>
          <?php endif; ?>
        </div>

        <!-- Video Info & Action Bar -->
        <div class="mt-3">
          <h1 class="h4 text-white mb-2"><?= h($asset['title']) ?></h1>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-secondary">
            <div class="d-flex align-items-center gap-3">
              <div class="yt-author-avatar" style="width: 44px; height: 44px; font-size: 1.25rem;">
                <i class="bi bi-lightning-charge-fill text-warning"></i>
              </div>
              <div>
                <div class="fw-bold text-white"><?= h($asset['category_name'] ?: 'Showcase Vault') ?></div>
                <div class="small text-secondary"><?= number_format((int)$asset['views_count']) ?> views • <?= timeAgo($asset['created_at']) ?></div>
              </div>
            </div>

            <!-- Action Buttons -->
            <div class="d-flex align-items-center gap-2">
              <button class="btn btn-dark border-secondary rounded-pill px-3 d-flex align-items-center gap-2" id="likeBtn" data-id="<?= $asset['id'] ?>">
                <i class="bi bi-hand-thumbs-up"></i>
                <span id="likeCount"><?= (int)$asset['likes_count'] ?></span>
              </button>

              <button class="btn btn-dark border-secondary rounded-pill px-3 d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#shareAssetModal">
                <i class="bi bi-share"></i>
                <span>Share & Embed</span>
              </button>

              <a href="<?= $isVideo ? $streamUrl : $rawCdnUrl ?>" download class="btn btn-dark border-secondary rounded-pill px-3 d-flex align-items-center gap-2">
                <i class="bi bi-download"></i>
                <span>Download</span>
              </a>

              <?php if (isAdminLoggedIn()): ?>
                <a href="<?= $baseUrl ?>/studio.php?edit=<?= $asset['id'] ?>" class="btn btn-outline-warning rounded-pill px-3">
                  <i class="bi bi-pencil-square me-1"></i> Edit
                </a>
              <?php endif; ?>
            </div>
          </div>

          <!-- Description Box with Timestamp Links -->
          <div class="yt-desc-box" id="descBox">
            <div class="d-flex align-items-center gap-3 mb-2 small fw-bold text-white">
              <span><?= number_format((int)$asset['views_count']) ?> views</span>
              <span><?= date('M j, Y', strtotime($asset['created_at'])) ?></span>
              <span><?= formatBytes((float)$asset['file_size']) ?></span>
              <span class="badge bg-danger-subtle text-danger text-uppercase"><?= $asset['asset_type'] ?></span>
            </div>

            <div class="yt-desc-text" id="descText">
              <?= formatDescriptionTimestamps($asset['description'] ?: 'No additional description provided.') ?>
            </div>

            <?php if (!empty($tags)): ?>
              <div class="mt-3 pt-2 border-top border-secondary d-flex flex-wrap gap-1">
                <?php foreach ($tags as $t): ?>
                  <span class="badge bg-secondary-subtle text-secondary">#<?= h($t) ?></span>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <!-- Video Chapters List (if any) -->
          <?php if (!empty($chapters)): ?>
            <div class="card bg-dark border-secondary mt-3">
              <div class="card-header border-secondary text-white fw-bold d-flex align-items-center gap-2">
                <i class="bi bi-list-nested text-danger"></i> Video Chapters
              </div>
              <ul class="list-group list-group-flush">
                <?php foreach ($chapters as $chap): ?>
                  <li class="list-group-item bg-transparent text-white border-secondary d-flex align-items-center justify-content-between">
                    <span><?= h($chap['title']) ?></span>
                    <a href="#" class="btn btn-outline-info btn-sm rounded-pill font-monospace" data-seek="<?= (int)$chap['timestamp_seconds'] ?>">
                      <?= formatDuration((int)$chap['timestamp_seconds']) ?>
                    </a>
                  </li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Right Column: Up Next / Recommended List -->
      <div class="col-lg-4 col-xl-3">
        <h5 class="text-white mb-3 d-flex align-items-center justify-content-between">
          <span>Up Next</span>
          <span class="badge bg-dark border border-secondary text-secondary small">Autoplay</span>
        </h5>

        <div class="d-flex flex-column gap-3">
          <?php foreach ($related as $rel): 
            $relWatchUrl = "{$baseUrl}/watch.php?v={$rel['id']}";
            $relRawUrl = "{$baseUrl}/" . ltrim($rel['file_path'], '/');
            $relThumb = $rel['thumbnail_path'] ? "{$baseUrl}/" . ltrim($rel['thumbnail_path'], '/') : ($rel['asset_type'] === 'image' ? $relRawUrl : '');
            if (!$relThumb) {
                $relThumb = "data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' width='160' height='90' viewBox='0 0 160 90'><rect width='160' height='90' fill='%23222'/><text x='50%25' y='50%25' dominant-baseline='middle' text-anchor='middle' fill='%23aaa' font-size='16'>" . strtoupper($rel['extension']) . "</text></svg>";
            }
          ?>
            <a href="<?= $relWatchUrl ?>" class="d-flex gap-2 text-decoration-none text-white yt-card p-1 rounded" style="transition: background 0.15s ease;">
              <div style="width: 140px; aspect-ratio: 16/9; position: relative; border-radius: 8px; overflow: hidden; flex-shrink: 0; background: #222;">
                <img src="<?= $relThumb ?>" alt="<?= h($rel['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                <span class="yt-duration-badge" style="font-size: 0.65rem; padding: 1px 4px;">
                  <?= $rel['duration_seconds'] > 0 ? formatDuration((int)$rel['duration_seconds']) : strtoupper($rel['extension']) ?>
                </span>
              </div>
              <div class="min-w-0">
                <div class="fw-semibold text-truncate-2" style="font-size: 0.85rem; line-height: 1.25;"><?= h($rel['title']) ?></div>
                <div class="text-secondary small mt-1"><?= h($rel['category_name'] ?: 'Vault') ?></div>
                <div class="text-secondary" style="font-size: 0.75rem;"><?= number_format((int)$rel['views_count']) ?> views • <?= timeAgo($rel['created_at']) ?></div>
              </div>
            </a>
          <?php endforeach; ?>
        </div>
      </div>

    </div>

  </div>
</div>

<!-- Player Controller Script -->
<script src="<?= $baseUrl ?>/js/yt-player.js"></script>

<script>
// Like button interaction
const likeBtn = document.getElementById('likeBtn');
if (likeBtn) {
  likeBtn.addEventListener('click', () => {
    const id = likeBtn.getAttribute('data-id');
    const formData = new FormData();
    formData.append('id', id);

    fetch('api/like.php', { method: 'POST', body: formData })
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          document.getElementById('likeCount').textContent = data.likes;
          likeBtn.classList.replace('btn-dark', 'btn-danger');
          showToast('Thank you for liking this asset!');
        } else if (data.alreadyLiked) {
          showToast('You already liked this video!');
        }
      });
  });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
