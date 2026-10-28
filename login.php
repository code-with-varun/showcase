<?php
/**
 * Superadmin Login (Light Theme)
 * Showcase Tube & Media CDN Vault
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

$baseUrl = getBaseUrl();
$redirect = $_GET['redirect'] ?? 'studio.php';
$error = '';

if (isAdminLoggedIn()) {
    header("Location: " . $baseUrl . '/' . ltrim($redirect, '/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter both username and password.';
    } elseif (loginAdmin($username, $password)) {
        header("Location: " . $baseUrl . '/' . ltrim($redirect, '/'));
        exit;
    } else {
        $error = 'Invalid credentials. Please verify your superadmin login.';
    }
}
?>
<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Superadmin Sign In - Showcase Tube</title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link rel="stylesheet" href="<?= $baseUrl ?>/css/youtube.css">
  <style>
    body {
      background-color: var(--yt-bg);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 1.5rem;
    }
    .login-card {
      max-width: 440px;
      width: 100%;
      background-color: #ffffff;
      border: 1px solid var(--yt-border);
      border-radius: 16px;
      padding: 2.5rem 2rem;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
    }
  </style>
</head>
<body data-bs-theme="light">

  <div class="login-card">
    <div class="text-center mb-4">
      <a href="<?= $baseUrl ?>/index.php" class="yt-brand justify-content-center mb-2">
        <span class="yt-logo-badge"><i class="bi bi-play-fill"></i></span>
        <span>SHOWCASE<span style="color: var(--yt-red);">TUBE</span></span>
      </a>
      <h4 class="text-dark mt-3 mb-1">Superadmin Access</h4>
      <p class="text-secondary small">Sign in to manage your asset repository, uploads, and CDN.</p>
    </div>

    <?php if ($error): ?>
      <div class="alert alert-danger py-2 small d-flex align-items-center gap-2 mb-3" role="alert">
        <i class="bi bi-exclamation-octagon-fill"></i>
        <div><?= h($error) ?></div>
      </div>
    <?php endif; ?>

    <form method="POST" action="">
      <div class="mb-3">
        <label for="username" class="form-label text-secondary small fw-bold">Username</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-person-fill"></i></span>
          <input type="text" class="form-control bg-light text-dark border-secondary-subtle" id="username" name="username" value="<?= h($_POST['username'] ?? 'superadmin') ?>" required autofocus>
        </div>
      </div>

      <div class="mb-4">
        <label for="password" class="form-label text-secondary small fw-bold">Password</label>
        <div class="input-group">
          <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-key-fill"></i></span>
          <input type="password" class="form-control bg-light text-dark border-secondary-subtle" id="password" name="password" required>
        </div>
      </div>

      <button type="submit" class="btn btn-danger w-100 py-2 fw-semibold d-flex align-items-center justify-content-center gap-2">
        <i class="bi bi-box-arrow-in-right"></i> Sign In to Studio
      </button>

      <div class="text-center mt-4">
        <a href="<?= $baseUrl ?>/index.php" class="text-secondary small text-decoration-none">
          <i class="bi bi-arrow-left"></i> Return to Public Showcase
        </a>
      </div>
    </form>
  </div>

</body>
</html>
