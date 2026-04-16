<?php
/**
 * Edit Todo
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$userId = currentUser()['id'];
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
$dueDate = isset($_POST['due_date']) ? $_POST['due_date'] : null;

global $db;

if (!$id || empty($title)) {
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
    $stmt = $db->prepare("
        UPDATE todos 
        SET title = :title, 
            description = :description, 
            priority = :priority, 
            status = :status, 
            due_date = :due_date,
            updated_at = NOW()
        WHERE id = :id AND user_id = :user_id
    ");

    $stmt->execute([
        ':id' => $id,
        ':user_id' => $userId,
        ':title' => $title,
        ':description' => $description,
        ':priority' => $priority,
        ':status' => $status,
        ':due_date' => !empty($dueDate) ? $dueDate : null
    ]);

    echo json_encode(['success' => true, 'message' => 'Todo updated successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>