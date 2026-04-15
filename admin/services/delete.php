<?php
/**
 * SERVICES - DELETE HANDLER
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

global $db;

$id = sanitizeInt($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Service not found');
} else {
    try {
        $stmt = $db->prepare("SELECT service_name FROM services WHERE id = ?");
        $stmt->execute([$id]);
        $service = $stmt->fetch();
        
        if ($service) {
            $stmt = $db->prepare("DELETE FROM services WHERE id = ?");
            $stmt->execute([$id]);
            logActivity('DELETE', 'services', $id, "Service deleted: " . $service['service_name']);
            setFlash('success', 'Service deleted successfully!');
        }
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

redirect('admin/services/index.php');
