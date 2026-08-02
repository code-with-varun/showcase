const fs = require('fs');
const path = require('path');

const SHOWCASE_DIR = path.resolve(__dirname, '..');
const ASSETS_DIR = path.join(SHOWCASE_DIR, 'assets');
const CATALOG_PATH = path.join(SHOWCASE_DIR, 'assets.json');

function getCategoryAndTags(ext, relativePath) {
  const extension = ext.toLowerCase().replace('.', '');
  let category = 'other';
  let tags = [extension.toUpperCase()];

  const normalizedPath = relativePath.replace(/\\/g, '/');
  const pathParts = normalizedPath.split('/').map(p => p.toLowerCase());
  // Remove filename from path parts when building tags
  const folderParts = pathParts.slice(0, -1);

  if (['mp4', 'webm', 'mov', 'avi', 'mkv', 'm4v'].includes(extension)) {
    if (folderParts.some(p => p.includes('motion') || p.includes('comic') || p.includes('animation'))) {
      category = 'motion-comics';
      tags.push('Motion Comic', 'Video');
    } else {
      category = 'videos';
      tags.push('Video');
    }
  } else if (['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif', 'bmp'].includes(extension)) {
    if (folderParts.some(p => p.includes('motion') || p.includes('comic'))) {
      category = 'motion-comics';
      tags.push('Motion Comic', 'AI Art');
    } else {
      category = 'images';
      tags.push('Image');
    }
  } else if (['mp3', 'wav', 'ogg', 'flac', 'm4a'].includes(extension)) {
    category = 'audio';
    tags.push('Audio', 'Soundtrack');
  } else if (['pdf', 'doc', 'docx', 'txt', 'md', 'json'].includes(extension)) {
    category = 'documents';
    tags.push('Document');
  } else if (['xls', 'xlsx', 'csv'].includes(extension)) {
    category = 'documents';
    tags.push('Document', 'Spreadsheet');
  } else if (['ppt', 'pptx'].includes(extension)) {
    category = 'documents';
    tags.push('Document', 'Presentation');
  } else if (['zip', 'rar', '7z', 'tar', 'gz', 'bz2'].includes(extension)) {
    category = 'archives';
    tags.push('Archive', 'Bundle');
  }

  // Include folder names as tags
  folderParts.forEach(part => {
    if (part !== 'assets' && !['images', 'videos', 'documents', 'archives', 'motion-comics'].includes(part)) {
      const formattedTag = part.split(/[-_]/).map(w => w.charAt(0).toUpperCase() + w.slice(1)).join(' ');
      if (formattedTag && !tags.includes(formattedTag)) tags.push(formattedTag);
    }
  });

  return { category, tags };
}

function formatBytes(bytes, decimals = 2) {
  if (!bytes || bytes === 0) return '0 Bytes';
  const k = 1024;
  const dm = decimals < 0 ? 0 : decimals;
  const sizes = ['Bytes', 'KB', 'MB', 'GB', 'TB'];
  const i = Math.floor(Math.log(bytes) / Math.log(k));
  return parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + ' ' + sizes[i];
}

function titleCase(str) {
  return str
    .replace(/[-_]/g, ' ')
    .replace(/\s+/g, ' ')
    .trim()
    .replace(/\b\w/g, c => c.toUpperCase());
}

function scanDirectory(dir, baseDir = dir) {
  let filesList = [];
  if (!fs.existsSync(dir)) return filesList;

  const items = fs.readdirSync(dir, { withFileTypes: true });

  for (const item of items) {
    const fullPath = path.join(dir, item.name);
    if (item.isDirectory()) {
      filesList = filesList.concat(scanDirectory(fullPath, baseDir));
    } else if (item.isFile() && !item.name.startsWith('.')) {
      const relativePath = path.relative(baseDir, fullPath).replace(/\\/g, '/');
      const stats = fs.statSync(fullPath);
      const ext = path.extname(item.name);
      const nameWithoutExt = path.basename(item.name, ext);
      const { category, tags } = getCategoryAndTags(ext, relativePath);

      const isImage = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg'].includes(ext.replace('.', '').toLowerCase());

      filesList.push({
        id: relativePath.replace(/[^a-zA-Z0-9]/g, '-').toLowerCase(),
        title: titleCase(nameWithoutExt),
        filename: item.name,
        path: `assets/${relativePath}`,
        category: category,
        extension: ext.replace('.', '').toLowerCase(),
        sizeBytes: stats.size,
        sizeFormatted: formatBytes(stats.size),
        updatedAt: stats.mtime.toISOString(),
        tags: tags,
        thumbnail: isImage ? `assets/${relativePath}` : null,
        description: `Archived CDN asset: ${item.name}`
      });
    }
  }

  return filesList;
}

function updateCatalog() {
  console.log('🔍 Scanning showcase/assets directory...');

  if (!fs.existsSync(ASSETS_DIR)) {
    fs.mkdirSync(ASSETS_DIR, { recursive: true });
    console.log('📁 Created assets/ directory');
  }

  let existingMap = new Map();
  if (fs.existsSync(CATALOG_PATH)) {
    try {
      const currentCatalog = JSON.parse(fs.readFileSync(CATALOG_PATH, 'utf8'));
      if (Array.isArray(currentCatalog.assets)) {
        currentCatalog.assets.forEach(asset => {
          existingMap.set(asset.path, asset);
        });
      }
    } catch (err) {
      console.warn('⚠️ Could not parse existing assets.json, creating fresh catalog.');
    }
  }

  const scannedAssets = scanDirectory(ASSETS_DIR, ASSETS_DIR);

  const mergedAssets = scannedAssets.map(scanned => {
    if (existingMap.has(scanned.path)) {
      const existing = existingMap.get(scanned.path);
      return {
        ...scanned,
        title: existing.title || scanned.title,
        description: existing.description || scanned.description,
        tags: Array.from(new Set([...scanned.tags, ...(existing.tags || [])])),
        thumbnail: scanned.thumbnail || existing.thumbnail
      };
    }
    return scanned;
  });

  const totalSizeBytes = mergedAssets.reduce((sum, a) => sum + a.sizeBytes, 0);

  const catalog = {
    updatedAt: new Date().toISOString(),
    cdnBaseUrl: "https://code-with-varun.github.io/showcase",
    rawBaseUrl: "https://raw.githubusercontent.com/code-with-varun/showcase/main",
    stats: {
      totalFiles: mergedAssets.length,
      totalSizeBytes: totalSizeBytes,
      totalSizeFormatted: formatBytes(totalSizeBytes),
      categoriesCount: {
        "motion-comics": mergedAssets.filter(a => a.category === 'motion-comics').length,
        "videos": mergedAssets.filter(a => a.category === 'videos').length,
        "images": mergedAssets.filter(a => a.category === 'images').length,
        "audio": mergedAssets.filter(a => a.category === 'audio').length,
        "documents": mergedAssets.filter(a => a.category === 'documents').length,
        "archives": mergedAssets.filter(a => a.category === 'archives').length,
        "other": mergedAssets.filter(a => a.category === 'other').length
      }
    },
    assets: mergedAssets
  };

  fs.writeFileSync(CATALOG_PATH, JSON.stringify(catalog, null, 2), 'utf8');
  console.log(`✅ Successfully updated assets.json with ${mergedAssets.length} assets (${formatBytes(totalSizeBytes)})`);
}

updateCatalog();
