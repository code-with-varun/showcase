<?php
/**
 * Standalone Iframe Embed Player
 * Suitable for embedding in external websites, portfolios, and blogs
 */

require_once __DIR__ . '/config/database.php';

$pdo = getDB();
$baseUrl = getBaseUrl();

$assetId = $_GET['v'] ?? $_GET['id'] ?? null;
if (!$assetId) {
    die("Asset parameter required.");
}

$stmt = $pdo->prepare("SELECT * FROM assets WHERE id = :id OR slug = :id LIMIT 1");
$stmt->execute([':id' => $assetId]);
$asset = $stmt->fetch();

if (!$asset) {
    die("Asset not found.");
}

$streamUrl = "{$baseUrl}/stream.php?v={$asset['id']}";
$watchUrl  = "{$baseUrl}/watch.php?v={$asset['id']}";
$autoplay  = (isset($_GET['autoplay']) && $_GET['autoplay'] == '1');
$mute      = (isset($_GET['mute']) && $_GET['mute'] == '1');
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="dark">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($asset['title']) ?> - Embed Player</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/css/youtube.css">
  <style>
    body, html {
      margin: 0;
      padding: 0;
      width: 100%;
      height: 100%;
      overflow: hidden;
      background-color: #000;
    }
    .embed-player-wrapper {
      position: relative;
      width: 100%;
      height: 100%;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #000;
    }
    .embed-watermark {
      position: absolute;
      top: 12px;
      right: 14px;
      z-index: 30;
      background: rgba(0,0,0,0.6);
      backdrop-filter: blur(4px);
      padding: 3px 8px;
      border-radius: 6px;
      color: #fff;
      text-decoration: none;
      font-size: 0.75rem;
      font-weight: 700;
      letter-spacing: 0.5px;
      transition: opacity 0.2s ease;
      opacity: 0.7;
    }
    .embed-watermark:hover {
      opacity: 1;
      color: #ff0000;
    }
  </style>
</head>
<body data-bs-theme="dark">

  <div class="embed-player-wrapper">
    <a href="<?= $watchUrl ?>" target="_blank" rel="noopener" class="embed-watermark">
      <i class="bi bi-play-fill text-danger"></i> SHOWCASE<span style="color: #ff0000;">TUBE</span>
    </a>

    <?php if ($asset['asset_type'] === 'video'): ?>
      <div class="yt-player-container h-100 w-100 rounded-0" id="ytPlayerContainer">
        <video id="mainVideo" src="<?= $streamUrl ?>" poster="<?= $asset['thumbnail_path'] ? $baseUrl . '/' . ltrim($asset['thumbnail_path'], '/') : '' ?>" playsinline <?= $autoplay ? 'autoplay' : '' ?> <?= $mute ? 'muted' : '' ?>></video>
        
        <div class="yt-controls-overlay">
          <!-- Scrubber -->
          <div class="yt-progress-container">
            <div class="yt-progress-bg">
              <div class="yt-progress-buffer"></div>
              <div class="yt-progress-played">
                <div class="yt-progress-scrubber"></div>
              </div>
            </div>
            <div class="yt-scrub-tooltip">0:00</div>
          </div>

          <!-- Bottom Controls -->
          <div class="yt-controls-row">
            <div class="d-flex align-items-center gap-2">
              <button class="yt-ctrl-btn yt-play-btn" title="Play/Pause">
                <i class="bi bi-play-fill"></i>
              </button>
              <div class="yt-volume-wrapper">
                <button class="yt-ctrl-btn yt-volume-btn" title="Mute">
                  <i class="bi bi-volume-up-fill"></i>
                </button>
                <input type="range" class="form-range yt-volume-slider" min="0" max="1" step="0.05" value="1">
              </div>
              <div class="yt-time-display">0:00 / 0:00</div>
            </div>

            <div class="d-flex align-items-center gap-1">
              <button class="yt-ctrl-btn yt-fullscreen-btn" title="Fullscreen">
                <i class="bi bi-fullscreen"></i>
              </button>
            </div>
          </div>
        </div>
      </div>
      <script src="<?= $baseUrl ?>/js/yt-player.js"></script>
    <?php elseif ($asset['asset_type'] === 'image'): ?>
      <img src="<?= $streamUrl ?>" alt="<?= h($asset['title']) ?>" style="max-width: 100%; max-height: 100%; object-fit: contain;">
    <?php else: ?>
      <div class="text-center text-white p-3">
        <h4><?= h($asset['title']) ?></h4>
        <a href="<?= $streamUrl ?>" download class="btn btn-danger btn-sm mt-2">Download File</a>
      </div>
    <?php endif; ?>
  </div>

</body>
</html>
