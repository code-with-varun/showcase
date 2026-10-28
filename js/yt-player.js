/**
 * YouTube-style HTML5 Video Player Controller
 * Showcase Tube & Media CDN
 */

class YTPlayer {
  constructor(containerId) {
    this.container = document.getElementById(containerId);
    if (!this.container) return;

    this.video = this.container.querySelector('video');
    this.playBtn = this.container.querySelector('.yt-play-btn');
    this.playIcon = this.playBtn?.querySelector('i');
    this.progressContainer = this.container.querySelector('.yt-progress-container');
    this.progressBar = this.container.querySelector('.yt-progress-played');
    this.bufferBar = this.container.querySelector('.yt-progress-buffer');
    this.scrubTooltip = this.container.querySelector('.yt-scrub-tooltip');
    this.timeDisplay = this.container.querySelector('.yt-time-display');
    this.volumeBtn = this.container.querySelector('.yt-volume-btn');
    this.volumeIcon = this.volumeBtn?.querySelector('i');
    this.volumeSlider = this.container.querySelector('.yt-volume-slider');
    this.fullscreenBtn = this.container.querySelector('.yt-fullscreen-btn');
    this.theaterBtn = this.container.querySelector('.yt-theater-btn');
    this.pipBtn = this.container.querySelector('.yt-pip-btn');
    this.speedSelect = this.container.querySelector('.yt-speed-select');

    this.isDragging = false;
    this.init();
  }

  init() {
    if (!this.video) return;

    // Video events
    this.video.addEventListener('click', () => this.togglePlay());
    this.video.addEventListener('play', () => this.onPlay());
    this.video.addEventListener('pause', () => this.onPause());
    this.video.addEventListener('timeupdate', () => this.onTimeUpdate());
    this.video.addEventListener('progress', () => this.onBufferProgress());
    this.video.addEventListener('loadedmetadata', () => this.onMetadataLoaded());
    this.video.addEventListener('ended', () => this.onVideoEnded());

    // Controls
    if (this.playBtn) this.playBtn.addEventListener('click', () => this.togglePlay());

    // Progress bar events
    if (this.progressContainer) {
      this.progressContainer.addEventListener('click', (e) => this.seek(e));
      this.progressContainer.addEventListener('mousemove', (e) => this.onProgressHover(e));
      this.progressContainer.addEventListener('mouseleave', () => {
        if (this.scrubTooltip) this.scrubTooltip.style.display = 'none';
      });
    }

    // Volume events
    if (this.volumeBtn) this.volumeBtn.addEventListener('click', () => this.toggleMute());
    if (this.volumeSlider) {
      this.volumeSlider.addEventListener('input', (e) => {
        this.video.volume = parseFloat(e.target.value);
        this.video.muted = (this.video.volume === 0);
        this.updateVolumeIcon();
      });
    }

    // Speed selector
    if (this.speedSelect) {
      this.speedSelect.addEventListener('change', (e) => {
        this.video.playbackRate = parseFloat(e.target.value);
      });
    }

    // Fullscreen
    if (this.fullscreenBtn) this.fullscreenBtn.addEventListener('click', () => this.toggleFullscreen());

    // Theater Mode
    if (this.theaterBtn) this.theaterBtn.addEventListener('click', () => this.toggleTheater());

    // PiP
    if (this.pipBtn && document.pictureInPictureEnabled) {
      this.pipBtn.addEventListener('click', () => this.togglePiP());
    } else if (this.pipBtn) {
      this.pipBtn.style.display = 'none';
    }

    // Keyboard shortcuts
    document.addEventListener('keydown', (e) => this.handleHotkeys(e));

    // Support timestamp seeking from external links e.g. <a href="#" data-seek="75">
    document.querySelectorAll('[data-seek]').forEach(el => {
      el.addEventListener('click', (e) => {
        e.preventDefault();
        const seconds = parseInt(el.getAttribute('data-seek'), 10);
        if (!isNaN(seconds)) {
          this.seekTo(seconds);
        }
      });
    });

    // Check URL parameters like ?t=30s or ?autoplay=1
    const params = new URLSearchParams(window.location.search);
    if (params.has('t')) {
      const t = parseInt(params.get('t'), 10);
      if (!isNaN(t)) {
        this.video.addEventListener('loadedmetadata', () => {
          this.video.currentTime = t;
        }, { once: true });
      }
    }
    if (params.get('autoplay') === '1') {
      this.video.play().catch(() => {
        this.video.muted = true;
        this.video.play().catch(() => {});
      });
    }

    this.initAutoplay();
  }

