<?php
/**
 * CMS-ecomzone Configuration File
 * Global constants and application settings
 */

// ============================================
// DATABASE CONFIGURATION
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'cms_ecomzone');
define('DB_CHARSET', 'utf8mb4');

// ============================================
// APPLICATION CONFIGURATION
// ============================================
define('APP_NAME', 'CMS-ecomzone');
define('APP_TAGLINE', 'Client Service & Project Management');
define('APP_URL', 'http://localhost/EcomZone-CMS');
define('APP_PATH', __DIR__ . '/../');

// ============================================
// SECURITY SETTINGS
// ============================================
define('SESSION_TIMEOUT', 7200); // 2 hours
define('CSRF_TOKEN_LENGTH', 32);
define('PASSWORD_HASH_COST', 12);
define('LOGIN_MAX_ATTEMPTS', 5);
define('LOGIN_LOCKOUT_TIME', 900); // 15 minutes

// ============================================
// FILE UPLOAD CONFIGURATION
// ============================================
define('UPLOAD_PATH', APP_PATH . 'uploads/');
define('UPLOAD_CLIENT_PATH', UPLOAD_PATH . 'clients/');
define('UPLOAD_RECEIPT_PATH', UPLOAD_PATH . 'receipts/');
define('MAX_UPLOAD_SIZE', 10485760); // 10MB
define('ALLOWED_UPLOAD_TYPES', ['jpeg', 'jpg', 'png', 'pdf', 'doc', 'docx', 'xls', 'xlsx']);

// ============================================
// API & EXTERNAL SERVICES
// ============================================
define('WHATSAPP_API_VERSION', 'v18.0');
define('WHATSAPP_API_URL', 'https://graph.instagram.com/');

// ============================================
// CURRENCY & FORMATTING
// ============================================
define('CURRENCY', 'PKR');
define('CURRENCY_SYMBOL', '₨');
define('DECIMAL_PLACES', 2);

// ============================================
// DATE & TIME
// ============================================
define('TIMEZONE', 'Asia/Karachi');
define('DATE_FORMAT', 'd M Y');
define('DATETIME_FORMAT', 'd M Y H:i');
define('TIME_FORMAT', 'H:i');

// ============================================
// PAGINATION
// ============================================
define('ITEMS_PER_PAGE', 15);
define('DATATABLE_ROWS', 10);

// ============================================
// NOTIFICATION SETTINGS
// ============================================
define('NOTIFICATIONS_LIMIT', 10);
define('SERVICE_ALERT_DAYS', [1, 7, 15, 30]);

// ============================================
// COLOR PALETTE (Match Workload Design)
// ============================================
const COLORS = [
    'primary' => '#0026ff',
    'secondary' => '#1EAAE7',
    'success' => '#2BC155',
    'warning' => '#FF9B52',
    'danger' => '#FF5E5E',
    'dark' => '#1D1D1D',
    'sidebar_bg' => '#1D1D35',
    'card_bg' => '#FFFFFF',
    'body_bg' => '#F4F4F4',
    'border' => '#EEEEEE',
    'text_muted' => '#888888'
];

// ============================================
// EMAIL CONFIGURATION (for future use)
// ============================================
define('MAIL_FROM', 'noreply@cms-ecomzone.com');
define('MAIL_FROM_NAME', 'CMS-ecomzone');

// ============================================
// ERROR REPORTING
// ============================================
error_reporting(E_ALL);
ini_set('display_errors', 0); // Set to 1 for development
ini_set('log_errors', 1);
ini_set('error_log', APP_PATH . 'logs/error.log');

// ============================================
// CHARACTER SET
// ============================================
mb_internal_encoding('UTF-8');
