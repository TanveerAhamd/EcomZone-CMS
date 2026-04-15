<?php
/**
 * PROJECTS - ADD TASK HANDLER
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            global $db;
            
            $project_id = sanitizeInt($_POST['project_id'] ?? 0);
            $task_name = $_POST['task_name'] ?? '';
            $priority = $_POST['priority'] ?? 'medium';
            $due_date = $_POST['due_date'] ?? null;
            $description = $_POST['description'] ?? '';
            
            if (!$task_name) {
                setFlash('danger', 'Task name is required');
            } else {
                $stmt = $db->prepare("
                    INSERT INTO tasks (project_id, task_name, description, priority, due_date, status, created_at)
                    VALUES (?, ?, ?, ?, ?, 'todo', NOW())
                ");
                $stmt->execute([$project_id, $task_name, $description, $priority, $due_date]);
                
                logActivity('CREATE', 'tasks', $db->lastInsertId(), "Task created: " . $task_name);
                setFlash('success', 'Task added successfully!');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

redirect('admin/projects/view.php?id=' . ($_POST['project_id'] ?? 0));
