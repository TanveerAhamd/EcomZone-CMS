<?php
/**
 * Delete Todo
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

// Verify ownership
$stmt = $db->prepare("SELECT id FROM todos WHERE id = ? AND user_id = ?");
$stmt->execute([$id, $userId]);
if (!$stmt->fetch()) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

try {
    $stmt = $db->prepare("DELETE FROM todos WHERE id = ? AND user_id = ?");
    $stmt->execute([$id, $userId]);

    echo json_encode(['success' => true, 'message' => 'Todo deleted successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>