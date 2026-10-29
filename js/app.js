/**
 * Showcase Tube & Media CDN Vault - Client Application Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  // ---------------------------------------------------------------
  // Sidebar Toggle - Persist collapsed state in localStorage
  // ---------------------------------------------------------------
  const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
  const sidebarCloseBtn = document.getElementById('sidebarCloseBtn');
  const sidebarBackdrop = document.getElementById('ytSidebarBackdrop');
  const SIDEBAR_KEY = 'runit_sidebar_collapsed';

  // Restore sidebar state immediately on load and clear pre-collapse flag
  function restoreSidebarState() {
    document.documentElement.classList.remove('sidebar-pre-collapsed');
    if (window.innerWidth >= 992) {
      if (localStorage.getItem(SIDEBAR_KEY) === '1') {
        document.body.classList.add('sidebar-collapsed');
      } else {
        document.body.classList.remove('sidebar-collapsed');
      }
    }
  }
  restoreSidebarState();

  if (sidebarToggleBtn) {
    sidebarToggleBtn.addEventListener('click', (e) => {
      e.preventDefault();
      document.documentElement.classList.remove('sidebar-pre-collapsed');
      if (window.innerWidth < 992) {
        document.body.classList.toggle('sidebar-mobile-open');
      } else {
        const isNowCollapsed = document.body.classList.toggle('sidebar-collapsed');
        localStorage.setItem(SIDEBAR_KEY, isNowCollapsed ? '1' : '0');
      }
    });
  }

  // Sidebar close button (mobile)
  if (sidebarCloseBtn) {
    sidebarCloseBtn.addEventListener('click', (e) => {
      e.preventDefault();
      document.body.classList.remove('sidebar-mobile-open');
    });
  }

  // Sidebar backdrop for mobile close
  if (sidebarBackdrop) {
    sidebarBackdrop.addEventListener('click', () => {
      document.body.classList.remove('sidebar-mobile-open');
    });
  }

  // Sidebar nav links: auto-close drawer on mobile, maintain state on desktop
  const sidebarLinks = document.querySelectorAll('#ytSidebar .nav-link');
  sidebarLinks.forEach(link => {
    link.addEventListener('click', () => {
      if (window.innerWidth < 992) {
        document.body.classList.remove('sidebar-mobile-open');
      }
    });
  });

  // Toast Helper
  const toastEl = document.getElementById('appToast');
  const toastMessage = document.getElementById('toastMessage');
  const toastInstance = toastEl ? new bootstrap.Toast(toastEl, { delay: 3500 }) : null;

  window.showToast = function(message, isSuccess = true) {
    if (!toastInstance || !toastMessage) {
      alert(message);
      return;
    }
    const icon = isSuccess ? '<i class="bi bi-check-circle-fill text-success"></i>' : '<i class="bi bi-exclamation-triangle-fill text-danger"></i>';
    toastMessage.innerHTML = `${icon} <span>${message}</span>`;
    toastInstance.show();
  };

  // Generic Copy Button
  document.addEventListener('click', (e) => {
    const btn = e.target.closest('.copy-btn');
    if (!btn) return;
    const textToCopy = btn.getAttribute('data-copy') || btn.previousElementSibling?.value;
    if (textToCopy) {
      navigator.clipboard.writeText(textToCopy).then(() => {
        const origText = btn.innerHTML;
        btn.innerHTML = '<i class="bi bi-check2"></i> Copied!';
        btn.classList.replace('btn-outline-danger', 'btn-success');
        showToast('Copied to clipboard!');
        setTimeout(() => {
          btn.innerHTML = origText;
          btn.classList.replace('btn-success', 'btn-outline-danger');
        }, 2000);
      }).catch(err => {
        console.error('Copy failed:', err);
      });
    }
  });

  // Quick Sync Triggers
  const syncButtons = [document.getElementById('quickSyncBtn'), document.getElementById('sidebarSyncBtn')];
  syncButtons.forEach(btn => {
    if (!btn) return;
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const origHtml = btn.innerHTML;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Syncing...';
      btn.style.pointerEvents = 'none';

      fetch('api/sync.php')
        .then(res => res.json())
        .then(data => {
          btn.innerHTML = origHtml;
          btn.style.pointerEvents = 'auto';
          if (data.success) {
            const stats = data.stats;
            showToast(`Sync complete! Added: ${stats.added}, Updated: ${stats.updated}, Removed: ${stats.missing}`);
            // If on studio page, reload after 1 sec
            if (window.location.pathname.includes('studio.php')) {
              setTimeout(() => window.location.reload(), 1200);
            }
          } else {
            showToast(data.error || 'Sync failed', false);
          }
        })
        .catch(err => {
          btn.innerHTML = origHtml;
          btn.style.pointerEvents = 'auto';
          showToast('Failed to connect to sync endpoint: ' + err.message, false);
        });
    });
  });

  // Share Modal Population
  document.querySelectorAll('[data-share-asset]').forEach(trigger => {
    trigger.addEventListener('click', () => {
      try {
        const raw = trigger.getAttribute('data-share-asset');
        const asset = JSON.parse(raw);

        document.getElementById('shareModalTitle').textContent = `Share & Embed: ${asset.title}`;
        document.getElementById('shareWatchUrl').value = asset.watchUrl;
        document.getElementById('shareCdnUrl').value = asset.cdnUrl;
        document.getElementById('shareEmbedSnippet').value = asset.embedTag;
        document.getElementById('shareMarkdownSnippet').value = asset.markdownTag;

        document.getElementById('copyShareWatchBtn').setAttribute('data-copy', asset.watchUrl);
        document.getElementById('copyShareCdnBtn').setAttribute('data-copy', asset.cdnUrl);
        document.getElementById('copyShareEmbedBtn').setAttribute('data-copy', asset.embedTag);
        document.getElementById('copyShareMdBtn').setAttribute('data-copy', asset.markdownTag);

        const modal = new bootstrap.Modal(document.getElementById('shareAssetModal'));
        modal.show();
      } catch (err) {
        console.error('Error opening share modal:', err);
      }
    });
  });

  // Hover video playback on card preview (Restricted to a 10-second portion snippet)
  const MAX_PREVIEW_DURATION = 10; // 10 seconds max snippet

  document.querySelectorAll('.yt-card').forEach(card => {
    const video = card.querySelector('video.hover-preview');
    if (!video) return;

    let hoverTimeout = null;

    // Loop the 10-second snippet while hovering
    const onPreviewTimeUpdate = () => {
      if (video.currentTime >= MAX_PREVIEW_DURATION) {
        video.currentTime = 0;
      }
    };

    video.addEventListener('timeupdate', onPreviewTimeUpdate);
    video.addEventListener('ended', () => {
      video.currentTime = 0;
      video.play().catch(() => {});
    });

    card.addEventListener('mouseenter', () => {
      // 180ms debounce so rapid swiping across cards doesn't load/trigger playback
      hoverTimeout = setTimeout(() => {
        video.currentTime = 0;
        const playPromise = video.play();
        if (playPromise !== undefined) {
          playPromise.catch(() => {});
        }
      }, 180);
    });

    card.addEventListener('mouseleave', () => {
      if (hoverTimeout) clearTimeout(hoverTimeout);
      video.pause();
      video.currentTime = 0;
    });
  });

  // Keyboard shortcut '/' to focus search input
  document.addEventListener('keydown', (e) => {
    if (e.key === '/' && !['input', 'textarea'].includes(e.target.tagName.toLowerCase())) {
      const searchInput = document.querySelector('.yt-search-input');
      if (searchInput) {
        e.preventDefault();
        searchInput.focus();
        searchInput.select();
      }
    }
  });

  // ---------------------------------------------------------------
  // Scroll to Top Floating Button
  // ---------------------------------------------------------------
  const scrollToTopBtn = document.getElementById('scrollToTopBtn');
  if (scrollToTopBtn) {
    const getScrollTop = () => {
      return window.pageYOffset || document.documentElement.scrollTop || document.body.scrollTop || 0;
    };

    const toggleScrollBtn = () => {
      if (getScrollTop() > 150) {
        scrollToTopBtn.classList.add('visible');
      } else {
        scrollToTopBtn.classList.remove('visible');
      }
    };

    window.addEventListener('scroll', toggleScrollBtn, { passive: true });
    document.addEventListener('scroll', toggleScrollBtn, { passive: true });
    toggleScrollBtn();

    const doScrollToTop = (e) => {
      e.preventDefault();
      window.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      document.documentElement.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
      document.body.scrollTo({
        top: 0,
        behavior: 'smooth'
      });
    };

    scrollToTopBtn.addEventListener('click', doScrollToTop);
    scrollToTopBtn.addEventListener('touchend', doScrollToTop);
  }
});
