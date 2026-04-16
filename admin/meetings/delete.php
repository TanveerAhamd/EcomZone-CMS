<?php
/**
 * MEETINGS - DELETE ENDPOINT
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method']);
    exit;
}

// Get JSON data
$input = json_decode(file_get_contents('php://input'), true);
$id = $input['id'] ?? null;

// Validate ID
if (!$id || !is_numeric($id)) {
    echo json_encode(['success' => false, 'error' => 'Invalid meeting ID']);
    exit;
}

try {
    global $db;

    // First verify the meeting exists and belongs to the current user
    $stmt = $db->prepare("SELECT id, title FROM meetings WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, currentUser()['id']]);
    $meeting = $stmt->fetch();

    if (!$meeting) {
        echo json_encode(['success' => false, 'error' => 'Meeting not found or you do not have permission to delete it']);
        exit;
    }

    // Delete the meeting
    $stmt = $db->prepare("DELETE FROM meetings WHERE id = ?");
    $stmt->execute([$id]);

    // Log the activity
    logActivity('DELETE', 'meetings', $id, "Meeting '{$meeting['title']}' deleted");

    echo json_encode([
        'success' => true,
        'message' => 'Meeting deleted successfully',
        'id' => $id
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
