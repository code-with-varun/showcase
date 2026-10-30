<?php
/**
 * Database Configuration & Global Helpers
 * Runit Showcase & Media CDN Vault
 */

// -----------------------------------------------------------------
// Environment detection: Hostinger / Live Server vs local XAMPP
// -----------------------------------------------------------------
$serverHost = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? '';
$hostNameOnly = strtolower(explode(':', $serverHost)[0]);
$isLocal = in_array($hostNameOnly, ['localhost', '127.0.0.1', '::1', '']) && empty(getenv('HOSTINGER'));

if (!$isLocal) {
    // Hostinger Live Database (defaults to localhost socket, fallback to 127.0.0.1 TCP)
    define('DB_HOST', 'localhost');
    define('DB_HOST_ALT', '127.0.0.1');
    define('DB_PORT', 3306);
    define('DB_NAME', 'u935057905_runit_ispark');
    define('DB_USER', 'u935057905_runit_ispark');
    define('DB_PASS', 'Maruthuvk@2026');
} else {
    // Local XAMPP
    define('DB_HOST', '127.0.0.1');
    define('DB_HOST_ALT', 'localhost');
    define('DB_PORT', 3306);
    define('DB_NAME', 'showcase_db');
    define('DB_USER', 'root');
    define('DB_PASS', '');
}

// Base root directory paths
define('ROOT_DIR', dirname(__DIR__));
define('ASSETS_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'assets');
define('THUMBNAILS_DIR', ASSETS_DIR . DIRECTORY_SEPARATOR . 'thumbnails');

/**
 * Visibility constants:
 * VISIBILITY_PRIVATE   = 0 — admin only
 * VISIBILITY_UNLISTED  = 1 — anyone with link
 * VISIBILITY_PUBLIC    = 2 — visible in public feed
 */
define('VISIBILITY_PRIVATE',  0);
define('VISIBILITY_UNLISTED', 1);
define('VISIBILITY_PUBLIC',   2);

/**
 * Get PDO Database Connection (Singleton with host fallback)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
            PDO::ATTR_TIMEOUT            => 5,
        ];
        
        $hosts = array_unique([DB_HOST, DB_HOST_ALT]);
        $lastException = null;

        foreach ($hosts as $host) {
            try {
                $dsn = "mysql:host=" . $host . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
                $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
                ensureAdminUser($pdo);
                return $pdo;
            } catch (PDOException $e) {
                $lastException = $e;
            }
        }

        die("Database connection failed: " . htmlspecialchars($lastException ? $lastException->getMessage() : 'Unknown error'));
    }
    return $pdo;
}

/**
 * Ensure Superadmin exists with password 'ChangeMe@123' if no user exists
 */
function ensureAdminUser(PDO $pdo): void {
    try {
        $count = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
        if ($count === 0) {
            $hash = password_hash('ChangeMe@123', PASSWORD_BCRYPT);
            $insert = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('superadmin', :hash, 'superadmin')");
            $insert->execute([':hash' => $hash]);
        }
    } catch (Exception $e) {
        // Table may not be created yet during first-time migration
    }
}

/**
 * Dynamically determine the application base URL
 * Fully supports SSL reverse proxies (Cloudflare, Hostinger, Nginx)
 */
function getBaseUrl(): string {
    if (defined('CUSTOM_BASE_URL') && CUSTOM_BASE_URL) {
        return rtrim(CUSTOM_BASE_URL, '/');
    }
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
        || (isset($_SERVER['HTTP_X_FORWARDED_SSL']) && strtolower($_SERVER['HTTP_X_FORWARDED_SSL']) === 'on');
    $scheme = $isHttps ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Check script directory relative to document root
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '\\') {
        $dir = '';
    } else {
        // Remove subfolders like /api or /includes or /scripts if called from there
        $dir = preg_replace('#/(api|includes|scripts)$#', '', $dir);
    }
    return rtrim($scheme . '://' . $host . $dir, '/');
}

/**
 * Format bytes into human-readable string (KB, MB, GB)
 */
