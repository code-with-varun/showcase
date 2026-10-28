<?php
/**
 * SyncManager Engine
 * Dual-Mode Synchronization: Scans filesystem/FTP drops and syncs with MySQL
 */

require_once __DIR__ . '/../config/database.php';

class SyncManager {
    private PDO $pdo;
    private string $assetsDir;

    public function __construct(?PDO $pdo = null, ?string $assetsDir = null) {
        $this->pdo = $pdo ?? getDB();
        $this->assetsDir = $assetsDir ?? ASSETS_DIR;
    }

    /**
     * Run full sync between filesystem and MySQL
     */
    public function sync(): array {
        $stats = [
            'scanned' => 0,
            'added'   => 0,
            'updated' => 0,
            'missing' => 0,
            'details' => []
        ];

        if (!is_dir($this->assetsDir)) {
            mkdir($this->assetsDir, 0777, true);
        }

        // Fetch all categories mapped by slug
        $categories = $this->loadCategories();

        // 1. Scan filesystem
        $filesOnDisk = $this->scanDirectory($this->assetsDir);
        $stats['scanned'] = count($filesOnDisk);

        // Fetch all existing file paths from MySQL
        $stmt = $this->pdo->query("SELECT id, file_path, file_size, project FROM assets");
        $existingAssets = [];
        while ($row = $stmt->fetch()) {
            $normalized = str_replace('\\', '/', $row['file_path']);
            $existingAssets[$normalized] = $row;
        }

        $seenPaths = [];

        foreach ($filesOnDisk as $file) {
            $relativePath = str_replace('\\', '/', $file['relative_path']);
            $seenPaths[$relativePath] = true;

            $ext = strtolower(pathinfo($file['filename'], PATHINFO_EXTENSION));
            $assetType = $this->determineAssetType($ext);
            $categoryId = $this->resolveCategoryId($relativePath, $assetType, $categories);
            $project = $this->detectProject($relativePath, $assetType);

            if (isset($existingAssets[$relativePath])) {
                // Already in database -> check if size changed or project needs backfilling
                $existing = $existingAssets[$relativePath];
                $needsUpdate = false;
                $updateFields = [];
                $updateParams = [':id' => $existing['id']];

                if ((int)$existing['file_size'] !== $file['size']) {
                    $updateFields[] = "file_size = :size";
                    $updateParams[':size'] = $file['size'];
                    $needsUpdate = true;
                }
                if (empty($existing['project']) && $project) {
                    $updateFields[] = "project = :project";
                    $updateParams[':project'] = $project;
                    $needsUpdate = true;
                }

                if ($needsUpdate) {
                    $updateFields[] = "updated_at = NOW()";
                    $setSql = implode(', ', $updateFields);
                    $updateStmt = $this->pdo->prepare("UPDATE assets SET $setSql WHERE id = :id");
                    $updateStmt->execute($updateParams);
                    $stats['updated']++;
                    $stats['details'][] = "Updated: {$relativePath} (" . formatBytes($file['size']) . ")";
                }
            } else {
                // New file detected -> Insert into MySQL
                $id = $this->generateUniqueId();
                $title = $this->formatTitle($file['filename']);
                $slug = $this->slugify($title) . '-' . substr($id, 0, 4);
                $mimeType = $this->detectMimeType($file['full_path'], $ext);
                $thumbnailPath = $this->generateThumbnailPath($assetType, $relativePath, $id, $file['full_path']);

                $insertStmt = $this->pdo->prepare("
                    INSERT INTO assets (
                        id, title, slug, description, asset_type, category_id, project,
                        file_path, file_name, file_size, mime_type, extension,
                        thumbnail_path, source, is_public, created_at, updated_at
                    ) VALUES (
                        :id, :title, :slug, :desc, :type, :cat_id, :project,
                        :path, :filename, :size, :mime, :ext,
                        :thumb, 'ftp_sync', 1, NOW(), NOW()
                    )
                ");

                $insertStmt->execute([
                    ':id'       => $id,
                    ':title'    => $title,
                    ':slug'     => $slug,
                    ':desc'     => "Cataloged asset: {$title}",
                    ':type'     => $assetType,
                    ':cat_id'   => $categoryId,
                    ':project'  => $project,
                    ':path'     => $relativePath,
                    ':filename' => $file['filename'],
                    ':size'     => $file['size'],
                    ':mime'     => $mimeType,
                    ':ext'      => $ext,
                    ':thumb'    => $thumbnailPath,
                ]);

                // Add default tags
                $tags = [$ext, strtoupper($assetType), 'Synced'];
                if ($project) $tags[] = $project;
                $this->addTags($id, $tags);

                $stats['added']++;
                $stats['details'][] = "Added: {$title} ({$relativePath})";
            }
        }

        // 2. Identify missing files that were deleted from disk
        foreach ($existingAssets as $path => $data) {
            if (!isset($seenPaths[$path]) && !file_exists(ROOT_DIR . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path))) {
                // File deleted from disk -> clean from database
                $delStmt = $this->pdo->prepare("DELETE FROM assets WHERE id = :id");
                $delStmt->execute([':id' => $data['id']]);
                $stats['missing']++;
                $stats['details'][] = "Removed deleted file: {$path}";
            }
        }

        return $stats;
    }

