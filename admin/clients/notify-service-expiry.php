<?php
/**
 * Service Expiry Notification Handler
 * Sends WhatsApp & Email notifications for expiring/expired services
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$serviceId = isset($_POST['service_id']) ? (int)$_POST['service_id'] : 0;
$notificationType = isset($_POST['type']) ? htmlspecialchars($_POST['type']) : ''; // 'whatsapp' or 'email'
$clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;

if (!$serviceId || !$clientId || !in_array($notificationType, ['whatsapp', 'email'])) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

global $db;

// Get service details
$stmt = $db->prepare("
    SELECT ps.*, p.project_name, p.client_id, c.primary_phone, c.secondary_phone, c.email, c.client_name
    FROM project_services ps
    JOIN projects p ON ps.project_id = p.id
    JOIN clients c ON p.client_id = c.id
    WHERE ps.id = ? AND p.client_id = ?
");
$stmt->execute([$serviceId, $clientId]);
$service = $stmt->fetch();

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Service not found']);
    exit;
}

// Get site settings for WhatsApp
$stmt = $db->prepare("SELECT setting_value FROM site_settings WHERE setting_key = 'whatsapp_api_url'");
$stmt->execute();
$whatsappSetting = $stmt->fetch();

$daysLeft = $service['expiry_date'] ? (strtotime($service['expiry_date']) - time()) / (24 * 60 * 60) : 0;
$statusText = $daysLeft < 0 ? 'EXPIRED' : 'EXPIRING SOON';

// Create modern message
$clientName = clean($service['client_name']);
$serviceName = clean($service['service_name']);
$projectName = clean($service['project_name']);
$expiryDate = formatDate($service['expiry_date']);

if ($notificationType === 'whatsapp') {
    if (!$service['primary_phone'] && !$service['secondary_phone']) {
        echo json_encode(['success' => false, 'error' => 'No phone number available']);
        exit;
    }

    $phone = $service['primary_phone'] ?: $service['secondary_phone'];
    $phone = preg_replace('/[^0-9]/', '', $phone);

    $message = "👋 *Hi $clientName*\n\n";
    $message .= "⚠️ *Service Renewal Notice*\n\n";
    $message .= "Service: *$serviceName*\n";
    $message .= "Project: *$projectName*\n";
    $message .= "Status: *$statusText*\n";
    $message .= "Expiry: *$expiryDate*\n\n";
    $message .= "📞 Please contact us to renew your service before it expires.\n\n";
    $message .= "Thank you for your business! 🙏";

    // Try to send via WhatsApp API
    if ($whatsappSetting) {
        $apiUrl = $whatsappSetting['setting_value'];
        $payload = [
            'phone' => $phone,
            'message' => $message
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);

        $response = curl_exec($ch);
        curl_close($ch);

        // Log the notification
        $stmt = $db->prepare("
            INSERT INTO notifications (client_id, type, title, message, status, created_at)
            VALUES (?, 'whatsapp', 'Service Renewal', ?, 'sent', NOW())
        ");
        $stmt->execute([$clientId, $message]);

        echo json_encode([
            'success' => true,
            'message' => 'WhatsApp message sent successfully to ' . substr($phone, -4),
            'phone' => substr($phone, -4)
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'WhatsApp API not configured']);
    }
} 
else if ($notificationType === 'email') {
    if (!$service['email']) {
        echo json_encode(['success' => false, 'error' => 'No email address available']);
        exit;
    }

    $email = $service['email'];
    $subject = "🔔 Service Renewal Notice: $serviceName";

    $emailBody = "
    <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
        <div style='background: linear-gradient(135deg, #6418C3, #9B59B6); color: white; padding: 30px; border-radius: 12px 12px 0 0; text-align: center;'>
            <h2 style='margin: 0;'>⚠️ Service Renewal Notice</h2>
        </div>
        
        <div style='background: #f9f9f9; padding: 30px; border-radius: 0 0 12px 12px;'>
            <p>Hi <strong>$clientName</strong>,</p>
            
            <p>We want to remind you that one of your services is about to expire:</p>
            
            <div style='background: white; padding: 20px; border-left: 4px solid #6418C3; margin: 20px 0; border-radius: 6px;'>
                <p style='margin: 10px 0;'><strong>Service:</strong> $serviceName</p>
                <p style='margin: 10px 0;'><strong>Project:</strong> $projectName</p>
                <p style='margin: 10px 0;'><strong>Expiry Date:</strong> <span style='color: #FF9B52; font-weight: bold;'>$expiryDate</span></p>
                <p style='margin: 10px 0;'><strong>Status:</strong> <span style='color: #FF5E5E; font-weight: bold;'>$statusText</span></p>
            </div>
            
            <p>👉 <strong>Action Required:</strong> Please contact us at your earliest convenience to renew this service and avoid any disruptions to your business.</p>
            
            <p>If you have any questions or need assistance, feel free to reach out to our team. We're here to help!</p>
            
            <p style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; color: #666; font-size: 0.9em;'>
                Thank you for your continued business! 🙏
            </p>
        </div>
    </div>";

    // Use PHP mail or your email service
    $headers = "MIME-Version: 1.0\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SITE_NAME . " <noreply@" . SITE_DOMAIN . ">\r\n";
    $headers .= "Reply-To: support@" . SITE_DOMAIN . "\r\n";

    $mailSent = mail($email, $subject, $emailBody, $headers);

    if ($mailSent) {
        // Log the notification
        $stmt = $db->prepare("
            INSERT INTO notifications (client_id, type, title, message, status, created_at)
            VALUES (?, 'email', 'Service Renewal', ?, 'sent', NOW())
        ");
        $stmt->execute([$clientId, "Sent to: $email"]);

        echo json_encode([
            'success' => true,
            'message' => 'Email notification sent successfully',
            'email' => substr($email, 0, 3) . '***' . substr($email, strrpos($email, '@'))
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to send email']);
    }
}
