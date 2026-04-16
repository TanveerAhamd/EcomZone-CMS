<?php
/**
 * UPDATE ALERT STATUS
 * Updates the status of a service alert (pending, sent, acknowledged, hidden, etc.)
 */

require_once __DIR__ . '/../../includes/init.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$serviceId = $input['service_id'] ?? null;
$newStatus = $input['status'] ?? null;

// Validate input
if (!$serviceId || !$newStatus) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

// Allowed statuses
$allowedStatuses = ['pending', 'sent', 'acknowledged', 'renewed', 'hidden', 'show'];
if (!in_array($newStatus, $allowedStatuses)) {
    echo json_encode(['success' => false, 'message' => 'Invalid status']);
    exit;
}

global $db;

try {
    // First, ensure service_alerts table has all necessary columns
    $db->exec("ALTER TABLE service_alerts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Add columns to project_services if they don't exist
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS last_alert_sent TIMESTAMP NULL");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS alert_count INT DEFAULT 0");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS renewed_at TIMESTAMP NULL");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Check if alert record exists
    $stmt = $db->prepare("SELECT id, status FROM service_alerts WHERE service_id = ?");
    $stmt->execute([$serviceId]);
    $alert = $stmt->fetch();

    if ($alert) {
        // Update existing alert
        $stmt = $db->prepare("
            UPDATE service_alerts 
            SET status = ?, updated_at = NOW()
            WHERE service_id = ?
        ");
        $stmt->execute([$newStatus, $serviceId]);
    } else {
        // Get service and client info
        $stmt = $db->prepare("
            SELECT p.client_id, c.email, c.primary_phone 
            FROM project_services ps
            LEFT JOIN projects p ON ps.project_id = p.id
            LEFT JOIN clients c ON p.client_id = c.id
            WHERE ps.id = ?
        ");
        $stmt->execute([$serviceId]);
        $service = $stmt->fetch();

        if ($service && $service['client_id']) {
            $stmt = $db->prepare("
                INSERT INTO service_alerts 
                (service_id, client_id, alert_type, contact_method, contact_value, status, alert_visibility, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
            ");
            
            $contactMethod = !empty($service['email']) ? 'email' : 'phone';
            $contactValue = $contactMethod === 'email' ? $service['email'] : $service['primary_phone'];
            
            $stmt->execute([
                $serviceId,
                $service['client_id'],
                'expiry',
                $contactMethod,
                $contactValue,
                $newStatus,
                'show'
            ]);
        }
    }
    
    // Update project_services tracking information
    if ($newStatus === 'sent') {
        $stmt = $db->prepare("
            UPDATE project_services 
            SET last_alert_sent = NOW(), alert_count = alert_count + 1, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$serviceId]);
    }

    echo json_encode([
        'success' => true,
        'message' => 'Alert status updated successfully',
        'status' => $newStatus
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
