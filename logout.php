<?php
/**
 * Logout Superadmin
 */

require_once __DIR__ . '/config/auth.php';

logoutAdmin();
header("Location: " . getBaseUrl() . "/index.php");
exit;