    /**
     * Recursively scan directory for assets (excluding thumbnails and dotfiles)
     */
    private function scanDirectory(string $dir, string $baseDir = ''): array {
        $files = [];
        if ($baseDir === '') $baseDir = dirname($dir);

        $items = @scandir($dir);
        if ($items === false) return $files;

        foreach ($items as $item) {
            if ($item === '.' || $item === '..' || str_starts_with($item, '.')) continue;

            $fullPath = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($fullPath)) {
                // Ignore thumbnails folder
                if (strtolower($item) === 'thumbnails' || strtolower($item) === 'cache') continue;
                $files = array_merge($files, $this->scanDirectory($fullPath, $baseDir));
            } elseif (is_file($fullPath)) {
                $relPath = ltrim(str_replace($baseDir, '', $fullPath), '/\\');
                $files[] = [
                    'filename'      => $item,
                    'full_path'     => $fullPath,
                    'relative_path' => str_replace('\\', '/', $relPath),
                    'size'          => filesize($fullPath) ?: 0,
                    'mtime'         => filemtime($fullPath) ?: time(),
                ];
            }
        }
        return $files;
    }

    /**
     * Determine asset type from extension
     */
    public function determineAssetType(string $ext): string {
        $ext = strtolower($ext);
        $videoExts = ['mp4', 'webm', 'mov', 'mkv', 'avi', 'm4v', 'flv', 'wmv'];
        $imageExts = ['png', 'jpg', 'jpeg', 'webp', 'gif', 'svg', 'avif', 'bmp', 'tiff'];
        $docExts   = ['pdf', 'doc', 'docx', 'txt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx', 'md', 'rtf'];
        $archExts  = ['zip', 'rar', '7z', 'tar', 'gz', 'bz2', 'iso'];

        if (in_array($ext, $videoExts)) return 'video';
        if (in_array($ext, $imageExts)) return 'image';
        if (in_array($ext, $docExts))   return 'document';
        if (in_array($ext, $archExts))  return 'archive';
        return 'document';
    }

    /**
     * Load categories into an associative array [slug => id]
     */
    private function loadCategories(): array {
        $stmt = $this->pdo->query("SELECT id, slug FROM categories");
        $cats = [];
        while ($row = $stmt->fetch()) {
            $cats[$row['slug']] = (int)$row['id'];
        }
        return $cats;
    }

    /**
     * Resolve category ID based on path and asset type
     */
    private function resolveCategoryId(string $path, string $type, array $categories): ?int {
        $pathLower = strtolower($path);
        if (str_contains($pathLower, 'motion-comic')) {
            return $categories['motion-comics'] ?? ($categories['videos'] ?? null);
        }
        if (str_contains($pathLower, 'video')) {
            return $categories['videos'] ?? null;
        }
        if (str_contains($pathLower, 'image') || $type === 'image') {
            return $categories['images'] ?? null;
        }
        if (str_contains($pathLower, 'document') || $type === 'document') {
            return $categories['documents'] ?? null;
        }
        if (str_contains($pathLower, 'archive') || $type === 'archive') {
            return $categories['archives'] ?? null;
        }
        return null;
    }

    /**
     * Auto-detect project group name from relative filesystem path
     */
    public function detectProject(string $relativePath, string $type): ?string {
        $normalized = str_replace('\\', '/', $relativePath);
        $parts = explode('/', trim($normalized, '/'));

        // e.g. assets/images/scripvault/screencapture...png
        if (count($parts) >= 3 && $parts[0] === 'assets') {
            if ($parts[1] === 'images' && count($parts) > 3) {
                $folder = $parts[2];
                if (strtolower($folder) === 'scripvault') {
                    return 'ScripVault';
                }
                return $this->formatProjectName($folder);
            }
            if ($parts[1] === 'motion-comics') {
                return 'Motion Comics';
            }
            // For other asset types with subfolders (e.g. assets/videos/{project}/...)
            if (count($parts) > 3) {
                return $this->formatProjectName($parts[2]);
            }
        }

        if (stripos($normalized, 'scripvault') !== false) {
            return 'ScripVault';
        }
        if (stripos($normalized, 'motion-comic') !== false) {
            return 'Motion Comics';
        }

        return null;
    }

    /**
     * Clean folder string into project title
     */
    public function formatProjectName(string $folder): string {
        $clean = preg_replace('/[_\-]+/', ' ', $folder);
        return ucwords(trim($clean));
    }

    /**
     * Generate human-friendly title from filename
     */
    public function formatTitle(string $filename): string {
        $name = pathinfo($filename, PATHINFO_FILENAME);

        // Handle browser screencaptures (e.g. screencapture-localhost-3000-dashboard-2026...)
        if (stripos($name, 'screencapture') !== false) {
            if (preg_match('/screencapture-localhost-\d+-(.+?)-\d{4}-\d{2}-\d{2}/i', $name, $m)) {
                return "ScripVault — " . ucwords(str_replace(['_', '-'], ' ', $m[1]));
            }
            return "ScripVault — Platform View";
        }

        // Replace dashes and underscores with spaces
        $clean = preg_replace('/[_\-]+/', ' ', $name);
        $clean = trim(preg_replace('/\s+/', ' ', $clean));

        // If purely numbers or hash, format cleanly
        if (preg_match('/^\d{10,}$/', $clean)) {
            return "Story Panel " . substr($clean, -4);
        }
        if (str_starts_with(strtolower($clean), 'file 00000000')) {
            return "Graphic Panel " . substr($clean, -6);
        }
        return ucwords($clean);
    }

    /**
     * Slugify title
     */
    public function slugify(string $text): string {
        $text = preg_replace('~[^\pL\d]+~u', '-', $text);
        $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
        $text = preg_replace('~[^-\w]+~', '', $text);
        $text = trim($text, '-');
        $text = preg_replace('~-+~', '-', $text);
        return strtolower($text) ?: 'asset';
    }

    /**
     * Generate a 11-character YouTube-style alphanumeric unique ID
     */
    public function generateUniqueId(): string {
        $chars = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ-_';
        $id = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < 11; $i++) {
            $id .= $chars[random_int(0, $max)];
        }
        return $id;
    }

    /**
     * Guess MIME type with fallback mapping
     */
    public function detectMimeType(string $filePath, string $ext): string {
        if (function_exists('mime_content_type') && file_exists($filePath)) {
            $mime = @mime_content_type($filePath);
            if ($mime && $mime !== 'text/plain' && $mime !== 'application/octet-stream') {
                return $mime;
            }
        }
        $mimeMap = [
            'mp4' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime', 'mkv' => 'video/x-matroska',
            'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml',
            'pdf' => 'application/pdf', 'txt' => 'text/plain', 'csv' => 'text/csv',
            'zip' => 'application/zip', 'rar' => 'application/x-rar-compressed', '7z' => 'application/x-7z-compressed',
        ];
        return $mimeMap[strtolower($ext)] ?? 'application/octet-stream';
    }

    /**
     * Generate or assign thumbnail path
     */
    private function generateThumbnailPath(string $assetType, string $relPath, string $id, string $fullPath): ?string {
        if ($assetType === 'image') {
            return $relPath; // Image itself is its own thumbnail
        }

        // For video: check if matching jpg/png exists
        $info = pathinfo($fullPath);
        $candidateJpg = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.jpg';
        $candidatePng = $info['dirname'] . DIRECTORY_SEPARATOR . $info['filename'] . '.png';
        if (file_exists($candidateJpg)) {
            return ltrim(str_replace(ROOT_DIR, '', $candidateJpg), '/\\');
        }
        if (file_exists($candidatePng)) {
            return ltrim(str_replace(ROOT_DIR, '', $candidatePng), '/\\');
        }

        return null; // Will fallback to SVG/Canvas thumbnail
    }

    /**
     * Add tags for an asset
     */
    private function addTags(string $assetId, array $tags): void {
        $stmt = $this->pdo->prepare("INSERT INTO asset_tags (asset_id, tag) VALUES (:id, :tag)");
        foreach ($tags as $tag) {
            $tag = trim($tag);
            if (!empty($tag)) {
                $stmt->execute([':id' => $assetId, ':tag' => $tag]);
            }
        }
    }
}
