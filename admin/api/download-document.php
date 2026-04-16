<?php
require_once __DIR__ . '/../../includes/init.php';
requireLogin();

global $db;

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$id) {
    die('Document not found');
}

try {
    $stmt = $db->prepare("SELECT file_name, file_path, original_name FROM document_bank WHERE id = :id");
    $stmt->execute([':id' => $id]);
    $doc = $stmt->fetch();

    if (!$doc) {
        die('Document not found');
    }

    $filePath = __DIR__ . '/../../uploads/document-bank/' . $doc['file_path'];

    if (!file_exists($filePath)) {
        die('Document file not found');
    }

    // Use original_name if available, otherwise use file_name
    $downloadName = !empty($doc['original_name']) ? $doc['original_name'] : $doc['file_name'];

    // Set headers for download
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . basename($downloadName) . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Cache-Control: must-revalidate');
    header('Pragma: public');

    readfile($filePath);
} catch (Exception $e) {
    die('Error: ' . $e->getMessage());
}
?>
