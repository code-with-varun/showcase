<?php
/**
 * Automated Hostinger FTP Deployer
 * Uploads all codebase files to /showcase, leaving assets/ untouched.
 */

$host = 'ftp.runito.in';
$port = 21;
$user = 'u935057905.runit';
$pass = 'Maruthuvk@2026';
$remoteBase = '/showcase';

echo "=== Connecting to Hostinger FTP ($host:$port) ===\n";
$conn = @ftp_connect($host, $port, 20);
if (!$conn) {
    die("FATAL: Failed to connect to $host\n");
}

if (!@ftp_login($conn, $user, $pass)) {
    die("FATAL: FTP login failed for $user\n");
}

ftp_pasv($conn, true);
echo "FTP Login Successful!\n";

$projectRoot = dirname(__DIR__);

// Helper to ensure remote directory exists
function ensureRemoteDir($conn, $dir) {
    $parts = explode('/', trim($dir, '/'));
    $current = '';
    foreach ($parts as $part) {
        $current .= '/' . $part;
        if (!@ftp_chdir($conn, $current)) {
            if (@ftp_mkdir($conn, $current)) {
                echo "Created remote directory: $current\n";
            }
        }
    }
}

// Files to deploy (relative to project root)
$filesToDeploy = [
    '.htaccess',
    'index.php',
    'playlist.php',
    'watch.php',
    'studio.php',
    'settings.php',
    'stream.php',
    'embed.php',
    'login.php',
    'logout.php',
    'migrate.php',
    'schema.sql',
    'config/database.php',
    'config/auth.php',
    'includes/header.php',
    'includes/sidebar.php',
    'includes/footer.php',
    'includes/SyncManager.php',
    'api/playlists.php',
    'api/upload.php',
    'api/update-asset.php',
    'api/delete-asset.php',
    'api/reset.php',
    'api/sync.php',
    'api/like.php',
    'css/youtube.css',
    'js/app.js'
];

$successCount = 0;
$failCount = 0;

foreach ($filesToDeploy as $relFile) {
    $localPath = $projectRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relFile);
    $remotePath = $remoteBase . '/' . $relFile;
    $remoteDir = dirname($remotePath);

    if (!file_exists($localPath)) {
        echo "[SKIP] Local file not found: $relFile\n";
        continue;
    }

    ensureRemoteDir($conn, $remoteDir);

    echo "Uploading $relFile -> $remotePath ... ";
    if (ftp_put($conn, $remotePath, $localPath, FTP_BINARY)) {
        echo "OK\n";
        $successCount++;
    } else {
        echo "FAILED\n";
        $failCount++;
    }
}

ftp_close($conn);
echo "\n=== Deployment Completed: $successCount succeeded, $failCount failed ===\n";
