<?php
/**
 * Delete Client Document
 * AJAX endpoint for document deletion
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

try {
    $documentId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
    
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

    // Delete file from storage
    $filePath = $_SERVER['DOCUMENT_ROOT'] . '/uploads/client-documents/' . $document['file_name'];
    if (file_exists($filePath)) {
        unlink($filePath);
    }

    // Delete from database
    $deleteStmt = $db->prepare("DELETE FROM client_documents WHERE id = ?");
    if ($deleteStmt->execute([$documentId])) {
        echo json_encode([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
    } else {
        throw new Exception('Failed to delete document');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
