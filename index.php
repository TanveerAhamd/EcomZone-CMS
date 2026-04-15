<?php
/**
 * INDEX - Main entry point for EcomZone CMS
 * Redirects to login if not authenticated, or to dashboard if authenticated
 */

require_once __DIR__ . '/includes/init.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    header('Location: /EcomZone-CMS/admin/dashboard.php');
    exit;
}

// If not logged in, redirect to login page
header('Location: /EcomZone-CMS/login.php');
exit;
?>
