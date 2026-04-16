<?php
/**
 * SERVICE CATEGORIES - DELETE PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

global $db;

$id = intval($_GET['id'] ?? 0);

if (!$id) {
    setFlash('danger', 'Invalid category');
    redirect('admin/service-categories/index.php');
}

// Check if category is in use
$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM projects WHERE service_category_id = ?");
$stmt->execute([$id]);
$projects_using = $stmt->fetch()['cnt'];

if ($projects_using > 0) {
    setFlash('danger', 'Cannot delete category in use by ' . $projects_using . ' project(s)');
} else {
    $stmt = $db->prepare("SELECT category_name FROM service_categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
    
    if ($category) {
        $stmt = $db->prepare("DELETE FROM service_categories WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('DELETE', 'service_categories', $id, "Category deleted: " . $category['category_name']);
        setFlash('success', 'Category deleted successfully!');
    }
}

redirect('admin/service-categories/index.php');
