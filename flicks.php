<?php
/**
 * Flicks - Modern Fullscreen Portrait Video Streamer (YouTube Shorts / Reels style)
 * Features:
 * - 9:16 Responsive mobile-first portrait video viewport
 * - Snap scrolling (up/down swipe, mouse wheel, and up/down arrow keys)
 * - Autoplay & loop when in viewport (IntersectionObserver)
 * - Tap to play/pause with visual icon feedback
 * - Sound toggle (Mute / Unmute memory across flicks)
 * - Double-tap to heart/like with animated pulse
 * - Floating share, copy link, and direct download buttons
 * - Zero-login persistence for likes in localStorage & session
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$pdo = getDB();
$baseUrl = getBaseUrl();
$isAdmin = (function_exists('isAdminLoggedIn') && isAdminLoggedIn());

$requestedSlug = trim($_GET['v'] ?? '');

// Fetch all public flicks (or all if admin)
$whereClause = $isAdmin ? '1=1' : 'visibility = 2';
$flicksStmt = $pdo->prepare("
    SELECT id, title, slug, description, copyright_info, file_path, file_name, 
           file_size, thumbnail_path, duration_seconds, views_count, likes_count, 
           visibility, created_at
    FROM assets 
    WHERE asset_type = 'video' AND is_flick = 1 AND $whereClause
    ORDER BY created_at DESC
");
$flicksStmt->execute();
$flicks = $flicksStmt->fetchAll(PDO::FETCH_ASSOC);

// If specific flick requested, reorder to put that flick first
if ($requestedSlug !== '') {
    $foundIdx = -1;
    foreach ($flicks as $idx => $f) {
        if ($f['slug'] === $requestedSlug || $f['id'] === $requestedSlug) {
            $foundIdx = $idx;
            break;
        }
    }
    if ($foundIdx > 0) {
        $target = $flicks[$foundIdx];
        unset($flicks[$foundIdx]);
        array_unshift($flicks, $target);
        $flicks = array_values($flicks);
    }
}

$firstFlick = $flicks[0] ?? null;
$pageTitle = $firstFlick ? (h($firstFlick['title']) . ' - Flicks') : 'Flicks - Short Videos';
$seoDescription = 'Watch short, engaging portrait videos on Runit Showcase Flicks.';
$canonicalUrl = $baseUrl . '/flicks' . ($firstFlick ? '/' . ($firstFlick['slug'] ?: $firstFlick['id']) : '');
$ogImage = $firstFlick && $firstFlick['thumbnail_path'] ? $baseUrl . '/' . ltrim($firstFlick['thumbnail_path'], '/') : '';

include __DIR__ . '/includes/header.php';
include __DIR__ . '/includes/sidebar.php';
?>

<div class="flicks-wrapper">
  <?php if (empty($flicks)): ?>
    <div class="container py-5 text-center my-auto">
      <div class="p-5 bg-white border border-light-subtle rounded-4 shadow-sm mx-auto" style="max-width: 520px;">
        <i class="bi bi-lightning-charge-fill display-1 text-warning mb-3 d-block"></i>
        <h4 class="fw-bold text-dark mb-2">No Flicks Added Yet</h4>
        <p class="text-secondary small mb-4">No vertical videos have been marked as Flicks. Only videos explicitly marked as Flicks in Creator Studio or uploaded with the Flick toggle will appear here.</p>
        <a href="<?= $baseUrl ?>/videos" class="btn btn-danger rounded-pill px-4 me-2">
          <i class="bi bi-play-circle me-1"></i> Browse Videos
        </a>
        <?php if ($isAdmin): ?>
          <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-dark rounded-pill px-4">
            <i class="bi bi-camera-reels me-1"></i> Creator Studio
          </a>
        <?php endif; ?>
      </div>
    </div>
  <?php else: ?>

    <!-- Flicks Scroll Container (Snap Vertical) -->
    <div class="flicks-feed" id="flicksFeed">
      <?php foreach ($flicks as $index => $item): 
        $targetSlug = $item['slug'] ?: $item['id'];
        $videoSrc = $baseUrl . '/' . ltrim($item['file_path'], '/');
        $flickUrl = $baseUrl . '/flicks/' . $targetSlug;
        $thumbUrl = $item['thumbnail_path'] ? ($baseUrl . '/' . ltrim($item['thumbnail_path'], '/')) : '';
        $likes = (int)($item['likes_count'] ?? 0);
        $views = (int)($item['views_count'] ?? 0);
      ?>
        <div class="flick-card" data-id="<?= $item['id'] ?>" data-slug="<?= $targetSlug ?>" data-index="<?= $index ?>">
          
          <!-- Portrait Video Container -->
          <div class="flick-player-box position-relative">
            
            <!-- Video Tag -->
            <video 
              class="flick-video" 
              src="<?= $videoSrc ?>" 
              poster="<?= $thumbUrl ?>" 
              playsinline 
              preload="metadata" 
              loop
            ></video>

            <!-- Play / Pause Overlay Icon -->
            <div class="flick-state-icon">
              <i class="bi bi-play-fill"></i>
            </div>

            <!-- Double-Tap Heart Animation -->
            <div class="flick-heart-anim">
              <i class="bi bi-heart-fill"></i>
            </div>

            <!-- Top Overlay: Sound Toggle & Category Badge -->
            <div class="flick-top-bar">
              <span class="badge bg-dark bg-opacity-50 text-white rounded-pill px-3 py-2 small backdrop-blur">
                <i class="bi bi-lightning-charge-fill text-warning me-1"></i> Flick
              </span>
              <button type="button" class="btn btn-dark btn-sm rounded-circle flick-sound-btn backdrop-blur" title="Toggle Sound (M)">
                <i class="bi bi-volume-mute-fill"></i>
              </button>
            </div>

            <!-- Bottom Video Scrubber Progress -->
            <div class="flick-progress-line">
              <div class="flick-progress-fill"></div>
            </div>

            <!-- Bottom Left Info: Title, Subtitle, Description -->
            <div class="flick-info-overlay">
              <h5 class="flick-title text-white fw-bold mb-1 text-truncate" title="<?= h($item['title']) ?>">
                <?= h($item['title']) ?>
              </h5>
              <?php if (!empty($item['description'])): ?>
                <p class="flick-desc text-white-50 small mb-2 text-truncate" style="max-width: 90%;">
                  <?= h($item['description']) ?>
                </p>
              <?php endif; ?>
              <div class="d-flex align-items-center gap-2 text-white-50 small font-monospace">
                <span><i class="bi bi-eye-fill me-1"></i><?= number_format($views) ?></span>
                <span>&bull;</span>
                <span><?= timeAgo($item['created_at']) ?></span>
              </div>
            </div>

            <!-- Right Floating Action Buttons (Like, Share, Copy Link, Fullscreen) -->
            <div class="flick-actions-bar">
              <!-- Like Button -->
              <button type="button" class="flick-action-btn flick-like-btn" data-id="<?= $item['id'] ?>" title="Like">
                <div class="flick-action-icon">
                  <i class="bi bi-heart-fill"></i>
                </div>
                <span class="flick-action-count flick-like-count"><?= $likes > 0 ? $likes : 'Like' ?></span>
              </button>

              <!-- Share Button -->
              <button type="button" class="flick-action-btn flick-share-btn" data-url="<?= $flickUrl ?>" data-title="<?= h($item['title']) ?>" title="Share">
                <div class="flick-action-icon">
                  <i class="bi bi-share-fill"></i>
                </div>
                <span class="flick-action-count">Share</span>
              </button>

              <!-- Copy Link -->
              <button type="button" class="flick-action-btn flick-copy-btn" data-url="<?= $flickUrl ?>" title="Copy link">
                <div class="flick-action-icon">
                  <i class="bi bi-link-45deg"></i>
                </div>
                <span class="flick-action-count">Copy</span>
              </button>

              <!-- Fullscreen / Normal Toggle -->
              <button type="button" class="flick-action-btn flick-fullscreen-btn" title="Toggle Fullscreen">
                <div class="flick-action-icon">
                  <i class="bi bi-fullscreen"></i>
                </div>
                <span class="flick-action-count flick-fullscreen-label">Full</span>
              </button>
            </div>

          </div>

          <!-- Desktop Navigation Side Arrows -->
          <div class="flick-desktop-nav d-none d-lg-flex flex-column gap-2">
            <button type="button" class="btn btn-light rounded-circle shadow-sm flick-nav-up" title="Previous Flick (Up Arrow)" <?= $index === 0 ? 'disabled' : '' ?>>
              <i class="bi bi-chevron-up"></i>
            </button>
            <button type="button" class="btn btn-light rounded-circle shadow-sm flick-nav-down" title="Next Flick (Down Arrow)" <?= $index === count($flicks) - 1 ? 'disabled' : '' ?>>
              <i class="bi bi-chevron-down"></i>
            </button>
          </div>

        </div>
      <?php endforeach; ?>
    </div>

  <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  const feed = document.getElementById('flicksFeed');
  if (!feed) return;

  const cards = Array.from(feed.querySelectorAll('.flick-card'));
  let isMuted = localStorage.getItem('runit_flicks_muted') !== 'false'; // muted by default for safe autoplay
  let currentActiveCard = null;

  // Sound sync across all videos
  function setSoundState(muted) {
    isMuted = muted;
    localStorage.setItem('runit_flicks_muted', isMuted);
    feed.querySelectorAll('.flick-video').forEach(v => {
      v.muted = isMuted;
    });
    feed.querySelectorAll('.flick-sound-btn i').forEach(icon => {
      icon.className = isMuted ? 'bi bi-volume-mute-fill' : 'bi bi-volume-up-fill';
    });
  }

  // IntersectionObserver to auto-play only visible flick
  const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
      const card = entry.target;
      const video = card.querySelector('.flick-video');
      const progressFill = card.querySelector('.flick-progress-fill');

      if (entry.isIntersecting && entry.intersectionRatio >= 0.6) {
        currentActiveCard = card;
        // Pause all other videos
        cards.forEach(c => {
          if (c !== card) {
            const v = c.querySelector('.flick-video');
            if (v && !v.paused) v.pause();
          }
        });

        // Play active
        video.muted = isMuted;
        const playPromise = video.play();
        if (playPromise !== undefined) {
          playPromise.catch(err => {
            console.warn('Autoplay waiting for user gesture:', err);
            video.muted = true;
            video.play().catch(() => {});
          });
        }

        // Update URL bar cleanly without reload
        const slug = card.getAttribute('data-slug');
        if (slug && window.history.replaceState) {
          window.history.replaceState(null, '', '<?= $baseUrl ?>/flick/' + slug);
        }

        // Increment view count in session/DB (once per card)
        if (!card.dataset.viewLogged) {
          card.dataset.viewLogged = '1';
          const assetId = card.getAttribute('data-id');
          if (assetId) {
            fetch('api/like.php?action=view', {
              method: 'POST',
              headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
              body: 'id=' + encodeURIComponent(assetId)
            }).catch(() => {});
          }
        }
      } else {
        if (!video.paused) {
          video.pause();
        }
      }
    });
  }, { threshold: [0.6] });

  cards.forEach(card => observer.observe(card));

  // Pre-load liked IDs from localStorage
  let likedIds = [];
  try {
    likedIds = JSON.parse(localStorage.getItem('runit_flicks_liked') || '[]');
    if (!Array.isArray(likedIds)) likedIds = [];
  } catch (e) {
    likedIds = [];
  }

  // Initialize videos
  cards.forEach((card, idx) => {
    const video = card.querySelector('.flick-video');
    const playerBox = card.querySelector('.flick-player-box');
    const stateIcon = card.querySelector('.flick-state-icon');
    const soundBtn = card.querySelector('.flick-sound-btn');
    const heartAnim = card.querySelector('.flick-heart-anim');
    const progressFill = card.querySelector('.flick-progress-fill');
    const cardId = card.getAttribute('data-id');
    const likeBtn = card.querySelector('.flick-like-btn');

    // Pre-mark liked state
    if (likedIds.includes(cardId) && likeBtn) {
      likeBtn.classList.add('liked');
    }

    // Scrubber update
    video.addEventListener('timeupdate', () => {
      if (video.duration) {
        const pct = (video.currentTime / video.duration) * 100;
        progressFill.style.width = pct + '%';
      }
    });

    // Sound toggle button
    soundBtn.addEventListener('click', (e) => {
      e.stopPropagation();
      setSoundState(!isMuted);
    });

    // Tap to play/pause with visual icon
    let lastTap = 0;
    playerBox.addEventListener('click', (e) => {
      if (e.target.closest('.flick-actions-bar') || e.target.closest('.flick-top-bar')) return;

      const now = Date.now();
      if (now - lastTap < 300) {
        // Double tap -> Like (force like if not yet liked, else heart pulse)
        triggerLike(card, true);
      } else {
        // Single tap -> Play / Pause
        if (video.paused) {
          video.play();
          showStateIcon(stateIcon, 'bi-play-fill');
        } else {
          video.pause();
          showStateIcon(stateIcon, 'bi-pause-fill');
        }
      }
      lastTap = now;
    });
  });

  function showStateIcon(el, iconClass) {
    if (!el) return;
    el.innerHTML = `<i class="bi ${iconClass}"></i>`;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 600);
  }

  function showHeartAnimation(el) {
    if (!el) return;
    el.classList.add('show');
    setTimeout(() => el.classList.remove('show'), 800);
  }

  // Like button handling (Toggle Like / Unlike)
  function triggerLike(card, forceLikeOnly = false) {
    const btn = card.querySelector('.flick-like-btn');
    const id = card.getAttribute('data-id');
    const countEl = card.querySelector('.flick-like-count');
    const heartAnim = card.querySelector('.flick-heart-anim');

    const isAlreadyLiked = likedIds.includes(id);
    if (forceLikeOnly && isAlreadyLiked) {
      showHeartAnimation(heartAnim);
      return;
    }

    fetch('api/like.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: 'id=' + encodeURIComponent(id)
    })
    .then(r => r.json())
    .then(data => {
      if (data.success) {
        if (data.liked) {
          if (!likedIds.includes(id)) likedIds.push(id);
          btn.classList.add('liked');
          showHeartAnimation(heartAnim);
        } else {
          likedIds = likedIds.filter(item => item !== id);
          btn.classList.remove('liked');
        }
        try {
          localStorage.setItem('runit_flicks_liked', JSON.stringify(likedIds));
        } catch (e) {}

        if (countEl) {
          countEl.textContent = data.likes > 0 ? data.likes : 'Like';
        }
      }
    })
    .catch(() => {});
  }

  feed.querySelectorAll('.flick-like-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const card = btn.closest('.flick-card');
      triggerLike(card, false);
    });
  });

  // Fullscreen / Normal Toggle
  function toggleFullscreen(card) {
    const playerBox = card.querySelector('.flick-player-box');
    const fsBtn = card.querySelector('.flick-fullscreen-btn');
    const icon = fsBtn ? fsBtn.querySelector('.flick-action-icon i') : null;
    const label = fsBtn ? fsBtn.querySelector('.flick-fullscreen-label') : null;

    const isNativeFs = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
    const isCssFs = playerBox.classList.contains('flick-fullscreen-active');

    if (isNativeFs) {
      if (document.exitFullscreen) {
        document.exitFullscreen().catch(() => {});
      } else if (document.webkitExitFullscreen) {
        document.webkitExitFullscreen();
      }
    } else if (isCssFs) {
      playerBox.classList.remove('flick-fullscreen-active');
      document.body.classList.remove('flick-body-fullscreen');
      updateFullscreenButtons(false);
    } else {
      // Try native fullscreen first
      const req = playerBox.requestFullscreen || playerBox.webkitRequestFullscreen;
      if (req) {
        req.call(playerBox).catch(() => {
          // Native denied or failed -> CSS fullscreen fallback
          playerBox.classList.add('flick-fullscreen-active');
          document.body.classList.add('flick-body-fullscreen');
          updateFullscreenButtons(true);
        });
      } else {
        // iOS Safari / non-supporting browsers -> CSS fullscreen fallback
        playerBox.classList.add('flick-fullscreen-active');
        document.body.classList.add('flick-body-fullscreen');
        updateFullscreenButtons(true);
      }
    }
  }

  function updateFullscreenButtons(isFs) {
    document.querySelectorAll('.flick-fullscreen-btn').forEach(b => {
      const ic = b.querySelector('.flick-action-icon i');
      const lb = b.querySelector('.flick-fullscreen-label');
      if (ic) ic.className = isFs ? 'bi bi-fullscreen-exit' : 'bi bi-fullscreen';
      if (lb) lb.textContent = isFs ? 'Exit' : 'Full';
    });
  }

  // Listen for native fullscreen state changes
  const onFsChange = () => {
    const isFs = Boolean(document.fullscreenElement || document.webkitFullscreenElement);
    updateFullscreenButtons(isFs);
    if (!isFs) {
      document.querySelectorAll('.flick-player-box.flick-fullscreen-active').forEach(box => {
        box.classList.remove('flick-fullscreen-active');
      });
      document.body.classList.remove('flick-body-fullscreen');
    }
  };
  document.addEventListener('fullscreenchange', onFsChange);
  document.addEventListener('webkitfullscreenchange', onFsChange);

  feed.querySelectorAll('.flick-fullscreen-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const card = btn.closest('.flick-card');
      toggleFullscreen(card);
    });
  });

  // Share and Copy links
  feed.querySelectorAll('.flick-share-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const url = btn.getAttribute('data-url');
      const title = btn.getAttribute('data-title');
      if (navigator.share) {
        navigator.share({ title: title, url: url }).catch(() => {});
      } else {
        navigator.clipboard.writeText(url);
        showToast('Link copied to clipboard!');
      }
    });
  });

  feed.querySelectorAll('.flick-copy-btn').forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      const url = btn.getAttribute('data-url');
      navigator.clipboard.writeText(url);
      showToast('Link copied to clipboard!');
    });
  });

  // Desktop Navigation Side Arrows
  feed.querySelectorAll('.flick-nav-up').forEach((btn, idx) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (idx > 0) {
        cards[idx - 1].scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  feed.querySelectorAll('.flick-nav-down').forEach((btn, idx) => {
    btn.addEventListener('click', (e) => {
      e.stopPropagation();
      if (idx < cards.length - 1) {
        cards[idx + 1].scrollIntoView({ behavior: 'smooth' });
      }
    });
  });

  // Keyboard Navigation: Up/Down arrow keys
  window.addEventListener('keydown', (e) => {
    if (!currentActiveCard) return;
    const curIdx = parseInt(currentActiveCard.getAttribute('data-index'), 10);
    if (e.key === 'ArrowDown' || e.key === 'j') {
      e.preventDefault();
      if (curIdx < cards.length - 1) {
        cards[curIdx + 1].scrollIntoView({ behavior: 'smooth' });
      }
    } else if (e.key === 'ArrowUp' || e.key === 'k') {
      e.preventDefault();
      if (curIdx > 0) {
        cards[curIdx - 1].scrollIntoView({ behavior: 'smooth' });
      }
    } else if (e.key.toLowerCase() === 'm') {
      e.preventDefault();
      setSoundState(!isMuted);
    } else if (e.key === ' ') {
      e.preventDefault();
      const video = currentActiveCard.querySelector('.flick-video');
      if (video) {
        if (video.paused) video.play();
        else video.pause();
      }
    }
  });

  // Apply initial sound state
  setSoundState(isMuted);
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
