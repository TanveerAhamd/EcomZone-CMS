<?php
/**
 * CLIENT DOCUMENTS - UPLOAD/MANAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

global $db;

$client_id = sanitizeInt($_GET['client_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['document'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'error' => 'CSRF token expired']);
        exit;
    }
    
    try {
        $doc_name = $_POST['doc_name'] ?? '';
        $doc_type = $_POST['doc_type'] ?? 'other';
        
        $file = uploadFile($_FILES['document'], 'client-documents');
        
        if ($file) {
            $stmt = $db->prepare("
                INSERT INTO client_documents (client_id, document_name, document_type, file_path, uploaded_by, created_at)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$client_id, $doc_name, $doc_type, $file, currentUser()['id']]);
            
            logActivity('CREATE', 'client_documents', $db->lastInsertId(), "Document uploaded: " . $doc_name);
            echo json_encode(['success' => true, 'message' => 'Document uploaded']);
        } else {
            echo json_encode(['success' => false, 'error' => 'Upload failed']);
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
        
        if ($doc && file_exists($_SERVER['DOCUMENT_ROOT'] . $doc['file_path'])) {
            unlink($_SERVER['DOCUMENT_ROOT'] . $doc['file_path']);
        }
        
        $stmt = $db->prepare("DELETE FROM client_documents WHERE id = ?");
        $stmt->execute([$doc_id]);
        
        logActivity('DELETE', 'client_documents', $doc_id, "Document deleted");
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
