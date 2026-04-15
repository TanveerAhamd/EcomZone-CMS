<?php
/**
 * NOTIFICATIONS API ENDPOINT
 */

require_once __DIR__ . '/../../includes/init.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit;
}

header('Content-Type: application/json');

$userId = currentUser()['id'];

global $db;

// Get notifications
$stmt = $db->prepare("
    SELECT * FROM notifications
    WHERE user_id = :user_id
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([':user_id' => $userId]);
$notifications = $stmt->fetchAll();

// Format for display
$formatted = array_map(function($n) {
    return [
        'id' => $n['id'],
        'title' => clean($n['title']),
        'message' => clean($n['message']),
        'type' => $n['type'],
        'link' => $n['link'],
        'is_read' => (bool)$n['is_read'],
        'time_ago' => timeAgo($n['created_at'])
    ];
}, $notifications);

echo json_encode([
    'success' => true,
    'notifications' => $formatted
]);
