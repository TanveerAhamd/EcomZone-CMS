<?php
/**
 * SERVICE CATEGORIES - CRUD PAGE
 * Manage service categories used in projects
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Service Categories';

global $db;

// Create table if it doesn't exist
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS service_categories (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            category_name VARCHAR(100) NOT NULL,
            description LONGTEXT,
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY(category_name),
            KEY(status)
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Add service_category_id column to projects table if it doesn't exist
try {
    $db->exec("ALTER TABLE projects ADD COLUMN service_category_id INT(11) DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

// Get all categories
$stmt = $db->prepare("
    SELECT 
        c.*, 
        COUNT(DISTINCT p.id) as projects_using
    FROM service_categories c
    LEFT JOIN projects p ON c.id = p.service_category_id
    GROUP BY c.id
    ORDER BY c.category_name
");
$stmt->execute();
$categories = $stmt->fetchAll();

// Handle delete
if ($_GET['action'] === 'delete' && $_GET['id']) {
    $id = sanitizeInt($_GET['id']);
    
    // Check if category is in use
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM projects WHERE service_category_id = ?");
    $stmt->execute([$id]);
    $inUse = $stmt->fetch()['cnt'] > 0;
    
    if ($inUse) {
        setFlash('danger', 'Cannot delete category - it is being used in projects');
    } else {
        $stmt = $db->prepare("DELETE FROM service_categories WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('DELETE', 'service_categories', $id, "Service category deleted");
        setFlash('success', 'Category deleted successfully!');
    }
    redirect('admin/service-categories/index.php');
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">
        <i class="fas fa-folder-open"></i> Service Categories
    </h1>
    <a href="<?php echo APP_URL; ?>/admin/service-categories/add.php" class="btn" style="background: #6418C3; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none; font-weight: 600;">
        <i class="fas fa-plus"></i> New Category
    </a>
</div>

<?php echo flashAlert(); ?>

<div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden;">
    <table style="width: 100%; border-collapse: collapse;">
        <thead style="background: #f8f9ff; border-bottom: 2px solid #6418C3;">
            <tr>
                <th style="padding: 15px; text-align: left; font-weight: 600; color: #6418C3;">Category Name</th>
                <th style="padding: 15px; text-align: left; font-weight: 600; color: #6418C3;">Description</th>
                <th style="padding: 15px; text-align: center; font-weight: 600; color: #6418C3;">Projects Using</th>
                <th style="padding: 15px; text-align: center; font-weight: 600; color: #6418C3;">Status</th>
                <th style="padding: 15px; text-align: center; font-weight: 600; color: #6418C3;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($categories as $cat): ?>
            <tr style="border-bottom: 1px solid #f0f0f0;">
                <td style="padding: 15px; font-weight: 600; color: #333;">
                    <i class="fas fa-tag"></i> <?php echo clean($cat['category_name']); ?>
                </td>
                <td style="padding: 15px; color: #666; font-size: 0.9rem;">
                    <?php echo clean(substr($cat['description'] ?? '', 0, 60)); ?><?php echo strlen($cat['description'] ?? '') > 60 ? '...' : ''; ?>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <span style="background: #E5F2FF; color: #1EAAE7; padding: 6px 12px; border-radius: 20px; font-weight: 600; display: inline-block;">
                        <?php echo $cat['projects_using']; ?>
                    </span>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <?php if ($cat['status'] === 'active'): ?>
                        <span style="background: #E5F5E5; color: #2BC155; padding: 6px 12px; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                            ACTIVE
                        </span>
                    <?php else: ?>
                        <span style="background: #FFE5E5; color: #FF5E5E; padding: 6px 12px; border-radius: 4px; font-weight: 600; font-size: 0.85rem;">
                            INACTIVE
                        </span>
                    <?php endif; ?>
                </td>
                <td style="padding: 15px; text-align: center;">
                    <a href="<?php echo APP_URL; ?>/admin/service-categories/add.php?id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-warning" style="padding: 6px 12px; text-decoration: none; border-radius: 4px; display: inline-block;">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if ($cat['projects_using'] == 0): ?>
                    <a href="?action=delete&id=<?php echo $cat['id']; ?>" class="btn btn-sm btn-danger" style="padding: 6px 12px; text-decoration: none; border-radius: 4px; display: inline-block;" onclick="return confirm('Delete this category?');">
                        <i class="fas fa-trash"></i> Delete
                    </a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (count($categories) === 0): ?>
            <tr>
                <td colspan="6" style="padding: 40px; text-align: center; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
                    No service categories yet
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
