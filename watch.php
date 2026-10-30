<?php
/**
 * YouTube-style Watch Page (Light Theme)
 * Runit Showcase & Media CDN Vault
 * Features:
 * - HTML5 Custom Video Player with Scrubber, Speed, Theater, and Fullscreen
 * - Autoplay Countdown Overlay for Next Video
 * - Unified Like / Share / Download Action Pills
 * - Clickable Video Chapters & Timestamps
 * - Description & Copyright Info Panel (replaces comments)
 * - Privacy: 0=private(admin only), 1=unlisted(link), 2=public(feed)
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();
$isAdmin = (function_exists('isAdminLoggedIn') && isAdminLoggedIn());

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

// --- Privacy enforcement ---
// visibility: 0=private (admin only), 1=unlisted (anyone with link), 2=public (everyone)
$visibility = (int)($asset['visibility'] ?? ($asset['is_public'] >= 1 ? 2 : 0));

if ($visibility === 0 && !$isAdmin) {
    // Private: only admin can view
    header("Location: {$baseUrl}/login.php?redirect=" . urlencode($_SERVER['REQUEST_URI']));
    exit;
}
// Unlisted (visibility=1): anyone with the link can access — no redirect needed
// Public (visibility=2): open access

$isVideo = ($asset['asset_type'] === 'video');
$isImage = ($asset['asset_type'] === 'image');
$isDoc   = ($asset['asset_type'] === 'document' || $asset['asset_type'] === 'archive');
$videoExt = strtolower($asset['extension'] ?? '');
// Modern browsers only natively support MP4 (H.264/AAC), WebM (VP8/VP9), and M4V in HTML5.
// Formats like WMV, FLV, MPG, AVI require external player (e.g. VLC) or conversion to MP4.
$isWebNativeVideo = in_array($videoExt, ['mp4', 'webm', 'm4v']);

// Fetch chapters if any
$chapStmt = $pdo->prepare("SELECT * FROM video_chapters WHERE asset_id = :id ORDER BY timestamp_seconds ASC");
$chapStmt->execute([':id' => $asset['id']]);
$chapters = $chapStmt->fetchAll();

// Fetch tags
$tagStmt = $pdo->prepare("SELECT tag FROM asset_tags WHERE asset_id = :id");
$tagStmt->execute([':id' => $asset['id']]);
$tags = $tagStmt->fetchAll(PDO::FETCH_COLUMN);

// Fetch Up Next / Recommended List - only public assets for guests
$visFilter = $isAdmin ? 'a.id != :id' : 'a.id != :id AND a.visibility = 2';
if ($isVideo) {
    $relStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE $visFilter AND a.asset_type = 'video'
        ORDER BY a.created_at DESC 
        LIMIT 15
    ");
    $relStmt->execute([':id' => $asset['id']]);
    $related = $relStmt->fetchAll();
} else {
    $relStmt = $pdo->prepare("
        SELECT a.*, c.name AS category_name 
        FROM assets a 
        LEFT JOIN categories c ON a.category_id = c.id 
        WHERE $visFilter AND a.asset_type = :type 
        ORDER BY a.created_at DESC 
        LIMIT 15
    ");
    $relStmt->execute([':id' => $asset['id'], ':type' => $asset['asset_type']]);
    $related = $relStmt->fetchAll();
}

$nextVideo = !empty($related) ? $related[0] : null;
$nextVideoUrl = $nextVideo ? "{$baseUrl}/watch.php?v={$nextVideo['id']}&autoplay=1" : '';

// Check for active playlist in URL
$playlistId = trim($_GET['list'] ?? '');
$activePlaylist = null;
$playlistVideos = [];
$playlistIndex = -1;

if (!empty($playlistId)) {
    $plStmt = $pdo->prepare("SELECT * FROM playlists WHERE id = :id OR slug = :slug LIMIT 1");
    $plStmt->execute([':id' => $playlistId, ':slug' => $playlistId]);
    $activePlaylist = $plStmt->fetch();

    if ($activePlaylist) {
        $piStmt = $pdo->prepare("
            SELECT a.id, a.title, a.duration_seconds, a.thumbnail_path, pi.position 
            FROM playlist_items pi 
            JOIN assets a ON pi.asset_id = a.id 
            WHERE pi.playlist_id = :pid 
            ORDER BY pi.position ASC, pi.created_at ASC
        ");
        $piStmt->execute([':pid' => $activePlaylist['id']]);
        $playlistVideos = $piStmt->fetchAll();

        foreach ($playlistVideos as $idx => $pv) {
            if ($pv['id'] === $asset['id']) {
                $playlistIndex = $idx;
                break;
            }
        }

        // Target previous and next video in playlist
        $prevVideoUrl = '';
        if ($playlistIndex > 0 && isset($playlistVideos[$playlistIndex - 1])) {
            $prevPv = $playlistVideos[$playlistIndex - 1];
            $prevVideoUrl = "{$baseUrl}/watch/" . ($prevPv['slug'] ?: $prevPv['id']) . "?list=" . ($activePlaylist['slug'] ?: $activePlaylist['id']);
        }

        // Target next video in playlist for autoplay
        if ($playlistIndex !== -1 && isset($playlistVideos[$playlistIndex + 1])) {
            $nextPv = $playlistVideos[$playlistIndex + 1];
            $nextVideo = [
                'id' => $nextPv['id'],
                'title' => $nextPv['title'],
                'duration_seconds' => $nextPv['duration_seconds'],
                'thumbnail_path' => $nextPv['thumbnail_path'],
            ];
            $nextVideoUrl = "{$baseUrl}/watch/" . ($nextPv['slug'] ?: $nextPv['id']) . "?list=" . ($activePlaylist['slug'] ?: $activePlaylist['id']) . "&autoplay=1";
        } elseif (!empty($playlistVideos)) {
            // End of playlist: loop back to beginning
            $firstPv = $playlistVideos[0];
            $nextVideo = [
                'id' => $firstPv['id'],
                'title' => $firstPv['title'],
                'duration_seconds' => $firstPv['duration_seconds'],
                'thumbnail_path' => $firstPv['thumbnail_path'],
            ];
            $nextVideoUrl = "{$baseUrl}/watch/" . ($firstPv['slug'] ?: $firstPv['id']) . "?list=" . ($activePlaylist['slug'] ?: $activePlaylist['id']) . "&autoplay=1";
        }
    }
}

$streamUrl = "{$baseUrl}/stream/" . ($asset['slug'] ?: $asset['id']);
$rawCdnUrl = "{$baseUrl}/" . ltrim($asset['file_path'], '/');
$embedTag  = "<iframe src=\"{$baseUrl}/embed/" . ($asset['slug'] ?: $asset['id']) . "\" width=\"100%\" height=\"450\" frameborder=\"0\" allowfullscreen></iframe>";

// SEO Configurations
$pageTitle = $asset['title'];
$seoDescription = !empty($asset['description']) 
    ? mb_substr(strip_tags($asset['description']), 0, 160) 
    : "Watch '{$asset['title']}' on Runit Showcase.";
$canonicalUrl = "{$baseUrl}/watch/" . ($asset['slug'] ?: $asset['id']);
$ogUrl = $canonicalUrl;
$ogImage = $asset['thumbnail_path'] ? "{$baseUrl}/" . ltrim($asset['thumbnail_path'], '/') : "{$baseUrl}/public/Runit%20logo%20pack/app-icon-red.png";
$ogType = 'video.other';

include __DIR__ . '/includes/header.php';
?>

<!-- Schema.org VideoObject Structured Data for Google Rich Snippets -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "VideoObject",
  "name": <?= json_encode($asset['title']) ?>,
  "description": <?= json_encode($seoDescription) ?>,
  "thumbnailUrl": [<?= json_encode($ogImage) ?>],
  "uploadDate": <?= json_encode(date('c', strtotime($asset['created_at']))) ?>,
  "duration": <?= json_encode('PT' . (int)$asset['duration_seconds'] . 'S') ?>,
  "contentUrl": <?= json_encode($rawCdnUrl) ?>,
  "embedUrl": <?= json_encode("{$baseUrl}/embed/" . ($asset['slug'] ?: $asset['id'])) ?>
}
</script>
<?php
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

// Visibility badge info
$visInfo = getVisibilityLabel($visibility);
?>

<div class="watch-container">
  <div class="watch-layout">

    <div class="row g-4">
      <!-- Left Column: Video Player & Main Details -->
      <div class="col-lg-8 col-xl-9">
        
        <!-- YouTube Custom Player -->
        <div class="yt-player-container" id="ytPlayerContainer" data-asset-id="<?= $asset['id'] ?>">
          <?php if ($isVideo): ?>
            <video id="mainVideo" src="<?= $streamUrl ?>" poster="<?= $asset['thumbnail_path'] ? $baseUrl . '/' . ltrim($asset['thumbnail_path'], '/') : '' ?>" playsinline preload="auto" <?= (!empty($_GET['autoplay']) && $_GET['autoplay'] == '1') ? 'autoplay' : '' ?>></video>
            
            <!-- Seek Ripple Overlays (for double-click and button clicks) -->
            <div class="yt-seek-overlay yt-seek-overlay-left" id="seekOverlayLeft" style="display: none;">
              <div class="yt-seek-ripple">
                <i class="bi bi-arrow-counterclockwise fs-2"></i>
                <span class="fw-bold small mt-1">10 seconds</span>
              </div>
            </div>
            <div class="yt-seek-overlay yt-seek-overlay-right" id="seekOverlayRight" style="display: none;">
              <div class="yt-seek-ripple">
                <i class="bi bi-arrow-clockwise fs-2"></i>
                <span class="fw-bold small mt-1">10 seconds</span>
              </div>
            </div>

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
                    <div class="text-secondary font-monospace" style="font-size: 0.7rem;"><?= formatDuration((int)$nextVideo['duration_seconds']) ?></div>
                  </div>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" id="cancelAutoplayBtn">Cancel</button>
                  <a href="<?= $nextVideoUrl ?>" class="btn btn-light btn-sm rounded-pill px-3 fw-bold" id="playNowAutoplayBtn">Play Now</a>
                </div>
              </div>
            <?php endif; ?>

            <!-- Unsupported Format Overlay (Shows for WMV, FLV, MPG, AVI or decode errors) -->
            <div class="yt-unsupported-overlay" id="unsupportedOverlay" style="<?= $isWebNativeVideo ? 'display: none;' : 'display: flex;' ?>">
              <div class="text-center p-4" style="max-width: 580px;">
                <div class="mb-3">
                  <span class="badge bg-warning text-dark px-3 py-2 fs-6 rounded-pill">
                    <i class="bi bi-file-earmark-play me-1"></i><?= strtoupper($videoExt) ?> Legacy Video Format
                  </span>
                </div>
                <h4 class="text-white fw-bold mb-2"><?= h($asset['title']) ?></h4>
                <p class="text-white-50 small mb-4" style="line-height: 1.5;">
                  Modern web browsers (Chrome, Edge, Safari, Firefox) only support MP4 and WebM formats for direct in-browser streaming. <strong>.<?= htmlspecialchars($videoExt) ?></strong> files cannot be decoded natively by HTML5. You can download the file below to watch in <strong>VLC Media Player</strong> or <strong>Windows Media Player</strong>.
                </p>
                <div class="d-flex flex-wrap align-items-center justify-content-center gap-2">
                  <a href="<?= $rawCdnUrl ?>" download="<?= h($asset['file_name']) ?>" class="btn btn-danger btn-lg rounded-pill px-4 shadow">
                    <i class="bi bi-download me-2"></i>Download <?= strtoupper($videoExt) ?> (<?= formatBytes((float)$asset['file_size']) ?>)
                  </a>
                  <?php if (!$isWebNativeVideo): ?>
                    <button type="button" class="btn btn-outline-light btn-sm rounded-pill px-3" id="tryPlayAnywayBtn">
                      <i class="bi bi-play-circle me-1"></i>Try Browser Play
                    </button>
                  <?php endif; ?>
                </div>
              </div>
            </div>

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
                  <?php if ($activePlaylist): ?>
                    <!-- Previous Video in Playlist -->
                    <a href="<?= $prevVideoUrl ?: '#' ?>" class="yt-ctrl-btn text-decoration-none <?= empty($prevVideoUrl) ? 'opacity-25' : '' ?>" title="Previous video in playlist" style="<?= empty($prevVideoUrl) ? 'pointer-events: none;' : '' ?>">
                      <i class="bi bi-skip-start-fill"></i>
                    </a>
                  <?php endif; ?>

                  <button class="yt-ctrl-btn yt-play-btn" title="Play/Pause (Space/K)">
                    <i class="bi bi-play-fill"></i>
                  </button>

                  <?php if ($activePlaylist && !empty($nextVideoUrl)): ?>
                    <!-- Next Video in Playlist -->
                    <a href="<?= $nextVideoUrl ?>" class="yt-ctrl-btn text-decoration-none" title="Next video in playlist">
                      <i class="bi bi-skip-end-fill"></i>
                    </a>
                  <?php endif; ?>

                  <!-- Rewind 10s -->
                  <button class="yt-ctrl-btn yt-seek-btn yt-seek-back-btn" id="seekBackBtn" title="Rewind 10 seconds (J or Left Arrow)">
                    <i class="bi bi-arrow-counterclockwise"></i>
                    <span class="yt-seek-text">10</span>
                  </button>

                  <!-- Fast Forward 10s -->
                  <button class="yt-ctrl-btn yt-seek-btn yt-seek-fwd-btn" id="seekFwdBtn" title="Forward 10 seconds (L or Right Arrow)">
                    <i class="bi bi-arrow-clockwise"></i>
                    <span class="yt-seek-text">10</span>
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

          <!-- Metadata Badges: Visibility & Format -->
          <div class="d-flex flex-wrap align-items-center gap-2 mb-2">
            <?php if ($isAdmin || $visibility === VISIBILITY_UNLISTED): ?>
              <span class="badge <?= $visInfo['badge'] ?> border small px-2 py-1">
                <i class="bi <?= $visInfo['icon'] ?> me-1"></i><?= $visInfo['label'] ?>
                <?php if ($visibility === VISIBILITY_UNLISTED && !$isAdmin): ?>
                  <span class="ms-1 opacity-75">— accessible via link</span>
                <?php endif; ?>
              </span>
            <?php endif; ?>

            <?php if ($isVideo): ?>
              <?php if ($isWebNativeVideo): ?>
                <span class="badge bg-success-subtle text-success border border-success-subtle small px-2 py-1">
                  <i class="bi bi-play-circle-fill me-1"></i>Web Streamable (<?= strtoupper($videoExt) ?>)
                </span>
              <?php else: ?>
                <span class="badge bg-warning-subtle text-warning-emphasis border border-warning-subtle small px-2 py-1">
                  <i class="bi bi-info-circle-fill me-1"></i><?= strtoupper($videoExt) ?> Format (Download for VLC)
                </span>
              <?php endif; ?>
            <?php endif; ?>
          </div>

          <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 pb-3 border-bottom border-light-subtle">

            <!-- Action Pills (horizontally scrollable on mobile) -->
            <div class="yt-action-pills-scroll">
              <!-- Like / Dislike Group -->
              <div class="yt-action-pill-group">
                <button class="yt-action-pill-btn" id="likeBtn" data-id="<?= $asset['id'] ?>" title="I like this">
                  <i class="bi bi-hand-thumbs-up"></i>
                  <span id="likeCount"><?= number_format((int)$asset['likes_count'] ?: 0) ?></span>
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

              <!-- Watch Later Button -->
              <button class="yt-action-pill-btn single" id="watchLaterBtn" data-id="<?= $asset['id'] ?>" title="Save to Watch Later">
                <i class="bi bi-clock" id="watchLaterIcon"></i>
                <span id="watchLaterLabel">Save</span>
              </button>

              <!-- Download Button -->
              <a href="<?= $isVideo ? $streamUrl : $rawCdnUrl ?>" download class="yt-action-pill-btn single" title="Download">
                <i class="bi bi-download"></i>
                <span>Download</span>
              </a>

              <?php if ($isAdmin): ?>
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
              <span><?= number_format((int)$asset['views_count']) ?> views</span>
              <span>Premiered <?= date('M j, Y', strtotime($asset['created_at'])) ?></span>
              <span><?= formatBytes((float)$asset['file_size']) ?></span>
              <?php if (!empty($tags)): ?>
                <?php foreach (array_slice($tags, 0, 5) as $t): ?>
                  <span class="text-primary">#<?= h($t) ?></span>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <div class="yt-desc-text" id="descText">
              <?= formatDescriptionTimestamps($asset['description'] ?: '') ?>
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

          <!-- Copyright / License Info Section -->
          <?php 
          $copyrightInfo = trim($asset['copyright_info'] ?? '');
          if ($copyrightInfo || $isAdmin): 
          ?>
            <div class="mt-4 pt-3 border-top border-light-subtle">
              <div class="d-flex align-items-center gap-2 mb-2">
                <i class="bi bi-c-circle text-secondary"></i>
                <span class="fw-bold text-dark small">Copyright & License</span>
                <?php if ($isAdmin && !$copyrightInfo): ?>
                  <span class="badge bg-warning-subtle text-warning border border-warning-subtle small ms-2">
                    <i class="bi bi-pencil me-1"></i>No copyright info set — <a href="<?= $baseUrl ?>/studio.php?edit=<?= $asset['id'] ?>" class="text-warning text-decoration-none fw-bold">Edit</a>
                  </span>
                <?php endif; ?>
              </div>
              <?php if ($copyrightInfo): ?>
                <div class="bg-light rounded-3 p-3 text-secondary small" style="line-height: 1.6;">
                  <?= nl2br(htmlspecialchars($copyrightInfo, ENT_QUOTES, 'UTF-8')) ?>
                </div>
              <?php elseif ($isAdmin): ?>
                <div class="bg-light rounded-3 p-3 text-muted small fst-italic">
                  No copyright information available. Add copyright info via <a href="<?= $baseUrl ?>/studio.php?edit=<?= $asset['id'] ?>">Edit Asset</a>.
                </div>
              <?php endif; ?>
            </div>
          <?php endif; ?>

        </div>
      </div>

      <!-- Right Column: Up Next / Recommended List -->
      <div class="col-lg-4 col-xl-3">
        <?php if ($activePlaylist && !empty($playlistVideos)): ?>
          <!-- YouTube Playlist Queue Card -->
          <div class="card border-danger border-opacity-25 shadow-sm mb-4 bg-white overflow-hidden">
            <div class="card-header bg-danger text-white py-2 px-3 d-flex align-items-center justify-content-between">
              <div class="min-w-0">
                <div class="fw-bold text-truncate small d-flex align-items-center gap-1">
                  <i class="bi bi-collection-play-fill"></i>
                  <span><?= h($activePlaylist['title']) ?></span>
                </div>
                <div class="opacity-75" style="font-size: 0.72rem;">
                  <?= ($playlistIndex !== -1 ? ($playlistIndex + 1) : 1) ?> / <?= count($playlistVideos) ?> videos
                </div>
              </div>
              <div class="d-flex align-items-center gap-1">
                <a href="<?= $baseUrl ?>/playlist/<?= ($activePlaylist['slug'] ?: $activePlaylist['id']) ?>" class="btn btn-sm btn-outline-light rounded-pill px-2 py-0" title="View Playlist Overview" style="font-size: 0.72rem;">
                  <i class="bi bi-box-arrow-up-right me-1"></i>List
                </a>
                <span class="badge bg-white text-danger small">Playlist</span>
              </div>
            </div>
            <div class="playlist-queue-scroll p-2 d-flex flex-column gap-1" style="max-height: 360px; overflow-y: auto;">
              <?php foreach ($playlistVideos as $pIdx => $pItem): 
                $isCurrent = ($pItem['id'] === $asset['id']);
                $itemUrl = "{$baseUrl}/watch/" . ($pItem['slug'] ?: $pItem['id']) . "?list=" . ($activePlaylist['slug'] ?: $activePlaylist['id']);
                $itemThumb = $pItem['thumbnail_path'] ? "{$baseUrl}/" . ltrim($pItem['thumbnail_path'], '/') : "{$baseUrl}/assets/thumbnails/thumb-flowcv.png";
              ?>
                <a href="<?= $itemUrl ?>" class="yt-upnext-card p-1 rounded text-decoration-none <?= $isCurrent ? 'bg-danger-subtle border border-danger-subtle' : '' ?>">
                  <div class="d-flex align-items-center justify-content-center text-secondary small px-1 font-monospace" style="min-width: 22px;">
                    <?php if ($isCurrent): ?>
                      <i class="bi bi-play-fill text-danger fs-6"></i>
                    <?php else: ?>
                      <?= $pIdx + 1 ?>
                    <?php endif; ?>
                  </div>
                  <div class="yt-upnext-thumb" style="width: 100px; height: 56px; flex-shrink: 0;">
                    <img src="<?= $itemThumb ?>" alt="<?= h($pItem['title']) ?>" style="width: 100%; height: 100%; object-fit: cover;">
                    <span class="yt-duration-badge" style="font-size: 0.65rem; padding: 1px 3px;">
                      <?= formatDuration((int)$pItem['duration_seconds']) ?>
                    </span>
                  </div>
                  <div class="yt-upnext-info min-w-0">
                    <div class="yt-upnext-title <?= $isCurrent ? 'fw-bold text-danger' : 'text-dark' ?>" title="<?= h($pItem['title']) ?>">
                      <?= h($pItem['title']) ?>
                    </div>
                  </div>
                </a>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>

        <!-- Autoplay Switch -->
        <div class="d-flex align-items-center justify-content-between mb-3">
          <span class="small fw-semibold text-secondary" style="font-size: 0.9rem;">Up Next</span>
          <div class="d-flex align-items-center gap-2">
            <span class="small fw-semibold text-secondary" style="font-size: 0.78rem;">Autoplay</span>
            <div class="form-check form-switch m-0 p-0" style="min-height: auto;">
              <input class="form-check-input m-0" type="checkbox" role="switch" id="autoplayToggle" checked style="cursor: pointer; width: 36px; height: 18px;" title="Toggle Autoplay">
            </div>
          </div>
        </div>

        <!-- Recommended Videos List -->
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
                  <div class="yt-upnext-meta">
                    <?= number_format((int)$rel['views_count']) ?> views • <?= timeAgo($rel['created_at']) ?>
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
        <div class="input-group mb-2">
          <input type="text" class="form-control bg-light font-monospace small" id="shareLinkInput" value="<?= $baseUrl ?>/watch.php?v=<?= $asset['id'] ?>" readonly>
          <button class="btn btn-dark copy-btn" id="copyShareLinkBtn" data-copy="<?= $baseUrl ?>/watch.php?v=<?= $asset['id'] ?>">Copy</button>
        </div>

        <!-- Start at timestamp switch (YouTube feature) -->
        <div class="form-check form-switch mb-3 d-flex align-items-center gap-2">
          <input class="form-check-input mt-0" type="checkbox" role="switch" id="shareAtTimestampCheck" style="cursor: pointer; width: 34px; height: 18px;">
          <label class="form-check-label small fw-semibold text-dark mb-0" for="shareAtTimestampCheck" style="cursor: pointer;">
            Start at <span class="badge bg-danger text-white font-monospace ms-1" id="shareCurrentTimeLabel">0:00</span>
          </label>
        </div>

        <label class="form-label small fw-bold text-secondary">Direct CDN Stream Link</label>
        <div class="input-group mb-3">
          <input type="text" class="form-control bg-light font-monospace small" value="<?= $streamUrl ?>" readonly>
          <button class="btn btn-outline-secondary copy-btn" data-copy="<?= $streamUrl ?>">Copy</button>
        </div>

        <label class="form-label small fw-bold text-secondary">Embed iFrame</label>
        <div class="input-group">
          <textarea class="form-control bg-light font-monospace small" id="shareEmbedInput" rows="2" readonly><?= htmlspecialchars($embedTag, ENT_QUOTES) ?></textarea>
          <button class="btn btn-outline-secondary copy-btn" id="copyShareEmbedBtn" data-copy='<?= htmlspecialchars($embedTag, ENT_QUOTES) ?>'>Copy</button>
        </div>
      </div>
    </div>
  </div>
</div>

<script>
// Next video URL passed from PHP for autoplay controller
window.nextVideoUrl = <?= json_encode($nextVideoUrl) ?>;

// Dislike button toggle
const dislikeBtn = document.getElementById('dislikeBtn');
if (dislikeBtn) {
  dislikeBtn.addEventListener('click', () => {
    dislikeBtn.classList.toggle('text-danger');
    showToast('Feedback submitted');
  });
}

// Watch Later Toggle (Zero Login / Client-side localStorage)
const watchLaterBtn = document.getElementById('watchLaterBtn');
const currentAssetId = <?= json_encode($asset['id']) ?>;

function getWatchLaterList() {
  try {
    const list = JSON.parse(localStorage.getItem('runit_watch_later') || '[]');
    return Array.isArray(list) ? list : [];
  } catch (e) {
    return [];
  }
}

function updateWatchLaterButtonUI() {
  if (!watchLaterBtn) return;
  const list = getWatchLaterList();
  const isSaved = list.includes(currentAssetId);
  const icon = document.getElementById('watchLaterIcon');
  const label = document.getElementById('watchLaterLabel');
  if (isSaved) {
    if (icon) icon.className = 'bi bi-check2-circle text-danger';
    if (label) label.textContent = 'Saved';
    watchLaterBtn.classList.add('active');
  } else {
    if (icon) icon.className = 'bi bi-clock';
    if (label) label.textContent = 'Save';
    watchLaterBtn.classList.remove('active');
  }
}

if (watchLaterBtn) {
  updateWatchLaterButtonUI();
  watchLaterBtn.addEventListener('click', () => {
    let list = getWatchLaterList();
    if (list.includes(currentAssetId)) {
      list = list.filter(id => id !== currentAssetId);
      localStorage.setItem('runit_watch_later', JSON.stringify(list));
      updateWatchLaterButtonUI();
      if (typeof showToast === 'function') showToast('Removed from Watch Later');
    } else {
      list.push(currentAssetId);
      localStorage.setItem('runit_watch_later', JSON.stringify(list));
      updateWatchLaterButtonUI();
      if (typeof showToast === 'function') showToast('Saved to Watch Later!');
    }
  });
}

// Share at current timestamp handler
const shareModalEl = document.getElementById('shareAssetModal');
const timestampCheck = document.getElementById('shareAtTimestampCheck');
const shareTimeLabel = document.getElementById('shareCurrentTimeLabel');
const shareLinkInput = document.getElementById('shareLinkInput');
const copyShareLinkBtn = document.getElementById('copyShareLinkBtn');
const shareEmbedInput = document.getElementById('shareEmbedInput');
const copyShareEmbedBtn = document.getElementById('copyShareEmbedBtn');

const baseWatchUrl = <?= json_encode($baseUrl . '/watch.php?v=' . $asset['id']) ?>;
const baseEmbedTag = <?= json_encode($embedTag) ?>;

function formatTimestampDisplay(sec) {
  if (isNaN(sec) || sec < 0) return '0:00';
  const mins = Math.floor(sec / 60);
  const secs = Math.floor(sec % 60);
  return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
}

if (shareModalEl && timestampCheck) {
  shareModalEl.addEventListener('show.bs.modal', () => {
    const video = document.getElementById('mainVideo');
    const currSec = video ? Math.floor(video.currentTime || 0) : 0;
    if (shareTimeLabel) shareTimeLabel.textContent = formatTimestampDisplay(currSec);
    timestampCheck.checked = false;
    if (shareLinkInput) shareLinkInput.value = baseWatchUrl;
    if (copyShareLinkBtn) copyShareLinkBtn.setAttribute('data-copy', baseWatchUrl);
    if (shareEmbedInput) shareEmbedInput.value = baseEmbedTag;
    if (copyShareEmbedBtn) copyShareEmbedBtn.setAttribute('data-copy', baseEmbedTag);
  });

  timestampCheck.addEventListener('change', () => {
    const video = document.getElementById('mainVideo');
    const currSec = video ? Math.floor(video.currentTime || 0) : 0;
    if (timestampCheck.checked && currSec > 0) {
      const timedUrl = `${baseWatchUrl}&t=${currSec}s`;
      const timedEmbed = `<iframe src="<?= $baseUrl ?>/embed.php?v=<?= $asset['id'] ?>&t=${currSec}s" width="100%" height="450" frameborder="0" allowfullscreen></iframe>`;
      if (shareLinkInput) shareLinkInput.value = timedUrl;
      if (copyShareLinkBtn) copyShareLinkBtn.setAttribute('data-copy', timedUrl);
      if (shareEmbedInput) shareEmbedInput.value = timedEmbed;
      if (copyShareEmbedBtn) copyShareEmbedBtn.setAttribute('data-copy', timedEmbed);
    } else {
      if (shareLinkInput) shareLinkInput.value = baseWatchUrl;
      if (copyShareLinkBtn) copyShareLinkBtn.setAttribute('data-copy', baseWatchUrl);
      if (shareEmbedInput) shareEmbedInput.value = baseEmbedTag;
      if (copyShareEmbedBtn) copyShareEmbedBtn.setAttribute('data-copy', baseEmbedTag);
    }
  });
}

// Fallback handling for non-browser formats (WMV, FLV, MPG, AVI)
const mainVideoEl = document.getElementById('mainVideo');
const unsupportedOverlayEl = document.getElementById('unsupportedOverlay');
const tryPlayAnywayBtn = document.getElementById('tryPlayAnywayBtn');

if (mainVideoEl && unsupportedOverlayEl) {
  mainVideoEl.addEventListener('error', () => {
    unsupportedOverlayEl.style.display = 'flex';
  });
  if (tryPlayAnywayBtn) {
    tryPlayAnywayBtn.addEventListener('click', () => {
      unsupportedOverlayEl.style.display = 'none';
      mainVideoEl.play().catch(err => {
        unsupportedOverlayEl.style.display = 'flex';
        if (typeof showToast === 'function') {
          showToast('Browser cannot decode this video codec. Please download the file to play.');
        }
      });
    });
  }
}
</script>

<!-- Player Controller Script -->
<script src="<?= $baseUrl ?>/js/yt-player.js"></script>

<?php 
$hideFooterShareModal = true;
include __DIR__ . '/includes/footer.php'; 
?>
