<?php
/**
 * Superadmin Settings & Profile Security
 * Change Username & Password for Runit Showcase
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/config/auth.php';

requireAdmin();

$pdo = getDB();
$baseUrl = getBaseUrl();
$currentAdmin = getCurrentAdmin();

// Fetch current user record from database
$adminId = $_SESSION['admin_id'] ?? null;
if ($adminId) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $adminId]);
    $user = $stmt->fetch();
} else {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :u LIMIT 1");
    $stmt->execute([':u' => $_SESSION['admin_username'] ?? 'superadmin']);
    $user = $stmt->fetch();
}

if (!$user) {
    die("User record not found.");
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newUsername     = trim($_POST['username'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword     = $_POST['new_password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';

    // Verify current password first
    if (empty($currentPassword)) {
        $error = 'Please enter your current password to authorize changes.';
    } elseif (!password_verify($currentPassword, $user['password_hash'])) {
        $error = 'The current password you entered is incorrect.';
    } elseif (empty($newUsername)) {
        $error = 'Username cannot be blank.';
    } else {
        // Check if username is changed and already taken by another user
        if ($newUsername !== $user['username']) {
            $checkStmt = $pdo->prepare("SELECT id FROM users WHERE username = :u AND id != :id LIMIT 1");
            $checkStmt->execute([':u' => $newUsername, ':id' => $user['id']]);
            if ($checkStmt->fetch()) {
                $error = 'This username is already in use. Please choose another.';
            }
        }

        if (!$error) {
            $passwordChanged = false;
            if (!empty($newPassword) || !empty($confirmPassword)) {
                if (strlen($newPassword) < 6) {
                    $error = 'New password must be at least 6 characters long.';
                } elseif ($newPassword !== $confirmPassword) {
                    $error = 'New password and confirmation do not match.';
                } else {
                    $newHash = password_hash($newPassword, PASSWORD_BCRYPT);
                    $updateStmt = $pdo->prepare("UPDATE users SET username = :u, password_hash = :p WHERE id = :id");
                    $updateStmt->execute([
                        ':u'  => $newUsername,
                        ':p'  => $newHash,
                        ':id' => $user['id']
                    ]);
                    $passwordChanged = true;
                    // Refresh hash in local record
                    $user['password_hash'] = $newHash;
                }
            } else {
                // Only updating username
                $updateStmt = $pdo->prepare("UPDATE users SET username = :u WHERE id = :id");
                $updateStmt->execute([
                    ':u'  => $newUsername,
                    ':id' => $user['id']
                ]);
            }

            if (!$error) {
                // Update active session
                $_SESSION['admin_username'] = $newUsername;
                $user['username'] = $newUsername;
                if ($passwordChanged) {
                    $success = 'Your password and profile have been updated successfully!';
                } else {
                    $success = 'Your username has been updated successfully!';
                }
            }
        }
    }
}

$pageTitle = "Settings & Security";
include __DIR__ . '/includes/header.php';
?>

<div class="container-fluid px-0">

  <!-- Breadcrumb & Top Bar -->
  <div class="d-flex flex-wrap align-items-center justify-content-between gap-3 mb-4 pb-3 border-bottom border-light-subtle">
    <div>
      <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-1 small">
          <li class="breadcrumb-item"><a href="<?= $baseUrl ?>/studio.php" class="text-danger text-decoration-none">Creator Studio</a></li>
          <li class="breadcrumb-item active" aria-current="page">Settings & Security</li>
        </ol>
      </nav>
      <h1 class="h3 text-dark mb-1 d-flex align-items-center gap-2">
        <i class="bi bi-shield-lock-fill text-danger"></i>
        <span>Settings & Security Profile</span>
      </h1>
      <p class="text-secondary small mb-0">Manage your superadmin credentials, login username, and password.</p>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-secondary d-flex align-items-center gap-1">
        <i class="bi bi-cpu me-1"></i> Creator Studio
      </a>
      <a href="<?= $baseUrl ?>/index.php" class="btn btn-outline-dark d-flex align-items-center gap-1">
        <i class="bi bi-eye me-1"></i> View Showcase
      </a>
    </div>
  </div>

  <!-- Success Notification Alert -->
  <?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
      <i class="bi bi-check-circle-fill fs-5 text-success"></i>
      <div><strong>Success!</strong> <?= h($success) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <!-- Error Notification Alert -->
  <?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show d-flex align-items-center gap-2 shadow-sm mb-4" role="alert">
      <i class="bi bi-exclamation-triangle-fill fs-5 text-danger"></i>
      <div><strong>Error:</strong> <?= h($error) ?></div>
      <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
  <?php endif; ?>

  <div class="row g-4">
    <!-- Left Column: Change Password & Profile Form -->
    <div class="col-lg-7 col-xl-8">
      <div class="card bg-white border-light-subtle shadow-sm">
        <div class="card-header bg-white border-bottom border-light-subtle py-3">
          <h5 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-key-fill text-danger"></i>
            <span>Change Admin Password & Profile</span>
          </h5>
        </div>
        <div class="card-body p-4">
          <form action="<?= $baseUrl ?>/settings.php" method="POST" id="passwordForm">

            <!-- Username Field -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Admin Username <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-person-fill"></i></span>
                <input type="text" name="username" class="form-control border-secondary-subtle" value="<?= h($user['username']) ?>" required>
              </div>
              <div class="form-text text-secondary" style="font-size: 0.78rem;">The username used to sign in to the administrative panel.</div>
            </div>

            <hr class="my-4 border-light-subtle">

            <!-- Current Password Field -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">Current Password <span class="text-danger">*</span></label>
              <div class="input-group">
                <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-lock-fill"></i></span>
                <input type="password" name="current_password" id="currentPasswordInput" class="form-control border-secondary-subtle" placeholder="Enter your current password" required autocomplete="current-password">
                <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="currentPasswordInput" title="Toggle visibility">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text text-secondary" style="font-size: 0.78rem;">Required to authorize any credential changes.</div>
            </div>

            <!-- New Password Field -->
            <div class="mb-3">
              <label class="form-label small fw-bold text-dark">New Password</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-shield-lock-fill"></i></span>
                <input type="password" name="new_password" id="newPasswordInput" class="form-control border-secondary-subtle" placeholder="Minimum 6 characters" minlength="6" autocomplete="new-password">
                <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="newPasswordInput" title="Toggle visibility">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div class="form-text text-secondary" style="font-size: 0.78rem;">Leave blank if you only want to change your username.</div>
            </div>

            <!-- Confirm New Password Field -->
            <div class="mb-4">
              <label class="form-label small fw-bold text-dark">Confirm New Password</label>
              <div class="input-group">
                <span class="input-group-text bg-light border-secondary-subtle text-secondary"><i class="bi bi-shield-check"></i></span>
                <input type="password" name="confirm_password" id="confirmPasswordInput" class="form-control border-secondary-subtle" placeholder="Confirm your new password" minlength="6" autocomplete="new-password">
                <button class="btn btn-outline-secondary toggle-pwd-btn" type="button" data-target="confirmPasswordInput" title="Toggle visibility">
                  <i class="bi bi-eye"></i>
                </button>
              </div>
              <div id="passwordMismatchNote" class="text-danger small mt-1 d-none">
                <i class="bi bi-x-circle me-1"></i> Passwords do not match.
              </div>
            </div>

            <!-- Submit Button Row -->
            <div class="d-flex align-items-center gap-2 pt-2">
              <button type="submit" class="btn btn-danger px-4 py-2 fw-semibold d-flex align-items-center gap-2" id="saveCredentialsBtn">
                <i class="bi bi-check2-circle"></i>
                <span>Update Credentials</span>
              </button>
              <a href="<?= $baseUrl ?>/studio.php" class="btn btn-outline-secondary px-3 py-2">
                Cancel
              </a>
            </div>

          </form>
        </div>
      </div>
    </div>

    <!-- Right Column: Account Security & Information Cards -->
    <div class="col-lg-5 col-xl-4">
      
      <!-- Account Overview Card -->
      <div class="card bg-white border-light-subtle shadow-sm mb-4">
        <div class="card-header bg-white border-bottom border-light-subtle py-3">
          <h6 class="fw-bold text-dark mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-person-badge-fill text-danger"></i>
            <span>Account Status</span>
          </h6>
        </div>
        <div class="card-body p-3">
          <div class="d-flex align-items-center gap-3 mb-3 pb-3 border-bottom border-light-subtle">
            <div class="yt-comment-avatar" style="background: linear-gradient(135deg, #d6111a, #ff4d4f); width: 48px; height: 48px; font-size: 1.2rem;">
              <i class="bi bi-shield-fill"></i>
            </div>
            <div>
              <div class="fw-bold text-dark"><?= h($user['username']) ?></div>
              <span class="badge bg-danger-subtle text-danger border border-danger-subtle small">Master Superadmin</span>
            </div>
          </div>

          <div class="d-flex flex-column gap-2 small">
            <div class="d-flex justify-content-between text-secondary">
              <span>Account Role:</span>
              <strong class="text-dark"><?= ucfirst(h($user['role'])) ?></strong>
            </div>
            <div class="d-flex justify-content-between text-secondary">
              <span>Encryption:</span>
              <strong class="text-success"><i class="bi bi-lock-fill me-1"></i>BCRYPT Salted</strong>
            </div>
            <div class="d-flex justify-content-between text-secondary">
              <span>Last Login:</span>
              <span class="text-dark font-monospace"><?= $user['last_login'] ? date('M j, Y - g:i A', strtotime($user['last_login'])) : 'Current Session' ?></span>
            </div>
            <div class="d-flex justify-content-between text-secondary">
              <span>Member Since:</span>
              <span class="text-dark"><?= $user['created_at'] ? date('M j, Y', strtotime($user['created_at'])) : '2026' ?></span>
            </div>
          </div>
        </div>
      </div>

      <!-- Security Best Practice Notice -->
      <div class="card bg-light border-light-subtle shadow-sm p-3">
        <div class="d-flex gap-2">
          <i class="bi bi-info-circle-fill text-primary fs-5 flex-shrink-0 mt-1"></i>
          <div>
            <h6 class="fw-bold text-dark mb-1">Single Admin Security</h6>
            <p class="text-secondary small mb-0" style="line-height: 1.45;">
              Runit Showcase is designed for single administrator access. This password protects file uploads, editing metadata, deleting assets, and triggering filesystem syncs.
            </p>
          </div>
        </div>
      </div>

    </div>
  </div>

</div>

<script>
// Show/Hide password toggle buttons
document.querySelectorAll('.toggle-pwd-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const targetId = btn.getAttribute('data-target');
    const input = document.getElementById(targetId);
    if (!input) return;

    const icon = btn.querySelector('i');
    if (input.type === 'password') {
      input.type = 'text';
      if (icon) icon.className = 'bi bi-eye-slash';
    } else {
      input.type = 'password';
      if (icon) icon.className = 'bi bi-eye';
    }
  });
});

// Real-time password confirmation match check
const newPwd = document.getElementById('newPasswordInput');
const confirmPwd = document.getElementById('confirmPasswordInput');
const mismatchNote = document.getElementById('passwordMismatchNote');
const saveBtn = document.getElementById('saveCredentialsBtn');

function validatePasswordsMatch() {
  if (!newPwd || !confirmPwd) return;
  const nVal = newPwd.value;
  const cVal = confirmPwd.value;

  if (cVal && nVal !== cVal) {
    mismatchNote.classList.remove('d-none');
    confirmPwd.classList.add('is-invalid');
  } else {
    mismatchNote.classList.add('d-none');
    confirmPwd.classList.remove('is-invalid');
  }
}

if (newPwd && confirmPwd) {
  newPwd.addEventListener('input', validatePasswordsMatch);
  confirmPwd.addEventListener('input', validatePasswordsMatch);
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
