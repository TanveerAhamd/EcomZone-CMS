<?php
/**
 * Toggle Todo Completion Status
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$userId = currentUser()['id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

global $db;

if (!$id) {
    echo json_encode(['success' => false, 'error' => 'Invalid request']);
    exit;
}

// Get current status
$stmt = $db->prepare("SELECT status FROM todos WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
$todo = $stmt->fetch();

if (!$todo) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $newStatus = $todo['status'] === 'completed' ? 'pending' : 'completed';
    
    $stmt = $db->prepare("
        UPDATE todos 
        SET status = :status, updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");

    $stmt->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':status' => $newStatus
    ]);

    echo json_encode(['success' => true, 'message' => 'Todo status updated', 'status' => $newStatus]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>