<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

$document_title = isset($_POST['document_title']) ? clean($_POST['document_title']) : '';
$client_id = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$file = isset($_FILES['file']) ? $_FILES['file'] : null;

if (!$document_title || !$client_id || !$file) {
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Validate file
$validTypes = ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'];
if (!in_array($file['type'], $validTypes)) {
    echo json_encode(['success' => false, 'error' => 'Invalid file type. Only PDF and images allowed.']);
    exit;
}

$maxSize = 10 * 1024 * 1024; // 10MB
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'error' => 'File too large. Max 10MB allowed.']);
    exit;
}

// Create uploads directory if it doesn't exist
$uploadDir = __DIR__ . '/../../uploads/document-bank/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Get client name
$stmtClient = $db->prepare("SELECT client_name FROM clients WHERE id = :id");
$stmtClient->execute([':id' => $client_id]);
$clientData = $stmtClient->fetch();
$clientName = $clientData ? sanitizeFileName($clientData['client_name']) : 'Client';

// Generate renamed filename: ClientName_DocumentTitle_Date_Time
$ext = pathinfo($file['name'], PATHINFO_EXTENSION);
$sanitizedTitle = sanitizeFileName($document_title);
$timestamp = date('Y-m-d_His'); // Format: 2024-04-16_143025
$uniqueName = $clientName . '_' . $sanitizedTitle . '_' . $timestamp . '.' . $ext;
$filePath = $uploadDir . $uniqueName;

// Move uploaded file
if (!move_uploaded_file($file['tmp_name'], $filePath)) {
    echo json_encode(['success' => false, 'error' => 'Failed to upload file']);
    exit;
}

// Insert into database
try {
    $stmt = $db->prepare("
        INSERT INTO document_bank (client_id, document_title, file_name, original_name, file_path, file_size, uploaded_at)
        VALUES (:client_id, :document_title, :file_name, :original_name, :file_path, :file_size, NOW())
    ");
    
    $stmt->execute([
        ':client_id' => $client_id,
        ':document_title' => $document_title,
        ':file_name' => $uniqueName,
        ':original_name' => $file['name'],
        ':file_path' => $uniqueName,
        ':file_size' => $file['size']
    ]);

    echo json_encode(['success' => true, 'message' => 'Document uploaded successfully']);
} catch (Exception $e) {
    // Delete the uploaded file if database insert fails
    unlink($filePath);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}

function sanitizeFileName($str) {
    $str = strtolower(trim($str));
    $str = preg_replace('/[^a-z0-9]+/', '_', $str);
    $str = preg_replace('/_+/', '_', $str);
    $str = trim($str, '_');
    return substr($str, 0, 30); // Limit to 30 chars
}
?>
