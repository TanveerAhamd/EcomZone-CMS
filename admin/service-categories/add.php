<?php
/**
 * SERVICE CATEGORIES - ADD/EDIT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Manage Category';

global $db;

$category = null;
$id = $_GET['id'] ?? null;

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

if ($id) {
    $stmt = $db->prepare("SELECT * FROM service_categories WHERE id = ?");
    $stmt->execute([$id]);
    $category = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $category_name = $_POST['category_name'] ?? '';
            $description = $_POST['description'] ?? '';
            $status = $_POST['status'] ?? 'active';
            
            if (!$category_name) {
                setFlash('danger', 'Category name is required');
            } else {
                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE service_categories 
                        SET category_name = ?, description = ?, status = ? 
                        WHERE id = ?
                    ");
                    $stmt->execute([$category_name, $description, $status, $id]);
                    logActivity('UPDATE', 'service_categories', $id, "Category updated: " . $category_name);
                    setFlash('success', 'Category updated successfully!');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO service_categories (category_name, description, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$category_name, $description, $status, currentUser()['id']]);
                    logActivity('CREATE', 'service_categories', $db->lastInsertId(), "Category created: " . $category_name);
                    setFlash('success', 'Category created successfully!');
                }
                redirect('admin/service-categories/index.php');
            }
        } catch (Exception $e) {
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                setFlash('danger', 'This category name already exists');
            } else {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 25px;">
    <a href="/EcomZone-CMS/admin/service-categories/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Categories
    </a>
</div>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $category ? 'Edit Category: ' . clean($category['category_name']) : 'Create New Service Category'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 700px;">
    <?php echo csrfField(); ?>

    <div class="form-group" style="margin-bottom: 20px;">
        <label style="font-weight: 600; margin-bottom: 8px; display: block;"><i class="fas fa-tag"></i> Category Name *</label>
        <input type="text" name="category_name" class="form-control" 
               value="<?php echo clean($category['category_name'] ?? ''); ?>" 
               placeholder="e.g., UAE Tax Compliance, Digital Marketing, etc." required
               style="border-radius: 6px; border: 1px solid #e0e0e0; padding: 10px;">
    </div>

    <div class="form-group" style="margin-bottom: 20px;">
        <label style="font-weight: 600; margin-bottom: 8px; display: block;"><i class="fas fa-align-left"></i> Description</label>
        <textarea name="description" class="form-control" rows="5" 
                  placeholder="Describe this service category..."
                  style="border-radius: 6px; border: 1px solid #e0e0e0; padding: 10px;"><?php echo clean($category['description'] ?? ''); ?></textarea>
        <small style="color: #999; display: block; margin-top: 5px;">Help users understand what services fall under this category</small>
    </div>

    <div class="form-group" style="margin-bottom: 25px;">
        <label style="font-weight: 600; margin-bottom: 8px; display: block;"><i class="fas fa-toggle-on"></i> Status</label>
        <select name="status" class="form-control" style="border-radius: 6px; border: 1px solid #e0e0e0; padding: 10px;">
            <option value="active" <?php echo ($category['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo ($category['status'] ?? null) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <small style="color: #999; display: block; margin-top: 5px;">Inactive categories won't appear in project creation</small>
    </div>

    <?php if ($category): ?>
    <div style="background: #f8f9ff; border-radius: 8px; padding: 15px; margin-bottom: 25px;">
        <h5 style="margin: 0 0 10px 0; font-weight: 600; color: #6418C3;">Statistics</h5>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; font-size: 0.9rem;">
            <?php
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM projects WHERE service_category_id = ?");
            $stmt->execute([$id]);
            $projects_using = $stmt->fetch()['cnt'];
            
            $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM project_services WHERE category_id = ?");
            $stmt->execute([$id]);
            $services_count = $stmt->fetch()['cnt'];
            ?>
            <div>
                <strong>Projects using this:</strong> <?php echo $projects_using; ?>
            </div>
            <div>
                <strong>Total services:</strong> <?php echo $services_count; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <div style="display: flex; gap: 10px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none; color: white; padding: 12px 24px; border-radius: 6px; font-weight: 600; cursor: pointer;">
            <i class="fas fa-save"></i> <?php echo $category ? 'Update Category' : 'Create Category'; ?>
        </button>
        <a href="/EcomZone-CMS/admin/service-categories/index.php" class="btn btn-secondary" style="border: 1px solid #e0e0e0; color: #333; padding: 12px 24px; border-radius: 6px; font-weight: 600; text-decoration: none;">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
