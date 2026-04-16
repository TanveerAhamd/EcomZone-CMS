<?php
/**
 * PROJECTS - ASSIGN CUSTOM SERVICES
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

global $db;

$project_id = sanitizeInt($_GET['id'] ?? 0);

if (!$project_id) {
    setFlash('danger', 'Invalid project');
    redirect('admin/projects/index.php');
}

// Ensure project_services table exists
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS project_services (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            project_id INT(11) NOT NULL,
            service_name VARCHAR(150) NOT NULL,
            price DECIMAL(12, 2) DEFAULT 0.00,
            start_date DATE,
            expiry_date DATE,
            status ENUM('active', 'expired', 'cancelled') DEFAULT 'active',
            created_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            KEY(project_id),
            KEY(status)
        )
    ");
} catch (Exception $e) {
    // Table might already exist
}

// Get project details
$stmt = $db->prepare("SELECT p.*, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

$pageTitle = 'Manage Services - ' . $project['project_name'];

// Handle form submission - Add/Edit service
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $service_id = sanitizeInt($_POST['service_id'] ?? 0);
            $service_name = $_POST['service_name'] ?? '';
            $price = floatval($_POST['price'] ?? 0);
            $start_date = $_POST['start_date'] ?? null;
            $expiry_date = $_POST['expiry_date'] ?? null;
            $status = $_POST['status'] ?? 'active';
            
            if (!$service_name) {
                setFlash('danger', 'Service name is required');
            } else {
                if ($service_id) {
                    $stmt = $db->prepare("
                        UPDATE project_services 
                        SET service_name = ?, price = ?, start_date = ?, expiry_date = ?, status = ?
                        WHERE id = ? AND project_id = ?
                    ");
                    $stmt->execute([$service_name, $price, $start_date ?: null, $expiry_date ?: null, $status, $service_id, $project_id]);
                    setFlash('success', 'Service updated successfully!');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO project_services (project_id, service_name, price, start_date, expiry_date, status, created_by, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$project_id, $service_name, $price, $start_date ?: null, $expiry_date ?: null, $status, currentUser()['id']]);
                    setFlash('success', 'Service added successfully!');
                }
                redirect("admin/projects/services.php?id=" . $project_id);
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

// Handle delete
if ($_GET['action'] === 'delete' && $_GET['service_id']) {
    $service_id = sanitizeInt($_GET['service_id']);
    $stmt = $db->prepare("DELETE FROM project_services WHERE id = ? AND project_id = ?");
    $stmt->execute([$service_id, $project_id]);
    setFlash('success', 'Service deleted successfully!');
    redirect("admin/projects/services.php?id=" . $project_id);
}

// Get services for this project
$stmt = $db->prepare("
    SELECT * FROM project_services 
    WHERE project_id = ? AND status != 'cancelled'
    ORDER BY created_at DESC
");
$stmt->execute([$project_id]);
$services = $stmt->fetchAll();

// Calculate totals
$total_price = 0;
$active_count = 0;

foreach ($services as $s) {
    if ($s['status'] !== 'cancelled') {
        $total_price += $s['price'];
        if ($s['status'] === 'active') $active_count++;
    }
}

$edit_service = null;
if ($_GET['edit'] ?? null) {
    $edit_id = sanitizeInt($_GET['edit']);
    $stmt = $db->prepare("SELECT * FROM project_services WHERE id = ? AND project_id = ?");
    $stmt->execute([$edit_id, $project_id]);
    $edit_service = $stmt->fetch();
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center;">
    <div>
        <a href="/EcomZone-CMS/admin/projects/view.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to Project
        </a>
    </div>
    <h1 style="margin: 0; font-weight: 700; font-size: 1.8rem;">
        <i class="fas fa-cogs"></i> Services for <?php echo clean($project['project_name']); ?>
    </h1>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Total Services</div>
        <div style="font-size: 2rem; font-weight: 700; color: #6418C3;"><?php echo count($services); ?></div>
    </div>
    <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Active</div>
        <div style="font-size: 2rem; font-weight: 700; color: #28a745;"><?php echo $active_count; ?></div>
    </div>
    <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
        <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Total Value</div>
        <div style="font-size: 2rem; font-weight: 700; color: #2196F3;"><?php echo formatCurrency($total_price); ?></div>
    </div>
</div>

<?php echo flashAlert(); ?>

<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 25px; margin-bottom: 25px;">
    <!-- Add/Edit Form -->
    <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-plus-circle"></i> <?php echo $edit_service ? 'Edit Service' : 'Add New Service'; ?>
        </h3>

        <form method="POST">
            <?php echo csrfField(); ?>
            <input type="hidden" name="service_id" value="<?php echo $edit_service['id'] ?? 0; ?>">

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 5px; display: block;">Service Name *</label>
                <input type="text" name="service_name" class="form-control" 
                       value="<?php echo clean($edit_service['service_name'] ?? ''); ?>" 
                       placeholder="e.g., Annual Tax Compliance, Monthly Audit" required
                       style="border-radius: 6px; border: 1px solid #e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 5px; display: block;">Price</label>
                <input type="number" name="price" class="form-control" step="0.01" 
                       value="<?php echo $edit_service['price'] ?? 0; ?>"
                       placeholder="0.00"
                       style="border-radius: 6px; border: 1px solid #e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 5px; display: block;">Start Date</label>
                <input type="date" name="start_date" class="form-control" 
                       value="<?php echo $edit_service['start_date'] ?? ''; ?>"
                       style="border-radius: 6px; border: 1px solid #e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 15px;">
                <label style="font-weight: 600; margin-bottom: 5px; display: block;">Expiry Date</label>
                <input type="date" name="expiry_date" class="form-control" 
                       value="<?php echo $edit_service['expiry_date'] ?? ''; ?>"
                       style="border-radius: 6px; border: 1px solid #e0e0e0;">
            </div>

            <div class="form-group" style="margin-bottom: 20px;">
                <label style="font-weight: 600; margin-bottom: 5px; display: block;">Status</label>
                <select name="status" class="form-control" style="border-radius: 6px; border: 1px solid #e0e0e0;">
                    <option value="active" <?php echo ($edit_service['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="expired" <?php echo ($edit_service['status'] ?? null) === 'expired' ? 'selected' : ''; ?>>Expired</option>
                    <option value="cancelled" <?php echo ($edit_service['status'] ?? null) === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                </select>
            </div>

            <div style="display: flex; gap: 10px;">
                <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none; color: white; flex: 1; border-radius: 6px; font-weight: 600; padding: 10px;">
                    <i class="fas fa-save"></i> <?php echo $edit_service ? 'Update' : 'Add'; ?>
                </button>
                <?php if ($edit_service): ?>
                    <a href="?id=<?php echo $project_id; ?>" class="btn btn-secondary" style="border: 1px solid #e0e0e0; flex: 1; border-radius: 6px; text-decoration: none; text-align: center; padding: 10px; font-weight: 600;">
                        <i class="fas fa-times"></i> Cancel
                    </a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- Services List -->
    <div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <h3 style="margin-top: 0; margin-bottom: 20px; font-weight: 700;">
            <i class="fas fa-list"></i> Services List
        </h3>

        <?php if (empty($services)): ?>
            <div style="text-align: center; color: #999; padding: 40px 20px;">
                <i class="fas fa-inbox" style="font-size: 2.5rem; margin-bottom: 10px; display: block;"></i>
                <p>No services added yet. Add one using the form on the left.</p>
            </div>
        <?php else: ?>
            <div style="max-height: 600px; overflow-y: auto;">
                <?php foreach ($services as $s): ?>
                    <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 12px; margin-bottom: 10px; background: <?php echo $s['status'] === 'active' ? '#f0f9ff' : '#fff5f5'; ?>;">
                        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                            <div style="flex: 1;">
                                <div style="font-weight: 700; margin-bottom: 2px;"><?php echo clean($s['service_name']); ?></div>
                                <div style="font-size: 0.9rem; color: #666;">
                                    <i class="fas fa-tag"></i> <?php echo formatCurrency($s['price']); ?>
                                </div>
                            </div>
                            <span style="display: inline-block; padding: 4px 8px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; 
                                         background: <?php echo $s['status'] === 'active' ? '#d4edda' : '#f8d7da'; ?>; 
                                         color: <?php echo $s['status'] === 'active' ? '#155724' : '#721c24'; ?>;">
                                <?php echo ucfirst($s['status']); ?>
                            </span>
                        </div>
                        
                        <div style="font-size: 0.85rem; color: #999; margin-bottom: 10px;">
                            <?php if ($s['start_date']): ?>
                                <i class="fas fa-calendar-check"></i> <?php echo formatDate($s['start_date']); ?> - 
                            <?php endif; ?>
                            <?php if ($s['expiry_date']): ?>
                                <i class="fas fa-calendar-times"></i> <?php echo formatDate($s['expiry_date']); ?>
                            <?php endif; ?>
                        </div>

                        <div style="display: flex; gap: 8px;">
                            <a href="?id=<?php echo $project_id; ?>&edit=<?php echo $s['id']; ?>" class="btn btn-sm btn-warning" style="padding: 5px 10px; font-size: 0.85rem; border-radius: 4px; background: #ffc107; border: none; color: white; text-decoration: none; cursor: pointer;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                            <a href="?id=<?php echo $project_id; ?>&action=delete&service_id=<?php echo $s['id']; ?>" class="btn btn-sm btn-danger" 
                               style="padding: 5px 10px; font-size: 0.85rem; border-radius: 4px; background: #dc3545; border: none; color: white; text-decoration: none; cursor: pointer;"
                               onclick="return confirm('Delete this service?');">
                                <i class="fas fa-trash"></i> Delete
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<div style="background: #f8f9ff; border-radius: 8px; padding: 20px; border-left: 4px solid #6418C3;">
    <p style="margin: 0; color: #666;">
        <i class="fas fa-info-circle"></i> <strong>Info:</strong> These services are custom for this project. They will appear in the project view and can be renewed or expired based on expiry dates.
    </p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
