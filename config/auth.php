<?php
/**
 * Authentication & Session Management
 * Admin protection for Runit Showcase
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/database.php';

/**
 * Check if the current user is logged in as an admin
 */
function isAdminLoggedIn(): bool {
    return !empty($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

/**
 * Get current admin session details
 */
function getCurrentAdmin(): ?array {
    if (!isAdminLoggedIn()) return null;
    return [
        'id'       => $_SESSION['admin_id'] ?? null,
        'username' => $_SESSION['admin_username'] ?? 'superadmin',
        'role'     => $_SESSION['admin_role'] ?? 'superadmin',
    ];
}

/**
 * Attempt admin login
 */
function loginAdmin(string $username, string $password): bool {
    $pdo = getDB();
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => trim($username)]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password_hash'])) {
        // Regenerate session ID for security
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_id']        = $user['id'];
        $_SESSION['admin_username']  = $user['username'];
        $_SESSION['admin_role']      = $user['role'];

        // Update last login timestamp
        $update = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);

        return true;
    }

    return false;
}

/**
 * Logout admin
 */
function logoutAdmin(): void {
    $_SESSION = [];
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

/**
 * Enforce admin authentication on protected pages
 */
function requireAdmin(string $redirect = 'login.php'): void {
    if (!isAdminLoggedIn()) {
        $currentUri = $_SERVER['REQUEST_URI'] ?? 'studio.php';
        $loginUrl = getBaseUrl() . '/' . ltrim($redirect, '/') . '?redirect=' . urlencode($currentUri);
        header("Location: " . $loginUrl);
        exit;
    }
}
