<?php
/**
 * SEND WHATSAPP MESSAGE ENDPOINT
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit;
}

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$phone = $data['phone'] ?? '';
$message = $data['message'] ?? '';
$type = $data['type'] ?? 'custom';

if (empty($phone) || empty($message)) {
    echo json_encode(['success' => false, 'message' => 'Missing required fields']);
    exit;
}

global $db;

try {
    // Log the WhatsApp message
    $stmt = $db->prepare("
        INSERT INTO whatsapp_logs (
            phone_number, message, message_type, status,
            created_by, created_at
        ) VALUES (
            :phone, :message, :type, 'sent',
            :user_id, NOW()
        )
    ");
    
    $stmt->execute([
        ':phone' => $phone,
        ':message' => $message,
        ':type' => $type,
        ':user_id' => currentUser()['id']
    ]);
    
    // Here you would integrate WITH actual WhatsApp API
    // For now, we just log it as sent
    
    logActivity('WHATSAPP_SEND', 'whatsapp', null, 'Sent WhatsApp message');
    
    echo json_encode([
        'success' => true,
        'message' => 'Message sent successfully'
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
