<?php
/**
 * Meeting Notes Management
 * Add, view, and manage meeting notes for client meetings
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$action = isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '';
$meetingId = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
$clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$notes = isset($_POST['notes']) ? $_POST['notes'] : '';

global $db;

// Get meeting to verify client access
$stmt = $db->prepare("SELECT * FROM meetings WHERE id = ? AND client_id = ?");
$stmt->execute([$meetingId, $clientId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    echo json_encode(['success' => false, 'error' => 'Meeting not found']);
    exit;
}

if ($action === 'save_notes') {
    // Save or update meeting notes
    if (empty($notes)) {
        echo json_encode(['success' => false, 'error' => 'Notes cannot be empty']);
        exit;
    }

    $stmt = $db->prepare("
        UPDATE meetings 
        SET notes = ?, updated_at = NOW()
        WHERE id = ? AND client_id = ?
    ");
    
    $result = $stmt->execute([$notes, $meetingId, $clientId]);

    if ($result) {
        echo json_encode([
            'success' => true,
            'message' => 'Meeting notes saved successfully',
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save notes']);
    }
}
else if ($action === 'get_notes') {
    // Get meeting notes
    $stmt = $db->prepare("SELECT notes FROM meetings WHERE id = ? AND client_id = ?");
    $stmt->execute([$meetingId, $clientId]);
    $result = $stmt->fetch();

    echo json_encode([
        'success' => true,
        'notes' => $result['notes'] ?? '',
        'meeting' => $meeting
    ]);
}
else {
    echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
