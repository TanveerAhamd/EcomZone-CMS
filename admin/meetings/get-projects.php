<?php
/**
 * Get Projects by Client (AJAX)
 * Filters projects based on selected client
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;

global $db;

if (!$clientId) {
    echo json_encode(['projects' => []]);
    exit;
}

// Verify client exists
$stmt = $db->prepare("SELECT id FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Client not found']);
    exit;
}

// Get projects for this client
$stmt = $db->prepare("
    SELECT id, project_name, project_code
    FROM projects
    WHERE client_id = ?
    ORDER BY project_name
");
$stmt->execute([$clientId]);
$projects = $stmt->fetchAll();

echo json_encode([
    'success' => true,
    'projects' => $projects
]);
