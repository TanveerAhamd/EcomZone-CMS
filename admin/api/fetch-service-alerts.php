<?php
/**
 * FETCH SERVICE ALERTS AND SERVICES
 * Retrieves all services with expiry info for alert generation
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

// Get all services with their expiry status
$stmt = $db->prepare("
    SELECT 
        ps.id as service_id,
        ps.service_name,
        ps.price,
        ps.expiry_date,
        ps.status,
        p.id as project_id,
        p.project_name,
        c.id as client_id,
        c.client_name,
        c.primary_phone,
        c.secondary_phone,
        c.email,
        c.status as client_status,
        sa.id as alert_id,
        sa.status as alert_status,
        sa.alert_visibility,
        sa.alert_type,
        sa.contact_method,
        sa.sent_at
    FROM project_services ps
    LEFT JOIN projects p ON ps.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN service_alerts sa ON ps.id = sa.service_id AND YEAR(sa.created_at) = YEAR(CURDATE())
    WHERE ps.status IN ('active', 'expired') 
    AND (c.id IS NULL OR c.status != 'deleted')
    AND (sa.id IS NULL OR sa.alert_visibility != 'hidden')
    ORDER BY ps.expiry_date ASC
") or die('Query error');

$stmt->execute();
$services = $stmt->fetchAll();

// Calculate alert status for each service
$alerts = [];
foreach ($services as $service) {
    if (!$service['client_id']) continue;
    
    $expiryDate = new DateTime($service['expiry_date']);
    $today = new DateTime();
    $interval = $today->diff($expiryDate);
    $daysLeft = $interval->invert ? -$interval->days : $interval->days;
    
    // Determine alert type
    $alertType = '';
    if ($daysLeft < 0) $alertType = 'expired';
    elseif ($daysLeft === 0) $alertType = 'today';
    elseif ($daysLeft === 1) $alertType = '1day';
    elseif ($daysLeft === 2) $alertType = '2days';
    elseif ($daysLeft === 3) $alertType = '3days';
    elseif ($daysLeft <= 7) $alertType = '7days';
    elseif ($daysLeft <= 15) $alertType = '15days';
    elseif ($daysLeft <= 30) $alertType = '30days';
    else continue; // Only show alerts for services expiring within 30 days
    
    $contact = $service['primary_phone'] ?: ($service['secondary_phone'] ?: $service['email']);
    
    $alerts[] = [
        'service_id' => $service['service_id'],
        'client_id' => $service['client_id'],
        'service_name' => $service['service_name'],
        'client_name' => $service['client_name'],
        'project_name' => $service['project_name'],
        'expiry_date' => formatDate($service['expiry_date']),
        'days_left' => $daysLeft,
        'alert_type' => $alertType,
        'contact' => $contact,
        'status' => $service['alert_status'] ?? 'pending',
        'sent_at' => $service['sent_at']
    ];
}

echo json_encode(['success' => true, 'alerts' => $alerts]);
