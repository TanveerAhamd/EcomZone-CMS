<?php
/**
 * AUTO-GENERATE SERVICE ALERTS
 * Creates alert records for services expiring within thresholds
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

global $db;

// Get all services expiring within 30 days that don't have today's alerts
$stmt = $db->prepare("
    SELECT DISTINCT
        ps.id as service_id,
        ps.service_name,
        ps.expiry_date,
        p.id as project_id,
        p.project_name,
        c.id as client_id,
        c.client_name
    FROM project_services ps
    LEFT JOIN projects p ON ps.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE ps.status IN ('active', 'expired')
    AND (c.id IS NULL OR c.status != 'deleted')
    AND DATEDIFF(ps.expiry_date, CURDATE()) >= -1
    AND DATEDIFF(ps.expiry_date, CURDATE()) <= 30
    AND NOT EXISTS (
        SELECT 1 FROM service_alerts sa 
        WHERE sa.service_id = ps.id 
        AND DATE(sa.created_at) = CURDATE()
    )
    ORDER BY ps.expiry_date ASC
");

$stmt->execute();
$services = $stmt->fetchAll();

$createdCount = 0;

foreach ($services as $service) {
    $daysLeft = (strtotime($service['expiry_date']) - time()) / (24 * 60 * 60);
    
    // Determine threshold
    $thresholdLevel = '';
    if ($daysLeft < 0) $thresholdLevel = 'expired';
    elseif ($daysLeft === 0) $thresholdLevel = 'today';
    elseif ($daysLeft < 1) $thresholdLevel = 'today';
    elseif ($daysLeft < 2) $thresholdLevel = '1day';
    elseif ($daysLeft < 3) $thresholdLevel = '2days';
    elseif ($daysLeft < 4) $thresholdLevel = '3days';
    elseif ($daysLeft < 8) $thresholdLevel = '7days';
    elseif ($daysLeft < 16) $thresholdLevel = '15days';
    elseif ($daysLeft <= 30) $thresholdLevel = '30days';
    else continue;
    
    // Create alert record (initially pending)
    $stmt = $db->prepare("
        INSERT INTO service_alerts (
            service_id, client_id, alert_type, 
            contact_method, contact_value, status
        ) VALUES (?, ?, ?, 'pending', '', 'pending')
    ");
    
    $stmt->execute([
        $service['service_id'],
        $service['client_id'],
        $thresholdLevel
    ]);
    
    $createdCount++;
}

echo json_encode([
    'success' => true,
    'count' => $createdCount,
    'message' => "Generated $createdCount new alert records"
]);
