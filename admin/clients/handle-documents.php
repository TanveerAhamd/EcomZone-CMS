<?php
/**
 * CLIENT DOCUMENTS - UPLOAD/MANAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

global $db;

$client_id = sanitizeInt($_POST['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    header('Content-Type: application/json');
    
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'CSRF token expired']);
        exit;
    }
    
    try {
        $doc_name = clean($_POST['doc_name'] ?? 'Untitled Document');
        $doc_type = clean($_POST['doc_type'] ?? 'other');
        
        // Define directory
        $targetDir = UPLOAD_PATH . 'documents/';
        
        $uploadResult = uploadFile($_FILES['document'], $targetDir);
        
        if ($uploadResult['success']) {
            // Save relative path for web access
            $relativePath = 'uploads/documents/' . $uploadResult['file_name'];
            
            $stmt = $db->prepare("
                INSERT INTO client_documents (client_id, document_name, document_type, file_path, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$client_id, $doc_name, $doc_type, $relativePath, currentUser()['id']]);
            
            logActivity('CREATE', 'client_documents', $db->lastInsertId(), "Document uploaded: " . $doc_name);
            echo json_encode(['success' => true, 'message' => 'Document uploaded']);
        } else {
            echo json_encode(['success' => false, 'error' => $uploadResult['error'] ?? 'Upload failed']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}


// Delete document
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    header('Content-Type: application/json');
    try {
        $doc_id = sanitizeInt($_POST['doc_id'] ?? 0);
        $stmt = $db->prepare("SELECT * FROM client_documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        $doc = $stmt->fetch();
        
        if ($doc) {
            $fullPath = APP_PATH . $doc['file_path'];
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }
            
            $stmt = $db->prepare("DELETE FROM client_documents WHERE id = ?");
            $stmt->execute([$doc_id]);
            
            logActivity('DELETE', 'client_documents', $doc_id, "Document deleted: " . ($doc['document_name'] ?? 'Untitled'));
            echo json_encode(['success' => true]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Document not found']);
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
