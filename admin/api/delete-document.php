<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Document ID is required']);
    exit;
}

try {
    // Get document file path
    $stmt = $db->prepare("SELECT file_path, file_name FROM document_bank WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        echo json_encode(['success' => false, 'error' => 'Document not found']);
        exit;
    }

    // Delete file from disk using the stored file_path
    $filePath = __DIR__ . '/../../uploads/document-bank/' . $doc['file_path'];
    if (file_exists($filePath)) {
        if (!unlink($filePath)) {
            echo json_encode(['success' => false, 'error' => 'Failed to delete file from disk']);
            exit;
        }
    }

    // Delete from database
    $stmt = $db->prepare("DELETE FROM document_bank WHERE id = :id");
    $stmt->execute([':id' => $id]);

    echo json_encode(['success' => true, 'message' => 'Document deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
