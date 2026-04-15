<?php
/**
 * Application Bootstrap - Initialize all dependencies
 * This file should be included at the top of every page
 */

// Start session
session_start();

// Load configuration
require_once __DIR__ . '/../config/config.php';

// Load database connection
require_once __DIR__ . '/../config/database.php';

// Load helper functions
require_once __DIR__ . '/functions.php';

// Set timezone
date_default_timezone_set(TIMEZONE);

// Start login session management
startSession();

// CSRF token generation for all pages
generateCsrf();
