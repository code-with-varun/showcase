<?php
/**
 * Database Configuration & Global Helpers
 * Runit Showcase & Media CDN Vault
 */

// Database credentials for XAMPP
define('DB_HOST', '127.0.0.1');
define('DB_PORT', 3306);
define('DB_NAME', 'showcase_db');
define('DB_USER', 'root');
define('DB_PASS', '');

// Base root directory paths
define('ROOT_DIR', dirname(__DIR__));
define('ASSETS_DIR', ROOT_DIR . DIRECTORY_SEPARATOR . 'assets');
define('THUMBNAILS_DIR', ASSETS_DIR . DIRECTORY_SEPARATOR . 'thumbnails');

/**
 * Get PDO Database Connection (Singleton)
 */
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=utf8mb4";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => true,
        ];
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
            ensureAdminUser($pdo);
        } catch (PDOException $e) {
            die("Database connection failed: " . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}

/**
 * Ensure Superadmin exists with password 'ChangeMe@123'
 */
function ensureAdminUser(PDO $pdo): void {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE username = 'superadmin' LIMIT 1");
    $stmt->execute();
    if (!$stmt->fetch()) {
        $hash = password_hash('ChangeMe@123', PASSWORD_BCRYPT);
        $insert = $pdo->prepare("INSERT INTO users (username, password_hash, role) VALUES ('superadmin', :hash, 'superadmin')");
        $insert->execute([':hash' => $hash]);
    }
}

/**
 * Dynamically determine the application base URL
 */
function getBaseUrl(): string {
    if (defined('CUSTOM_BASE_URL') && CUSTOM_BASE_URL) {
        return rtrim(CUSTOM_BASE_URL, '/');
    }
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Check script directory relative to document root
    $scriptName = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    $dir = dirname($scriptName);
    if ($dir === '/' || $dir === '\\') {
        $dir = '';
    } else {
        // Remove subfolders like /api or /includes if called from there
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
