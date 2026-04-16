<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

try {
    $clientId = (int)$_POST['client_id'] ?? 0;
    $documentName = trim($_POST['document_name'] ?? '');
    $documentType = $_POST['document_type'] ?? 'other';

    if (!$clientId || !$documentName || empty($_FILES['file'])) {
        throw new Exception('Missing required information');
    }

    global $db;
    $file = $_FILES['file'];
    $uploadsDir = __DIR__ . '/../../uploads/client-documents/';

    // Create directory if it doesn't exist
    if (!is_dir($uploadsDir)) {
        mkdir($uploadsDir, 0755, true);
    }

    // Generate simple filename
    $fileExt = pathinfo($file['name'], PATHINFO_EXTENSION);
    $fileName = time() . '_' . uniqid() . '.' . $fileExt;
    $filePath = $uploadsDir . $fileName;

    // Move uploaded file
    if (!move_uploaded_file($file['tmp_name'], $filePath)) {
        throw new Exception('Failed to upload file');
    }

    // Save to database
    $stmt = $db->prepare("
        INSERT INTO client_documents (client_id, doc_type, file_name, original_name, uploaded_at)
        VALUES (?, ?, ?, ?, NOW())
    ");

    if ($stmt->execute([$clientId, $documentType, $fileName, $documentName])) {
        echo json_encode(['success' => true, 'message' => 'File uploaded successfully']);
    } else {
        unlink($filePath);
        throw new Exception('Database save failed');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}