  initAutoplay() {
    const autoplayToggle = document.getElementById('autoplayToggle');
    if (autoplayToggle) {
      const saved = localStorage.getItem('yt_autoplay');
      if (saved !== null) {
        autoplayToggle.checked = (saved === 'true');
      }
      autoplayToggle.addEventListener('change', () => {
        localStorage.setItem('yt_autoplay', autoplayToggle.checked);
      });
    }
  }

  onVideoEnded() {
    const autoplayToggle = document.getElementById('autoplayToggle');
    const isAutoplay = autoplayToggle ? autoplayToggle.checked : (localStorage.getItem('yt_autoplay') !== 'false');
    const nextVideoUrl = window.nextVideoUrl;

    if (!isAutoplay || !nextVideoUrl) return;

    const overlay = document.getElementById('autoplayOverlay');
    const countdownEl = document.getElementById('countdownSecs');
    if (overlay) {
      overlay.style.display = 'flex';
    }

    let remaining = 5;
    if (countdownEl) countdownEl.textContent = remaining;

    if (this.autoplayTimer) clearInterval(this.autoplayTimer);
    this.autoplayTimer = setInterval(() => {
      remaining--;
      if (countdownEl) countdownEl.textContent = remaining;
      if (remaining <= 0) {
        clearInterval(this.autoplayTimer);
        window.location.href = nextVideoUrl;
      }
    }, 1000);

    const cancelBtn = document.getElementById('cancelAutoplayBtn');
    if (cancelBtn) {
      cancelBtn.onclick = () => {
        if (this.autoplayTimer) clearInterval(this.autoplayTimer);
        if (overlay) overlay.style.display = 'none';
      };
    }
  }

  togglePlay() {
    if (this.video.paused) {
      this.video.play();
    } else {
      this.video.pause();
    }
  }

  onPlay() {
    this.container.classList.remove('paused');
    if (this.playIcon) {
      this.playIcon.className = 'bi bi-pause-fill';
    }
  }

  onPause() {
    this.container.classList.add('paused');
    if (this.playIcon) {
      this.playIcon.className = 'bi bi-play-fill';
    }
  }

  onTimeUpdate() {
    if (this.isDragging || !this.video.duration) return;
    const pct = (this.video.currentTime / this.video.duration) * 100;
    if (this.progressBar) this.progressBar.style.width = pct + '%';
    this.updateTimeText();
  }

  onBufferProgress() {
    if (!this.video.duration || !this.video.buffered.length) return;
    try {
      const bufferedEnd = this.video.buffered.end(this.video.buffered.length - 1);
      const pct = (bufferedEnd / this.video.duration) * 100;
      if (this.bufferBar) this.bufferBar.style.width = pct + '%';
    } catch (e) {}
  }

  onMetadataLoaded() {
    this.updateTimeText();
    this.updateVolumeIcon();
  }

  updateTimeText() {
    if (!this.timeDisplay) return;
    const curr = this.formatTime(this.video.currentTime);
    const total = this.formatTime(this.video.duration || 0);
    this.timeDisplay.textContent = `${curr} / ${total}`;
  }

  formatTime(seconds) {
    if (isNaN(seconds) || seconds < 0) return '0:00';
    const hrs = Math.floor(seconds / 3600);
    const mins = Math.floor((seconds % 3600) / 60);
    const secs = Math.floor(seconds % 60);
    if (hrs > 0) {
      return `${hrs}:${mins < 10 ? '0' : ''}${mins}:${secs < 10 ? '0' : ''}${secs}`;
    }
    return `${mins}:${secs < 10 ? '0' : ''}${secs}`;
  }