function formatBytes(int|float $bytes, int $decimals = 2): string {
    if ($bytes <= 0) return '0 B';
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = (int) floor(log($bytes, 1024));
    return round($bytes / pow(1024, $i), $decimals) . ' ' . $units[$i];
}

/**
 * Format seconds into YouTube-style duration (e.g. 03:45 or 1:12:30)
 */
function formatDuration(int $seconds): string {
    if ($seconds <= 0) return '0:00';
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $sec = $seconds % 60;
    if ($hours > 0) {
        return sprintf('%d:%02d:%02d', $hours, $minutes, $sec);
    }
    return sprintf('%d:%02d', $minutes, $sec);
}

/**
 * Human-friendly relative time (e.g., '2 hours ago', 'Yesterday')
 */
function timeAgo(string $datetime): string {
    $timestamp = strtotime($datetime);
    if (!$timestamp) return 'Recently';
    $difference = time() - $timestamp;
    if ($difference < 60) return 'Just now';
    if ($difference < 3600) return floor($difference / 60) . ' mins ago';
    if ($difference < 86400) return floor($difference / 3600) . ' hours ago';
    if ($difference < 604800) return floor($difference / 86400) . ' days ago';
    if ($difference < 2592000) return floor($difference / 604800) . ' weeks ago';
    if ($difference < 31536000) return floor($difference / 2592000) . ' months ago';
    return floor($difference / 31536000) . ' years ago';
}

/**
 * Sanitize output for HTML
 */
function h(string|null $str): string {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Get visibility label and badge class for display
 */
function getVisibilityLabel(int $visibility): array {
    return match($visibility) {
        VISIBILITY_PRIVATE  => ['label' => 'Private',  'badge' => 'bg-danger-subtle text-danger',   'icon' => 'bi-lock-fill'],
        VISIBILITY_UNLISTED => ['label' => 'Unlisted', 'badge' => 'bg-warning-subtle text-warning', 'icon' => 'bi-link-45deg'],
        VISIBILITY_PUBLIC   => ['label' => 'Public',   'badge' => 'bg-success-subtle text-success', 'icon' => 'bi-globe2'],
        default             => ['label' => 'Public',   'badge' => 'bg-success-subtle text-success', 'icon' => 'bi-globe2'],
    };
}

/**
 * Get metadata styling, icons, and descriptions for video project/folder sections
 */
function getVideoProjectMeta(string $pName): array {
    $lower = strtolower($pName);
    if (str_contains($lower, 'remix')) {
        return [
            'icon'  => 'bi-disc-fill text-danger',
            'badge' => 'Music & Edits',
            'bg'    => 'bg-danger-subtle',
            'desc'  => 'High-energy music video remixes & beat-synced edits'
        ];
    }
    if (str_contains($lower, 'chalkboard')) {
        return [
            'icon'  => 'bi-mortarboard-fill text-primary',
            'badge' => 'Tutorials & Guides',
            'bg'    => 'bg-primary-subtle',
            'desc'  => 'Educational & instructional screen tutorials'
        ];
    }
    if (str_contains($lower, 'lavish')) {
        return [
            'icon'  => 'bi-mic-fill text-warning-emphasis',
            'badge' => 'Interviews & Shows',
            'bg'    => 'bg-warning-subtle',
            'desc'  => 'Interviews, talk shows & creative spotlight features'
        ];
    }
    if (str_contains($lower, 'short')) {
        return [
            'icon'  => 'bi-film text-info-emphasis',
            'badge' => 'Cinematic Shorts',
            'bg'    => 'bg-info-subtle',
            'desc'  => 'Original short cinematic films & creative narratives'
        ];
    }
    if (str_contains($lower, 'varun') || str_contains($lower, 'kiruthiga') || str_contains($lower, 'wedding')) {
        return [
            'icon'  => 'bi-heart-fill text-danger',
            'badge' => 'Cinematic Stories',
            'bg'    => 'bg-danger-subtle',
            'desc'  => 'Wedding & special event cinematic story series'
        ];
    }
    return [
        'icon'  => 'bi-folder2-open text-danger',
        'badge' => 'Video Collection',
        'bg'    => 'bg-light',
        'desc'  => 'Video collection & media assets'
    ];
}
