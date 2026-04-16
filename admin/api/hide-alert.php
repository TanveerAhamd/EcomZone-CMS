<?php
/**
 * HIDE ALERT
 * Hides an alert from the list without deleting it from the database
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

if (!$serviceId) {
    echo json_encode(['success' => false, 'message' => 'Service ID is required']);
    exit;
}

global $db;

try {
    // Ensure table has necessary columns
    $db->exec("ALTER TABLE service_alerts ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
    
    // Check if alert exists
    $stmt = $db->prepare("SELECT id FROM service_alerts WHERE service_id = ?");
    $stmt->execute([$serviceId]);
    $alert = $stmt->fetch();

    if ($alert) {
        // Update status to hidden
        $stmt = $db->prepare("
            UPDATE service_alerts 
            SET alert_visibility = 'hidden', status = 'hidden', updated_at = NOW()
            WHERE service_id = ?
        ");
        $stmt->execute([$serviceId]);
    } else {
        // Create hidden alert record
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
                'hidden',
                'hidden'
            ]);
        }
    }

    echo json_encode([
        'success' => true,
        'message' => 'Alert hidden successfully'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
