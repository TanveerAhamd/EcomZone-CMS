<?php
/**
 * AJAX - GET PROJECT SERVICES FOR INVOICE
 */

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

requireLogin();
requireRole(['admin', 'manager']);

$project_id = sanitizeInt($_GET['project_id'] ?? 0);

if (!$project_id) {
    echo json_encode(['success' => false, 'error' => 'Project ID required']);
    exit;
}

try {
    global $db;
    
    // Get project
    $stmt = $db->prepare("SELECT id FROM projects WHERE id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
    
    if (!$project) {
        echo json_encode(['success' => false, 'error' => 'Project not found']);
        exit;
    }
    
    // Get project services
    $stmt = $db->prepare("
        SELECT id, service_name, price, status
        FROM project_services 
        WHERE project_id = ? AND status != 'cancelled'
        ORDER BY created_at DESC
    ");
    $stmt->execute([$project_id]);
    $services = $stmt->fetchAll();
    
    echo json_encode([
        'success' => true,
        'services' => array_map(function($s) {
            return [
                'id' => $s['id'],
                'service_name' => $s['service_name'],
                'price' => (float)$s['price'],
                'status' => $s['status']
            ];
        }, $services),
        'count' => count($services)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