  seek(e) {
    const rect = this.progressContainer.getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    this.video.currentTime = pos * this.video.duration;
  }

  seekTo(seconds) {
    if (this.video) {
      this.video.currentTime = seconds;
      this.video.play().catch(() => {});
      this.container.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
  }

  onProgressHover(e) {
    if (!this.scrubTooltip || !this.video.duration) return;
    const rect = this.progressContainer.getBoundingClientRect();
    const pos = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
    const hoverTime = pos * this.video.duration;

    this.scrubTooltip.style.display = 'block';
    this.scrubTooltip.style.left = (pos * 100) + '%';
    this.scrubTooltip.textContent = this.formatTime(hoverTime);
  }

  toggleMute() {
    this.video.muted = !this.video.muted;
    if (this.volumeSlider) {
      this.volumeSlider.value = this.video.muted ? 0 : this.video.volume;
    }
    this.updateVolumeIcon();
  }

  updateVolumeIcon() {
    if (!this.volumeIcon) return;
    if (this.video.muted || this.video.volume === 0) {
      this.volumeIcon.className = 'bi bi-volume-mute-fill';
    } else if (this.video.volume < 0.5) {
      this.volumeIcon.className = 'bi bi-volume-down-fill';
    } else {
      this.volumeIcon.className = 'bi bi-volume-up-fill';
    }
  }

  toggleFullscreen() {
    if (!document.fullscreenElement) {
      this.container.requestFullscreen?.().catch(() => {});
    } else {
      document.exitFullscreen?.().catch(() => {});
    }
  }

  toggleTheater() {
    const layout = document.querySelector('.watch-layout');
    if (layout) {
      layout.classList.toggle('theater-active');
      window.dispatchEvent(new Event('resize'));
    }
  }

  async togglePiP() {
    try {
      if (document.pictureInPictureElement) {
        await document.exitPictureInPicture();
      } else {
        await this.video.requestPictureInPicture();
      }
    } catch (e) {
      console.warn('PiP error:', e);
    }
  }

  handleHotkeys(e) {
    // Avoid triggering when typing in inputs/textareas
    const tag = e.target.tagName.toLowerCase();
    if (tag === 'input' || tag === 'textarea' || e.target.isContentEditable) return;

    switch (e.key.toLowerCase()) {
      case ' ':
      case 'k':
        e.preventDefault();
        this.togglePlay();
        break;
      case 'f':
        e.preventDefault();
        this.toggleFullscreen();
        break;
      case 't':
        e.preventDefault();
        this.toggleTheater();
        break;
      case 'm':
        e.preventDefault();
        this.toggleMute();
        break;
      case 'j':
        e.preventDefault();
        this.video.currentTime = Math.max(0, this.video.currentTime - 10);
        break;
      case 'l':
        e.preventDefault();
        this.video.currentTime = Math.min(this.video.duration, this.video.currentTime + 10);
        break;
      case 'arrowleft':
        e.preventDefault();
        this.video.currentTime = Math.max(0, this.video.currentTime - 5);
        break;
      case 'arrowright':
        e.preventDefault();
        this.video.currentTime = Math.min(this.video.duration, this.video.currentTime + 5);
        break;
      case 'arrowup':
        e.preventDefault();
        this.video.volume = Math.min(1, this.video.volume + 0.05);
        this.video.muted = false;
        if (this.volumeSlider) this.volumeSlider.value = this.video.volume;
        this.updateVolumeIcon();
        break;
      case 'arrowdown':
        e.preventDefault();
        this.video.volume = Math.max(0, this.video.volume - 0.05);
        if (this.volumeSlider) this.volumeSlider.value = this.video.volume;
        this.updateVolumeIcon();
        break;
      case '0': case '1': case '2': case '3': case '4':
      case '5': case '6': case '7': case '8': case '9':
        e.preventDefault();
        const pct = parseInt(e.key, 10) / 10;
        this.video.currentTime = pct * this.video.duration;
        break;
    }
  }
}

// Auto-initialize when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
  if (document.getElementById('ytPlayerContainer')) {
    window.playerInstance = new YTPlayer('ytPlayerContainer');
  }
});
