<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

$client_id = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$doc_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    if ($doc_id) {
        // Get single document by ID
        $stmt = $db->prepare("
            SELECT db.id, db.client_id, db.document_title, db.file_name, db.original_name, db.file_path, db.file_size, db.uploaded_at,
                   c.client_name
            FROM document_bank db
            JOIN clients c ON db.client_id = c.id
            WHERE db.id = :id
        ");
        $stmt->execute([':id' => $doc_id]);
    } else if ($client_id) {
        // Get documents for specific client
        $stmt = $db->prepare("
            SELECT db.id, db.client_id, db.document_title, db.file_name, db.original_name, db.file_path, db.file_size, db.uploaded_at,
                   c.client_name
            FROM document_bank db
            JOIN clients c ON db.client_id = c.id
            WHERE db.client_id = :client_id
            ORDER BY db.uploaded_at DESC
        ");
        $stmt->execute([':client_id' => $client_id]);
    } else {
        // Get all documents
        $stmt = $db->prepare("
            SELECT db.id, db.client_id, db.document_title, db.file_name, db.original_name, db.file_path, db.file_size, db.uploaded_at,
                   c.client_name
            FROM document_bank db
            JOIN clients c ON db.client_id = c.id
            ORDER BY db.uploaded_at DESC
        ");
        $stmt->execute();
    }

    $documents = $stmt->fetchAll();
    echo json_encode(['success' => true, 'documents' => $documents]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
