<?php
/**
 * Redirect to unified 3-category showcase view
 */
require_once __DIR__ . '/config/database.php';
header("Location: " . getBaseUrl() . "/index.php?type=docs_zips");
exit;
