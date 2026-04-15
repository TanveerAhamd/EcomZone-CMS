<?php
/**
 * LOGOUT PAGE
 * Clear session and redirect to login
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/functions.php';

startSession();

if (isLoggedIn()) {
    logActivity('LOGOUT', 'auth', currentUser()['id'], 'User logged out');
}

// Destroy session
$_SESSION = [];
session_destroy();

// Delete remember token cookie
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/', '', true, true);
}

// Redirect to login
redirect('login.php');
