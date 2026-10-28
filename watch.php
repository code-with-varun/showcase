<?php
/**
 * YouTube-style Watch Page (Light Theme)
 * Showcase Tube & Media CDN Vault
 * Features:
 * - HTML5 Custom Video Player with Scrubber, Speed, Theater, and Fullscreen
 * - Autoplay Countdown Overlay for Next Video
 * - Channel Header with Subscribe Button
 * - Unified Like / Dislike / Share / Download Action Pills
 * - Clickable Video Chapters & Timestamps
 * - Interactive Comments Section
 * - Up Next Sidebar strictly with Recommended VIDEOS ONLY
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

$isVideo = ($asset['asset_type'] === 'video');
$isImage = ($asset['asset_type'] === 'image');
$isDoc   = ($asset['asset_type'] === 'document' || $asset['asset_type'] === 'archive');

// Fetch chapters if any
$chapStmt = $pdo->prepare("SELECT * FROM video_chapters WHERE asset_id = :id ORDER BY timestamp_seconds ASC");
$chapStmt->execute([':id' => $asset['id']]);
$chapters = $chapStmt->fetchAll();

// Fetch tags
$tagStmt = $pdo->prepare("SELECT tag FROM asset_tags WHERE asset_id = :id");
$tagStmt->execute([':id' => $asset['id']]);
$tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Up Next / Recommended List - STRICTLY VIDEOS for videos!
if ($isVideo) {
    $relStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE a.id != :id AND a.asset_type = 'video' AND a.is_public = 1 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $relStmt->execute([':id' => $asset['id']]);
    $related = $relStmt->fetchAll();
} else {
    $relStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE a.id != :id AND a.asset_type = :type AND a.is_public = 1 
        ORDER BY a.created_at DESC 
        LIMIT 10
    ");
    $relStmt->execute([':id' => $asset['id'], ':type' => $asset['asset_type']]);
    $related = $relStmt->fetchAll();
}

$nextVideo = !empty($related) ? $related[0] : null;
$nextVideoUrl = $nextVideo ? "{$baseUrl}/watch.php?v={$nextVideo['id']}&autoplay=1" : '';

$streamUrl = "{$baseUrl}/stream.php?v={$asset['id']}";
$rawCdnUrl = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
$embedTag  = "<iframe src=\"{$baseUrl}/embed.php?v={$asset['id']}\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>";

$pageTitle = $asset['title'];
include __DIR__ . '/includes/header.php';

// Helper to convert timestamps in description into clickable links
function formatDescriptionTimestamps(string $desc): string {
    $escaped = htmlspecialchars($desc, ENT_QUOTES, 'UTF-8');
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
            
            <!-- Autoplay Countdown Overlay (End Screen) -->
            <?php if ($nextVideo): ?>
              <div class="yt-autoplay-overlay" id="autoplayOverlay" style="display: none;">
                <div class="small text-uppercase fw-bold text-white-50 mb-2">Up next in <span id="countdownSecs" class="text-white fw-bold">5</span>s</div>
                <div class="d-flex align-items-center gap-3 bg-dark bg-opacity-75 p-3 rounded-3 border border-secondary mb-3 text-start" style="max-width: 440px; width: 100%;">
                  <div style="width: 120px; aspect-ratio: 16/9; border-radius: 6px; overflow: hidden; flex-shrink: 0; background: #000;">
                    <img src="<?= $nextVideo['thumbnail_path'] ? $baseUrl . '/' . ltrim($nextVideo['thumbnail_path'], '/') : '' ?>" alt="" style="width: 100%; height: 100%; object-fit: cover;">
                  </div>
                  <div class="min-w-0">
                    <div class="fw-bold text-white text-truncate small"><?= h($nextVideo['title']) ?></div>
                    <div class="text-secondary small mt-1">Varun Akshay <i class="bi bi-patch-check-fill text-secondary"></i></div>
                    <div class="text-secondary font-monospace" style="font-size: 0.7rem;"><?= formatDuration((int)$nextVideo['duration_seconds']) ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" id="cancelAutoplayBtn">Cancel</button>
                  <a href="<?= $nextVideoUrl ?>" class="btn btn-light btn-sm rounded-pill px-3 fw-bold" id="playNowAutoplayBtn">Play Now</a>
                </div>
              </div>
            <?php endif; ?>

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
          <h1 class="h4 text-dark fw-bold mb-2"><?= h($asset['title']) ?></h1>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle">
            <!-- Channel / Author Info -->
            <div class="d-flex align-items-center gap-3">
              <div class="yt-comment-avatar" style="background: linear-gradient(135deg, #1e3c72, #2a5298); width: 44px; height: 44px; font-size: 1.1rem;">
                VA
              </div>
              <div>
                <div class="d-flex align-items-center gap-1">
                  <span class="fw-bold text-dark">Varun Akshay</span>
                  <i class="bi bi-patch-check-fill text-secondary small" title="Verified Creator"></i>
                </div>
                <div class="small text-secondary" style="font-size: 0.78rem;">14.2K subscribers</div>
              </div>
              <button class="btn btn-yt-subscribe ms-2" id="subscribeBtn">Subscribe</button>
            </div>

            <!-- YouTube Unified Action Pills -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <!-- Like / Dislike Group -->
              <div class="yt-action-pill-group">
                <button class="yt-action-pill-btn" id="likeBtn" data-id="<?= $asset['id'] ?>" title="I like this">
                  <i class="bi bi-hand-thumbs-up"></i>
                  <span id="likeCount"><?= number_format((int)$asset['likes_count'] ?: 3420) ?></span>
                </button>
                <div class="yt-action-pill-divider"></div>
                <button class="yt-action-pill-btn" id="dislikeBtn" title="I dislike this">
                  <i class="bi bi-hand-thumbs-down"></i>
                </button>
              </div>

              <!-- Share Button -->
              <button class="yt-action-pill-btn single" data-bs-toggle="modal" data-bs-target="#shareAssetModal" title="Share & Embed">
                <i class="bi bi-share"></i>
                <span>Share</span>
              </button>

              <!-- Download Button -->
              <a href="<?= $isVideo ? $streamUrl : $rawCdnUrl ?>" download class="yt-action-pill-btn single" title="Download Video">
                <i class="bi bi-download"></i>
                <span>Download</span>
              </a>

              <?php if (isAdminLoggedIn()): ?>
                <a href="<?= $baseUrl ?>/studio.php?edit=<?= $asset['id'] ?>" class="btn btn-outline-warning rounded-pill px-3 btn-sm">
                  <i class="bi bi-pencil-square me-1"></i> Edit
                </a>
              <?php endif; ?>

              <!-- More Button -->
              <button class="yt-action-pill-btn icon-only" title="More options">
                <i class="bi bi-three-dots"></i>
              </button>
            </div>
          </div>

          <!-- Description Box with Clickable Chapters -->
          <div class="yt-desc-box" id="descBox">
            <div class="d-flex flex-wrap align-items-center gap-3 mb-2 small fw-bold text-dark">
              <span><?= number_format((int)$asset['views_count'] ?: 3420) ?> views</span>
              <span>Premiered <?= date('M j, Y', strtotime($asset['created_at'])) ?></span>
              <span><?= formatBytes((float)$asset['file_size']) ?></span>
              <?php if (!empty($tags)): ?>
                <?php foreach (array_slice($tags, 0, 3) as $t): ?>
                  <span class="text-primary">#<?= h($t) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="yt-desc-text" id="descText">
              <?= formatDescriptionTimestamps($asset['description'] ?: 'High-definition video walkthrough covering end-to-end architecture, technical achievements, and key platform metrics.') ?>
            </div>

            <!-- Clickable Video Chapters within description -->
            <?php if (!empty($chapters)): ?>
              <div class="mt-3 pt-2 border-top border-light-subtle">
                <div class="fw-bold text-dark small mb-2"><i class="bi bi-list-nested text-danger me-1"></i> Chapters:</div>
                <div class="d-flex flex-column gap-1">
                  <?php foreach ($chapters as $chap): ?>
                    <div class="d-flex align-items-center gap-2">
                      <a href="#" class="timestamp-link font-monospace small" data-seek="<?= (int)$chap['timestamp_seconds'] ?>">
                        <?= formatDuration((int)$chap['timestamp_seconds']) ?>
                      </a>
                      <span class="small text-dark"><?= h($chap['title']) ?></span>
                    </div>
                  <?php endforeach; ?>
                </div>
              </div>
            <?php endif; ?>
          </div>

          <!-- Real YouTube Comments Section -->
          <div class="mt-4 pt-2">
            <div class="d-flex align-items-center gap-4 mb-4">
              <h5 class="fw-bold text-dark mb-0">24 Comments</h5>
              <button class="btn btn-link text-secondary text-decoration-none p-0 small fw-semibold">
                <i class="bi bi-filter-left fs-5 align-middle me-1"></i> Sort by
              </button>
            </div>

            <!-- Add a comment form -->
            <div class="d-flex gap-3 mb-4">
              <div class="yt-comment-avatar" style="background: #e65100; width: 38px; height: 38px;">
                U
              </div>
              <div class="flex-grow-1">
                <input type="text" class="form-control yt-comment-input" id="newCommentInput" placeholder="Add a comment...">
                <div class="d-flex justify-content-end gap-2 mt-2 d-none" id="commentActionButtons">
                  <button type="button" class="btn btn-sm btn-outline-secondary rounded-pill px-3" id="cancelCommentBtn">Cancel</button>
                  <button type="button" class="btn btn-sm btn-dark rounded-pill px-3 fw-bold" id="submitCommentBtn">Comment</button>
                </div>
              </div>
            </div>

            <!-- Comments List -->
            <div class="d-flex flex-column gap-3" id="commentsList">
              <div class="d-flex gap-3">
                <div class="yt-comment-avatar" style="background: #1976d2;">MD</div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">@marcus_dev</span>
                    <span class="text-secondary" style="font-size: 0.75rem;">2 days ago</span>
                  </div>
                  <p class="text-dark small mb-1 mt-1">The structure of this resume with FlowCV is super clean. The way you mapped full-stack competencies and KPI metrics makes a huge difference for hiring managers.</p>
                  <div class="d-flex align-items-center gap-2">
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-up"></i> <span>14</span></button>
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="yt-comment-action-btn">Reply</button>
                  </div>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="yt-comment-avatar" style="background: #7b1fa2;">ET</div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">@elena_tech</span>
                    <span class="text-secondary" style="font-size: 0.75rem;">3 days ago</span>
                  </div>
                  <p class="text-dark small mb-1 mt-1">Really appreciate the walkthrough on the ATS optimization and typography hierarchy! Bookmarked this for reference.</p>
                  <div class="d-flex align-items-center gap-2">
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-up"></i> <span>9</span></button>
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="yt-comment-action-btn">Reply</button>
                  </div>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="yt-comment-avatar" style="background: #388e3c;">DC</div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">@david_cloud</span>
                    <span class="text-secondary" style="font-size: 0.75rem;">5 days ago</span>
                  </div>
                  <p class="text-dark small mb-1 mt-1">Excellent presentation. The project portfolio breakdown is spot on for engineering lead roles. Great pacing throughout the video.</p>
                  <div class="d-flex align-items-center gap-2">
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-up"></i> <span>6</span></button>
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="yt-comment-action-btn">Reply</button>
                  </div>
                </div>
              </div>

              <div class="d-flex gap-3">
                <div class="yt-comment-avatar" style="background: #f57c00;">SC</div>
                <div>
                  <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold text-dark small">@sarah_codes</span>
                    <span class="text-secondary" style="font-size: 0.75rem;">1 week ago</span>
                  </div>
                  <p class="text-dark small mb-1 mt-1">The tip about separating core skills from cloud infrastructure makes scanning so much faster. Awesome work!</p>
                  <div class="d-flex align-items-center gap-2">
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-up"></i> <span>4</span></button>
                    <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-down"></i></button>
                    <button class="yt-comment-action-btn">Reply</button>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Right Column: Up Next / Recommended List (VIDEOS ONLY!) -->
      <div class="col-lg-4 col-xl-3">
        <!-- Top Up Next / Autoplay Header -->
        <div class="d-flex align-items-center justify-content-between mb-3">
          <div class="d-flex align-items-center gap-1">
            <button class="btn btn-sm btn-dark rounded-pill px-3 py-1 fw-semibold small">All</button>
            <button class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-secondary small">From Varun</button>
            <button class="btn btn-sm btn-light border rounded-pill px-3 py-1 text-secondary small">Related</button>
          </div>
          
          <!-- Autoplay Switch -->
          <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold text-secondary" style="font-size: 0.78rem;">Autoplay</span>
            <div class="form-check form-switch m-0 p-0" style="min-height: auto;">
              <input class="form-check-input m-0" type="checkbox" role="switch" id="autoplayToggle" checked style="cursor: pointer; width: 36px; height: 18px;" title="Toggle Autoplay">
            </div>
          </div>
        </div>

        <!-- Recommended Videos List (VIDEOS ONLY) -->
        <div class="d-flex flex-column gap-2" id="upNextList">
          <?php if (empty($related)): ?>
            <div class="text-secondary small text-center p-3 border rounded bg-white">
              <i class="bi bi-film display-6 d-block mb-1 text-muted"></i>
              No other videos in queue.
            </div>
          <?php else: ?>
            <?php foreach ($related as $index => $rel): 
              $relWatchUrl = "{$baseUrl}/watch.php?v={$rel['id']}";
              $relThumb = $rel['thumbnail_path'] ? "{$baseUrl}/" . ltrim($rel['thumbnail_path'], '/') : "{$baseUrl}/assets/thumbnails/thumb-flowcv.png";
            ?>
              <a href="<?= $relWatchUrl ?>" class="yt-upnext-card">
                <div class="yt-upnext-thumb">
                  <img src="<?= $relThumb ?>" alt="<?= h($rel['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;" loading="lazy">
                  <span class="yt-duration-badge" style="font-size: 0.7rem; padding: 1px 4px;">
                    <?= formatDuration((int)$rel['duration_seconds']) ?>
                  </span>
                </div>
                <div class="yt-upnext-info">
                  <div class="yt-upnext-title" title="<?= h($rel['title']) ?>">
                    <?= h($rel['title']) ?>
                  </div>
                  <div class="yt-upnext-channel">
                    <span>Varun Akshay</span>
                    <i class="bi bi-patch-check-fill text-secondary" style="font-size: 0.72rem;"></i>
                  </div>
                  <div class="yt-upnext-meta">
                    <?= number_format((int)$rel['views_count'] ?: 2180) ?> views • <?= timeAgo($rel['created_at']) ?>
                  </div>
                </div>
              </a>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </div>

    </div>

  </div>
</div>

<!-- Modal for Sharing Asset -->
<div class="modal fade" id="shareAssetModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content bg-white text-dark border-0 shadow">
      <div class="modal-header border-bottom border-light-subtle">
        <h5 class="modal-title fw-bold"><i class="bi bi-share text-danger me-2"></i>Share & Embed</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        <label class="form-label small fw-bold text-secondary">Direct Watch Link</label>
        <div class="input-group mb-3">
          <input type="text" class="form-control bg-light" id="shareLinkInput" value="<?= $baseUrl ?>/watch.php?v=<?= $asset['id'] ?>" readonly>
          <button class="btn btn-dark copy-btn" data-copy="<?= $baseUrl ?>/watch.php?v=<?= $asset['id'] ?>">Copy</button>
        </div>

        <label class="form-label small fw-bold text-secondary">Direct CDN Stream Link</label>
        <div class="input-group mb-3">
          <input type="text" class="form-control bg-light" value="<?= $streamUrl ?>" readonly>
          <button class="btn btn-outline-secondary copy-btn" data-copy="<?= $streamUrl ?>">Copy</button>
        </div>

        <label class="form-label small fw-bold text-secondary">Embed iFrame</label>
        <div class="input-group">
          <input type="text" class="form-control bg-light" value='<?= htmlspecialchars($embedTag, ENT_QUOTES) ?>' readonly>
          <button class="btn btn-outline-secondary copy-btn" data-copy='<?= htmlspecialchars($embedTag, ENT_QUOTES) ?>'>Copy</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Next video URL passed from PHP for autoplay controller
window.nextVideoUrl = <?= json_encode($nextVideoUrl) ?>;

// Subscribe button interaction
const subscribeBtn = document.getElementById('subscribeBtn');
if (subscribeBtn) {
  subscribeBtn.addEventListener('click', () => {
    if (subscribeBtn.classList.contains('subscribed')) {
      subscribeBtn.classList.remove('subscribed');
      subscribeBtn.textContent = 'Subscribe';
      showToast('Unsubscribed from channel');
    } else {
      subscribeBtn.classList.add('subscribed');
      subscribeBtn.innerHTML = '<i class="bi bi-bell-fill text-dark me-1"></i> Subscribed';
      showToast('Subscribed to Varun Akshay!');
    }
  });
}

// Dislike button toggle
const dislikeBtn = document.getElementById('dislikeBtn');
if (dislikeBtn) {
  dislikeBtn.addEventListener('click', () => {
    dislikeBtn.classList.toggle('text-danger');
    showToast('Feedback submitted');
  });
}

// Comment input focus & submission
const commentInput = document.getElementById('newCommentInput');
const commentActions = document.getElementById('commentActionButtons');
const cancelCommentBtn = document.getElementById('cancelCommentBtn');
const submitCommentBtn = document.getElementById('submitCommentBtn');
const commentsList = document.getElementById('commentsList');

if (commentInput && commentActions) {
  commentInput.addEventListener('focus', () => {
    commentActions.classList.remove('d-none');
  });

  cancelCommentBtn.addEventListener('click', () => {
    commentInput.value = '';
    commentActions.classList.add('d-none');
  });

  submitCommentBtn.addEventListener('click', () => {
    const text = commentInput.value.trim();
    if (!text) return;

    const newCommentEl = document.createElement('div');
    newCommentEl.className = 'd-flex gap-3 animate__animated animate__fadeIn';
    newCommentEl.innerHTML = `
      <div class="yt-comment-avatar" style="background: #e65100;">U</div>
      <div>
        <div class="d-flex align-items-center gap-2">
          <span class="fw-bold text-dark small">@you</span>
          <span class="text-secondary" style="font-size: 0.75rem;">Just now</span>
        </div>
        <p class="text-dark small mb-1 mt-1">${text.replace(/</g, "&lt;").replace(/>/g, "&gt;")}</p>
        <div class="d-flex align-items-center gap-2">
          <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-up"></i> <span>0</span></button>
          <button class="yt-comment-action-btn"><i class="bi bi-hand-thumbs-down"></i></button>
          <button class="yt-comment-action-btn">Reply</button>
        </div>
      </div>
    `;

    commentsList.prepend(newCommentEl);
    commentInput.value = '';
    commentActions.classList.add('d-none');
    showToast('Comment posted!');
  });
}
</script>

<!-- Player Controller Script -->
<script src="<?= $baseUrl ?>/js/yt-player.js"></script>

<?php include __DIR__ . '/includes/footer.php'; ?>
