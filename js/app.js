/**
 * AI MOTION COMICS CDN & ARCHIVE LIBRARY VIEW
 * Client-side App Logic
 */

document.addEventListener('DOMContentLoaded', () => {
  let catalogData = {
    cdnBaseUrl: "https://code-with-varun.github.io/showcase",
    rawBaseUrl: "https://raw.githubusercontent.com/code-with-varun/showcase/main",
    stats: { totalFiles: 0, totalSizeFormatted: "0 Bytes", categoriesCount: {} },
    assets: []
  };

  let activeCategory = 'all';
  let searchQuery = '';
  let activeSort = 'newest';
  let activeView = 'grid';

  // DOM Elements
  const assetGrid = document.getElementById('assetGrid');
  const searchInput = document.getElementById('searchInput');
  const sortSelect = document.getElementById('sortSelect');
  const categoryNav = document.getElementById('categoryNav');
  const gridViewBtn = document.getElementById('gridViewBtn');
  const listViewBtn = document.getElementById('listViewBtn');
  const previewModal = document.getElementById('previewModal');
  const modalMediaView = document.getElementById('modalMediaView');
  const modalSidebar = document.getElementById('modalSidebar');
  const closeModalBtn = document.getElementById('closeModalBtn');
  const toastContainer = document.getElementById('toastContainer');
  const statTotalFiles = document.getElementById('statTotalFiles');
  const statTotalSize = document.getElementById('statTotalSize');
  const heroCdnInput = document.getElementById('heroCdnInput');
  const copyHeroCdnBtn = document.getElementById('copyHeroCdnBtn');

  // Load Catalog Data
  async function loadCatalog() {
    try {
      const response = await fetch('assets.json?t=' + Date.now());
      if (!response.ok) throw new Error('Could not load assets.json');
      catalogData = await response.json();
      initApp();
    } catch (err) {
      console.warn('Fallback loading assets:', err);
      // Fallback state if catalog fetch fails
      initApp();
    }
  }

  function initApp() {
    updateStats();
    renderCategoryPills();
    renderAssets();
    setupEventListeners();
  }

  // Update Top Stats
  function updateStats() {
    if (statTotalFiles) statTotalFiles.textContent = catalogData.stats.totalFiles || catalogData.assets.length;
    if (statTotalSize) statTotalSize.textContent = catalogData.stats.totalSizeFormatted || '0 Bytes';
    if (heroCdnInput) heroCdnInput.value = catalogData.cdnBaseUrl || "https://code-with-varun.github.io/showcase";
  }

  // Render Category Filters
  function renderCategoryPills() {
    const categories = [
      { id: 'all', label: 'All Assets', icon: '⚡' },
      { id: 'motion-comics', label: 'AI Motion Comics', icon: '🎬' },
      { id: 'images', label: 'Images & Panels', icon: '🎨' },
      { id: 'videos', label: 'Videos', icon: '🎥' },
      { id: 'documents', label: 'Documents', icon: '📄' },
      { id: 'archives', label: 'Archives & Zips', icon: '📦' }
    ];

    categoryNav.innerHTML = categories.map(cat => {
      let count = 0;
      if (cat.id === 'all') {
        count = catalogData.assets.length;
      } else {
        count = catalogData.assets.filter(a => a.category === cat.id).length;
      }
      return `
        <button class="nav-pill ${activeCategory === cat.id ? 'active' : ''}" data-category="${cat.id}">
          <span>${cat.icon}</span> ${cat.label}
          <span class="pill-count">${count}</span>
        </button>
      `;
    }).join('');

    categoryNav.querySelectorAll('.nav-pill').forEach(pill => {
      pill.addEventListener('click', () => {
        categoryNav.querySelectorAll('.nav-pill').forEach(p => p.classList.remove('active'));
        pill.classList.add('active');
        activeCategory = pill.dataset.category;
        renderAssets();
      });
    });
  }

  // Filter & Sort Assets
  function getFilteredAssets() {
    return catalogData.assets.filter(asset => {
      const matchesCategory = activeCategory === 'all' || asset.category === activeCategory;
      const matchesSearch = !searchQuery || 
        asset.title.toLowerCase().includes(searchQuery) ||
        asset.filename.toLowerCase().includes(searchQuery) ||
        (asset.tags && asset.tags.some(t => t.toLowerCase().includes(searchQuery))) ||
        asset.extension.toLowerCase().includes(searchQuery);
      return matchesCategory && matchesSearch;
    }).sort((a, b) => {
      if (activeSort === 'newest') return new Date(b.updatedAt) - new Date(a.updatedAt);
      if (activeSort === 'name') return a.title.localeCompare(b.title);
      if (activeSort === 'size') return b.sizeBytes - a.sizeBytes;
      return 0;
    });
  }

  // Render File Type Icon Box for non-images
  function renderTypeIcon(extension, category) {
    const ext = extension.toLowerCase();
    let iconClass = 'icon-other';
    let iconSymbol = '📄';

    if (['zip', 'rar', '7z', 'tar', 'gz'].includes(ext)) {
      iconClass = 'icon-zip';
      iconSymbol = '📦';
    } else if (ext === 'pdf') {
      iconClass = 'icon-pdf';
      iconSymbol = '📕';
    } else if (['doc', 'docx', 'txt', 'md'].includes(ext)) {
      iconClass = 'icon-doc';
      iconSymbol = '📝';
    } else if (['xls', 'xlsx', 'csv'].includes(ext)) {
      iconClass = 'icon-xls';
      iconSymbol = '📊';
    } else if (['ppt', 'pptx'].includes(ext)) {
      iconClass = 'icon-ppt';
      iconSymbol = '📊';
    } else if (['mp4', 'webm', 'mov'].includes(ext)) {
      iconClass = 'icon-doc';
      iconSymbol = '🎬';
    }

    return `
      <div class="file-type-icon ${iconClass}">
        <span>${iconSymbol}</span>
        <span>${ext.toUpperCase()}</span>
      </div>
    `;
  }

  // Render Assets Grid/List
  function renderAssets() {
    const assets = getFilteredAssets();

    if (assets.length === 0) {
      assetGrid.innerHTML = `
        <div class="empty-state">
          <div class="empty-icon">🔍</div>
          <h3>No assets found</h3>
          <p style="color: var(--text-muted); font-size: 0.9rem; margin-top: 0.5rem;">
            Try adjusting your search query or filter category.
          </p>
        </div>
      `;
      return;
    }

    assetGrid.className = `asset-grid ${activeView === 'list' ? 'list-view' : ''}`;

    assetGrid.innerHTML = assets.map(asset => {
      const fullCdnUrl = `${catalogData.cdnBaseUrl}/${asset.path}`;
      const isImage = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(asset.extension);
      const isVideo = ['mp4', 'webm', 'mov'].includes(asset.extension);

      let previewHTML = '';
      if (isImage) {
        previewHTML = `<img src="${asset.path}" alt="${asset.title}" loading="lazy" />`;
      } else if (isVideo) {
        previewHTML = `
          <video muted loop preload="metadata">
            <source src="${asset.path}#t=0.5" type="video/${asset.extension}">
          </video>
          <div style="position: absolute; font-size: 2rem; color: #fff; background: rgba(0,0,0,0.5); padding: 0.5rem; border-radius: 50%;">▶</div>
        `;
      } else {
        previewHTML = renderTypeIcon(asset.extension, asset.category);
      }

      const tagsHTML = (asset.tags || []).slice(0, 3).map(tag => 
        `<span class="tag-pill">${tag}</span>`
      ).join('');

      return `
        <div class="asset-card" data-id="${asset.id}">
          <div class="asset-preview">
            ${previewHTML}
            <span class="ext-badge">${asset.extension}</span>
            <span class="size-badge">${asset.sizeFormatted}</span>
          </div>
          <div class="asset-details">
            <h3 class="asset-title" title="${asset.title}">${asset.title}</h3>
            <div class="asset-filename" title="${asset.filename}">${asset.filename}</div>
            <div class="asset-tags">${tagsHTML}</div>
            <div class="asset-actions">
              <button class="action-btn copy-cdn-btn" data-url="${fullCdnUrl}" title="Copy CDN Link">
                <span>🔗</span> CDN
              </button>
              <button class="action-btn copy-embed-btn" data-asset='${JSON.stringify(asset).replace(/'/g, "&apos;")}' title="Copy HTML Snippet">
                <span>📋</span> Embed
              </button>
              <button class="action-btn preview-btn" data-id="${asset.id}" title="Preview Asset">
                <span>👁️</span> View
              </button>
              <a href="${asset.path}" download class="action-btn" title="Download Asset" style="text-decoration: none;">
                <span>⬇️</span>
              </a>
            </div>
          </div>
        </div>
      `;
    }).join('');

    // Attach card event listeners
    assetGrid.querySelectorAll('.copy-cdn-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        copyToClipboard(btn.dataset.url, 'Copied CDN URL to clipboard!');
      });
    });

    assetGrid.querySelectorAll('.copy-embed-btn').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        const asset = JSON.parse(btn.dataset.asset);
        const embedSnippet = generateEmbedCode(asset);
        copyToClipboard(embedSnippet, 'Copied HTML Embed tag!');
      });
    });

    assetGrid.querySelectorAll('.preview-btn').forEach(btn => {
      btn.addEventListener('click', () => {
        const asset = catalogData.assets.find(a => a.id === btn.dataset.id);
        if (asset) openLightbox(asset);
      });
    });

    // Mouseover preview logic for videos
    assetGrid.querySelectorAll('.asset-card').forEach(card => {
      const video = card.querySelector('video');
      if (video) {
        card.addEventListener('mouseenter', () => video.play().catch(() => {}));
        card.addEventListener('mouseleave', () => {
          video.pause();
          video.currentTime = 0;
        });
      }
    });
  }

  // Generate HTML Embed Snippet based on extension
  function generateEmbedCode(asset) {
    const cdnUrl = `${catalogData.cdnBaseUrl}/${asset.path}`;
    const ext = asset.extension.toLowerCase();

    if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext)) {
      return `<img src="${cdnUrl}" alt="${asset.title}" loading="lazy" />`;
    } else if (['mp4', 'webm', 'mov'].includes(ext)) {
      return `<video src="${cdnUrl}" controls poster="${asset.thumbnail || ''}"></video>`;
    } else if (['mp3', 'wav', 'ogg'].includes(ext)) {
      return `<audio src="${cdnUrl}" controls></audio>`;
    } else {
      return `<a href="${cdnUrl}" target="_blank" download="${asset.filename}">Download ${asset.title}</a>`;
    }
  }

  // Lightbox Modal
  function openLightbox(asset) {
    const fullCdnUrl = `${catalogData.cdnBaseUrl}/${asset.path}`;
    const rawUrl = `${catalogData.rawBaseUrl}/${asset.path}`;
    const embedSnippet = generateEmbedCode(asset);
    const mdSnippet = `![${asset.title}](${fullCdnUrl})`;

    const isImage = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(asset.extension);
    const isVideo = ['mp4', 'webm', 'mov'].includes(asset.extension);
    const isPdf = asset.extension === 'pdf';

    let mediaHTML = '';
    if (isImage) {
      mediaHTML = `<img src="${asset.path}" alt="${asset.title}" />`;
    } else if (isVideo) {
      mediaHTML = `<video src="${asset.path}" controls autoplay loop></video>`;
    } else if (isPdf) {
      mediaHTML = `<iframe src="${asset.path}" style="width: 100%; height: 100%; border: none;"></iframe>`;
    } else {
      mediaHTML = `
        <div style="text-align: center; color: #fff; padding: 2rem;">
          <div style="font-size: 4rem; margin-bottom: 1rem;">📦</div>
          <h3>${asset.filename}</h3>
          <p style="color: var(--text-muted); margin-top: 0.5rem;">${asset.sizeFormatted} • ${asset.extension.toUpperCase()} File</p>
          <a href="${asset.path}" download class="btn btn-primary" style="margin-top: 1.5rem;">Download File</a>
        </div>
      `;
    }

    modalMediaView.innerHTML = mediaHTML;

    modalSidebar.innerHTML = `
      <div>
        <h2 style="font-size: 1.25rem; margin-bottom: 0.25rem;">${asset.title}</h2>
        <div style="font-size: 0.75rem; color: var(--text-muted); font-family: monospace;">${asset.filename}</div>
      </div>

      <div class="snippet-box">
        <div class="snippet-header">
          <span>GitHub Pages CDN URL</span>
          <button class="action-btn copy-modal-cdn" style="padding: 0.2rem 0.4rem; font-size: 0.65rem;">Copy</button>
        </div>
        <div class="snippet-content">${fullCdnUrl}</div>
      </div>

      <div class="snippet-box">
        <div class="snippet-header">
          <span>Raw GitHub Link</span>
          <button class="action-btn copy-modal-raw" style="padding: 0.2rem 0.4rem; font-size: 0.65rem;">Copy</button>
        </div>
        <div class="snippet-content">${rawUrl}</div>
      </div>

      <div class="snippet-box">
        <div class="snippet-header">
          <span>HTML Embed Tag</span>
          <button class="action-btn copy-modal-embed" style="padding: 0.2rem 0.4rem; font-size: 0.65rem;">Copy</button>
        </div>
        <div class="snippet-content" style="white-space: pre-wrap;">${escapeHtml(embedSnippet)}</div>
      </div>

      <div style="font-size: 0.8rem; border-top: 1px solid var(--border-subtle); padding-top: 1rem;">
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
          <span style="color: var(--text-muted);">File Size:</span>
          <strong>${asset.sizeFormatted}</strong>
        </div>
        <div style="display: flex; justify-content: space-between; margin-bottom: 0.4rem;">
          <span style="color: var(--text-muted);">Category:</span>
          <strong style="text-transform: capitalize;">${asset.category}</strong>
        </div>
        <div style="display: flex; justify-content: space-between;">
          <span style="color: var(--text-muted);">Last Updated:</span>
          <strong>${new Date(asset.updatedAt).toLocaleDateString()}</strong>
        </div>
      </div>

      <a href="${asset.path}" download class="btn btn-primary" style="width: 100%; text-align: center; margin-top: auto;">
        ⬇️ Download File
      </a>
    `;

    // Attach copy events inside modal
    modalSidebar.querySelector('.copy-modal-cdn').addEventListener('click', () => {
      copyToClipboard(fullCdnUrl, 'Copied CDN Link!');
    });
    modalSidebar.querySelector('.copy-modal-raw').addEventListener('click', () => {
      copyToClipboard(rawUrl, 'Copied Raw GitHub Link!');
    });
    modalSidebar.querySelector('.copy-modal-embed').addEventListener('click', () => {
      copyToClipboard(embedSnippet, 'Copied HTML Embed Tag!');
    });

    previewModal.showModal();
  }

  function escapeHtml(str) {
    return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }

  // Toast Notification System
  function copyToClipboard(text, message = 'Copied to clipboard!') {
    navigator.clipboard.writeText(text).then(() => {
      showToast(message);
    }).catch(err => {
      console.error('Copy failed:', err);
    });
  }

  function showToast(message) {
    const toast = document.createElement('div');
    toast.className = 'toast';
    toast.innerHTML = `<span>✅</span> ${message}`;
    toastContainer.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(10px)';
      setTimeout(() => toast.remove(), 300);
    }, 2500);
  }

  // Setup Global Event Listeners
  function setupEventListeners() {
    searchInput.addEventListener('input', (e) => {
      searchQuery = e.target.value.trim().toLowerCase();
      renderAssets();
    });

    sortSelect.addEventListener('change', (e) => {
      activeSort = e.target.value;
      renderAssets();
    });

    gridViewBtn.addEventListener('click', () => {
      activeView = 'grid';
      gridViewBtn.classList.add('active');
      listViewBtn.classList.remove('active');
      renderAssets();
    });

    listViewBtn.addEventListener('click', () => {
      activeView = 'list';
      listViewBtn.classList.add('active');
      gridViewBtn.classList.remove('active');
      renderAssets();
    });

    closeModalBtn.addEventListener('click', () => {
      previewModal.close();
    });

    previewModal.addEventListener('click', (e) => {
      if (e.target === previewModal) previewModal.close();
    });

    if (copyHeroCdnBtn && heroCdnInput) {
      copyHeroCdnBtn.addEventListener('click', () => {
        copyToClipboard(heroCdnInput.value, 'Copied Base CDN URL!');
      });
    }

    // Keyboard shortcut "/" to focus search
    document.addEventListener('keydown', (e) => {
      if (e.key === '/' && document.activeElement !== searchInput) {
        e.preventDefault();
        searchInput.focus();
      }
    });
  }

  // Initialize
  loadCatalog();
});
