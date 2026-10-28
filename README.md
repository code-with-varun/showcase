# Showcase Tube & Digital Media CDN Vault

A YouTube-style video streaming application, digital asset repository, and high-performance CDN built with **Bootstrap 5.3 + PHP 8.2 + MySQL (MariaDB)**.

Designed for archiving, streaming, and embedding **AI Motion Comics, Videos, Artwork, PDFs, Spreadsheets, and Archive Bundles** directly into external portfolios, client apps, and websites.

---

## ⚡ Key Features

1. **YouTube-Style Watch & Streaming Experience**
   * **Custom YouTube Player**: Custom red scrubber bar (`#ff0000`), hover time tooltip, buffer progress, speed controls (0.25x – 2.0x), volume slider, theater mode, picture-in-picture, fullscreen, and hotkeys (`Space`/`K`, `F`, `T`, `M`, `J`/`L`, `0-9`).
   * **HTTP 206 Partial-Content Video Streaming** (`stream.php`): Instant byte-range seeking without downloading full multi-gigabyte video files.
   * **Clickable Timestamps & Chapters**: Automatically parses timestamps (e.g. `01:23 Intro`) in descriptions and jumps player directly to that second.
   * **Up Next / Related Queue**: Dynamic recommendations based on category and upload date.

2. **Universal CDN & Embed Engine**
   * **Responsive Iframe Embeds** (`embed.php`): Borderless embed player ready for embedding in external projects, portfolios, or blogs.
   * **Direct CDN URLs**: High-speed cached media delivery for `<video>`, `<img>`, and download links.
   * **One-Click Share Drawer**: Copy responsive Iframe codes, direct streaming links, HTML tags, or Markdown snippets.

3. **Dual-Mode Upload & Filesystem Sync**
   * **Browser Web Upload**: Upload small/medium files with drag & drop, progress bar, category selection, custom thumbnails, and tags.
   * **Direct File Drop / FTP & One-Click Sync**: For massive video files (100MB – 10GB+) or zip archives, drop files directly into `assets/videos/`, `assets/images/`, `assets/documents/`, `assets/archives/`, and click **"Scan & Sync Filesystem"** in Studio or run `php scripts/sync-assets.php`.
   * **Asset Management Studio**: Edit titles, descriptions, chapters, tags, and thumbnails in real time.

4. **Dedicated Media Vaults**
   * **Images & Comics Gallery** (`images.php`): High-resolution lightbox with zoom and direct CDN link generator.
   * **Documents & PDFs** (`documents.php`): File vault with inline PDF viewer modal and download counters.

---

## 🔐 Superadmin Credentials

* **URL**: `http://localhost/showcase/login.php`
* **Username**: `superadmin`
* **Password**: `ChangeMe@123` *(stored using secure Bcrypt password hash)*

---

## 🚀 Getting Started Locally (XAMPP)

1. **MySQL Database**:
   * Database: `showcase_db`
   * Import schema: `schema.sql` (already pre-loaded on local XAMPP).
2. **Accessing the App**:
   * Open: `http://localhost/showcase/`
   * Studio / Admin Panel: `http://localhost/showcase/studio.php`
3. **CLI Filesystem Sync**:
   ```bash
   php scripts/sync-assets.php
   ```

---

## 📁 Repository Structure

```
showcase/
├── api/
│   ├── delete-asset.php    # Asset deletion API
│   ├── like.php            # Video like incrementer
│   ├── sync.php            # AJAX filesystem / FTP sync trigger
│   ├── update-asset.php    # Metadata & chapters editor
│   └── upload.php          # Browser file uploader
├── assets/                 # (Ignored in Git) Local media storage
│   ├── archives/           # Zip bundles & archives
│   ├── documents/          # PDFs & office documents
│   ├── images/             # Artwork, photos, screenshots
│   ├── motion-comics/      # AI motion comic panels & art
│   ├── thumbnails/         # Generated & custom thumbnails
│   └── videos/             # MP4 & WebM video files
├── config/
│   ├── auth.php            # Session authentication middleware
│   └── database.php        # PDO database connection & helpers
├── css/
│   └── youtube.css         # YouTube dark theme & player styling
├── includes/
│   ├── footer.php          # Modals, toasts, and scripts
│   ├── header.php          # YouTube navbar & search
│   ├── sidebar.php         # Collapsible left navigation drawer
│   └── SyncManager.php     # Dual-mode synchronization engine
├── js/
│   ├── app.js              # UI toggles, toasts, and clipboard logic
│   └── yt-player.js        # Custom YouTube player controller
├── scripts/
│   └── sync-assets.php     # CLI sync runner
├── documents.php           # Document repository & PDF viewer
├── embed.php               # Standalone iframe embed player
├── images.php              # Image showcase & lightbox
├── index.php               # YouTube home feed & filters
├── login.php               # Superadmin login
├── logout.php              # Superadmin logout
├── schema.sql              # MySQL schema definition
├── stream.php              # HTTP 206 video streaming engine
├── studio.php              # Creator studio & asset manager
└── watch.php               # YouTube watch page
```

---

## 🔗 Embed Code Examples

### 1. Responsive Video Iframe Embed (YouTube Style)
```html
<iframe src="http://localhost/showcase/embed.php?v=VIDEO_ID" 
        width="100%" height="450" frameborder="0" 
        allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" 
        allowfullscreen>
</iframe>
```

### 2. High-Speed Direct Stream Tag
```html
<video src="http://localhost/showcase/stream.php?v=VIDEO_ID" controls></video>
```

### 3. Markdown Image / Comic Embed
```markdown
![Asset Title](http://localhost/showcase/assets/images/example.png)
```
