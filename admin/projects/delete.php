<?php
/**
 * PROJECTS - DELETE HANDLER
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

global $db;

$id = sanitizeInt($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Project not found');
} else {
    try {
        $stmt = $db->prepare("SELECT project_name FROM projects WHERE id = ?");
        $stmt->execute([$id]);
        $project = $stmt->fetch();
        
        if ($project) {
            // Delete related tasks first
            $db->prepare("DELETE FROM tasks WHERE project_id = ?")->execute([$id]);
            
            // Delete related project services
            $db->prepare("DELETE FROM project_services WHERE project_id = ?")->execute([$id]);
            
            // Delete project
            $stmt = $db->prepare("DELETE FROM projects WHERE id = ?");
            $stmt->execute([$id]);
            
            logActivity('DELETE', 'projects', $id, "Project deleted: " . $project['project_name']);
            setFlash('success', 'Project deleted successfully!');
        }
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

redirect('admin/projects/index.php');
