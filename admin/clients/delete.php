<?php
/**
 * CLIENT DELETE ENDPOINT
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

if (!isset($_GET['id'])) {
    setFlash('danger', 'Client not found');
    redirect('admin/clients/index.php');
}

$clientId = (int)$_GET['id'];

global $db;

try {
    // Check if exists
    $stmt = $db->prepare("SELECT id FROM clients WHERE id = :id");
    $stmt->execute([':id' => $clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        setFlash('danger', 'Client not found');
        redirect('admin/clients/index.php');
    }
    
    // Delete client (cascades to related data)
    $stmt = $db->prepare("DELETE FROM clients WHERE id = :id");
    $stmt->execute([':id' => $clientId]);
    
    logActivity('DELETE', 'clients', $clientId, 'Deleted client');
    setFlash('success', 'Client deleted successfully');
} catch (Exception $e) {
    setFlash('danger', 'Error: ' . $e->getMessage());
}

redirect('admin/clients/index.php');
