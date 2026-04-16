<?php
/**
 * View/Edit Client Document
 * AJAX endpoint for document details and editing
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

try {
    $documentId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $action = isset($_GET['action']) ? $_GET['action'] : 'view';
    
    if (!$documentId) {
        throw new Exception('Invalid document ID');
    }

    global $db;

    // Get document details
    $stmt = $db->prepare("SELECT * FROM client_documents WHERE id = ?");
    $stmt->execute([$documentId]);
    $document = $stmt->fetch();

    if (!$document) {
        throw new Exception('Document not found');
    }

    // Verify user has access to this client
    $clientStmt = $db->prepare("SELECT id FROM clients WHERE id = ?");
    $clientStmt->execute([$document['client_id']]);
    if (!$clientStmt->fetch()) {
        throw new Exception('Access denied');
    }

    // Get file information
    $filePath = '/uploads/client-documents/' . $document['file_name'];
    $fileFullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
    $fileSize = file_exists($fileFullPath) ? filesize($fileFullPath) : 0;
    $fileSizeFormatted = $fileSize > 1048576 ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';

    if ($action === 'view') {
        echo json_encode([
            'success' => true,
            'document' => [
                'id' => $document['id'],
                'original_name' => $document['original_name'],
                'file_name' => $document['file_name'],
                'doc_type' => $document['doc_type'],
                'uploaded_at' => formatDate($document['uploaded_at']),
                'file_size' => $fileSizeFormatted,
                'file_path' => $filePath,
                'file_exists' => file_exists($fileFullPath)
            ]
        ]);
    } else if ($action === 'edit') {
        // Update document name and type
        $newName = isset($_POST['original_name']) ? htmlspecialchars($_POST['original_name'], ENT_QUOTES, 'UTF-8') : '';
        $newType = isset($_POST['doc_type']) ? htmlspecialchars($_POST['doc_type'], ENT_QUOTES, 'UTF-8') : '';

        if (!$newName || !$newType) {
            throw new Exception('Document name and type are required');
        }

        $updateStmt = $db->prepare("
            UPDATE client_documents 
            SET original_name = ?, doc_type = ?
            WHERE id = ?
        ");

        if ($updateStmt->execute([$newName, $newType, $documentId])) {
            echo json_encode([
                'success' => true,
                'message' => 'Document updated successfully'
            ]);
        } else {
            throw new Exception('Failed to update document');
        }
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
