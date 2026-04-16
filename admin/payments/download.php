<?php
/**
 * PAYMENTS - SECURE RECEIPT DOWNLOAD
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

global $db;

$file = $_GET['file'] ?? null;

if (!$file) {
    die('File not specified');
}

// Prevent directory traversal
if (strpos($file, '..') !== false || strpos($file, '/') !== false || strpos($file, '\\') !== false) {
    die('Invalid file name');
}

$filePath = UPLOAD_RECEIPT_PATH . $file;

// Verify file exists and is in the correct directory
if (!file_exists($filePath)) {
    die('File not found');
}

// Verify file is actually in the receipts folder
$realPath = realpath($filePath);
$realReceiptPath = realpath(UPLOAD_RECEIPT_PATH);

if ($realPath === false || $realReceiptPath === false || strpos($realPath, $realReceiptPath) !== 0) {
    die('Invalid file path');
}

// Verify user has access to this receipt (check if they can view the payment)
$stmt = $db->prepare("SELECT id FROM payments WHERE receipt_file = ? LIMIT 1");
$stmt->execute([$file]);
if (!$stmt->fetch()) {
    die('Access denied');
}

// Serve the file
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$mimeType = finfo_file($finfo, $filePath);
finfo_close($finfo);

// Set headers
header('Content-Type: ' . $mimeType);
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');
header('Content-Length: ' . filesize($filePath));
header('Cache-Control: no-cache, must-revalidate');

// Stream file
readfile($filePath);
exit;
?>
