<?php
/**
 * PREVIEW ALERT MESSAGE
 * Shows message that will be sent to client
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

$serviceId = (int)($_GET['service_id'] ?? 0);
$clientId = (int)($_GET['client_id'] ?? 0);
$thresholdType = $_GET['threshold'] ?? '';

if (!$serviceId || !$clientId) {
    echo json_encode(['success' => false, 'error' => 'Missing parameters']);
    exit;
}

// Get service details
$stmt = $db->prepare("
    SELECT 
        ps.*, 
        p.project_name,
        c.client_name,
        c.primary_phone,
        c.secondary_phone,
        c.email
    FROM project_services ps
    JOIN projects p ON ps.project_id = p.id
    JOIN clients c ON p.client_id = c.id
    WHERE ps.id = ? AND c.id = ?
");
$stmt->execute([$serviceId, $clientId]);
$service = $stmt->fetch();

if (!$service) {
    echo json_encode(['success' => false, 'error' => 'Service not found']);
    exit;
}

// WhatsApp message template (preview)
$templates = [
    '30days' => "👋 Hi *{CLIENT_NAME}*\n\n📢 *Service Renewal Notification*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 Expiry Date: *{EXPIRY_DATE}*\n⏱️ Days Remaining: *{DAYS_LEFT}*\n\n💼 *Action Needed:*\nPlease renew your service to ensure uninterrupted service.\n\n📞 Contact our team for renewal details.",
    
    '15days' => "🔔 Hi *{CLIENT_NAME}*\n\n⚠️ *Urgent: Service Expiring Soon*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 Expiry: *{EXPIRY_DATE}*\n⏱️ Days Left: *{DAYS_LEFT}*\n\n🚀 *Immediate Action Required:*\nRenew your service within the next {DAYS_LEFT} days to avoid interruption.",
    
    '7days' => "🚨 Hi *{CLIENT_NAME}*\n\n⛔ *CRITICAL: Service Expiring in {DAYS_LEFT} Days*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 Last Day: *{EXPIRY_DATE}*\n\n⚡ *Action Required NOW:*\nYour service expires on {EXPIRY_DATE}. Please renew immediately to prevent service disruption.",
    
    '3days' => "🔴 Hi *{CLIENT_NAME}*\n\n⛔ *FINAL NOTICE: Service Expires in {DAYS_LEFT} Days*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 Expiry: *{EXPIRY_DATE}* ← FINAL DAYS\n\n⚡ *This is your last chance to renew!*",
    
    '1day' => "🔴 Hi *{CLIENT_NAME}*\n\n⛔ *LAST DAY WARNING*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 EXPIRES TOMORROW: *{EXPIRY_DATE}*\n\n⚡ *ACT NOW or lose access tomorrow!*",
    
    'today' => "🔴 Hi *{CLIENT_NAME}*\n\n⛔⛔⛔ *TODAY IS YOUR LAST DAY* ⛔⛔⛔\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\n📅 FINAL DAY: *{EXPIRY_DATE}*\n\n⚠️ *SERVICE WILL BE TERMINATED TONIGHT*",
    
    'expired' => "❌ Hi *{CLIENT_NAME}*\n\n😔 *Your Service Has Expired*\n\nService: *{SERVICE_NAME}*\nProject: *{PROJECT_NAME}*\nExpired: *{EXPIRY_DATE}*\n\n🔄 To restore your service, please renew immediately."
];

$daysLeft = abs((strtotime($service['expiry_date']) - time()) / (24 * 60 * 60));
$template = $templates[$thresholdType] ?? $templates['30days'];

$message = str_replace(
    ['{CLIENT_NAME}', '{SERVICE_NAME}', '{PROJECT_NAME}', '{EXPIRY_DATE}', '{DAYS_LEFT}'],
    [
        clean($service['client_name']),
        clean($service['service_name']),
        clean($service['project_name']),
        formatDate($service['expiry_date']),
        ceil($daysLeft)
    ],
    $template
);

$contact = $service['primary_phone'] ?: ($service['secondary_phone'] ?: $service['email']);
$contactMasked = strpos($contact, '@') ? substr($contact, 0, 3) . '***' : 'Phone: ' . substr($contact, -4);

echo json_encode([
    'success' => true,
    'service_name' => $service['service_name'],
    'client_name' => $service['client_name'],
    'message' => $message,
    'contact' => $contactMasked,
    'method' => strpos($contact, '@') ? 'Email' : 'WhatsApp'
]);

// Create a record in the service_alerts table
$db->prepare("
    INSERT INTO service_alerts (service_id, client_id, alert_type, contact_method, contact_value, message_content, status)
    VALUES (?, ?, ?, ?, ?, ?, ?)
");
$db->execute([$serviceId, $clientId, $thresholdType, strpos($contact, '@') ? 'whatsapp' : 'email', $contact, $message, 'pending']);
