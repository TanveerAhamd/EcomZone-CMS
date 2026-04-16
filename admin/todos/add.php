<?php
/**
 * Add New Todo
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$userId = currentUser()['id'];
$title = isset($_POST['title']) ? trim($_POST['title']) : '';
$description = isset($_POST['description']) ? trim($_POST['description']) : '';
$priority = isset($_POST['priority']) ? $_POST['priority'] : 'medium';
$status = isset($_POST['status']) ? $_POST['status'] : 'pending';
$dueDate = isset($_POST['due_date']) ? $_POST['due_date'] : null;

global $db;

if (empty($title)) {
    echo json_encode(['success' => false, 'error' => 'Title is required']);
    exit;
}

try {
    $stmt = $db->prepare("
        INSERT INTO todos (user_id, title, description, priority, status, due_date)
        VALUES (:user_id, :title, :description, :priority, :status, :due_date)
    ");

    $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':description' => $description,
        ':priority' => $priority,
        ':status' => $status,
        ':due_date' => !empty($dueDate) ? $dueDate : null
    ]);

    echo json_encode(['success' => true, 'message' => 'Todo created successfully']);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>