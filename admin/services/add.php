<?php
/**
 * SERVICES - ADD/EDIT PAGE (COMPLETE CRUD)
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Manage Service';

global $db;

$service = null;
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM services WHERE id = ?");
    $stmt->execute([$id]);
    $service = $stmt->fetch();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $service_name = $_POST['service_name'] ?? '';
            $category = $_POST['category'] ?? 'tax_compliance';
            $description = $_POST['description'] ?? '';
            $price = (float)($_POST['price'] ?? 0);
            $renewal_period = $_POST['renewal_period'] ?? 'yearly';
            $status = $_POST['status'] ?? 'active';
            
            if (!$service_name || $price <= 0) {
                setFlash('danger', 'Service name and valid price required');
            } else {
                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE services SET service_name=?, category=?, description=?, price=?, renewal_period=?, status=? WHERE id=?
                    ");
                    $stmt->execute([$service_name, $category, $description, $price, $renewal_period, $status, $id]);
                    logActivity('UPDATE', 'services', $id, "Service updated: " . $service_name);
                    setFlash('success', 'Service updated successfully!');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO services (service_name, category, description, price, renewal_period, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([$service_name, $category, $description, $price, $renewal_period, $status]);
                    logActivity('CREATE', 'services', $db->lastInsertId(), "Service created: " . $service_name);
                    setFlash('success', 'Service created successfully!');
                }
                redirect('admin/services/index.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $service ? 'Edit Service: ' . clean($service['service_name']) : 'Create New Service'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 600px;">
    <?php echo csrfField(); ?>

    <div class="form-group">
        <label>Service Name *</label>
        <input type="text" name="service_name" class="form-control" value="<?php echo clean($service['service_name'] ?? ''); ?>" required>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div class="form-group">
            <label>Category</label>
            <select name="category" class="form-control">
                <option value="tax_compliance" <?php echo ($service['category'] ?? 'tax_compliance') === 'tax_compliance' ? 'selected' : ''; ?>>Tax Compliance</option>
                <option value="company_setup" <?php echo ($service['category'] ?? null) === 'company_setup' ? 'selected' : ''; ?>>Company Setup</option>
                <option value="accounting" <?php echo ($service['category'] ?? null) === 'accounting' ? 'selected' : ''; ?>>Accounting</option>
                <option value="consulting" <?php echo ($service['category'] ?? null) === 'consulting' ? 'selected' : ''; ?>>Consulting</option>
                <option value="legal" <?php echo ($service['category'] ?? null) === 'legal' ? 'selected' : ''; ?>>Legal</option>
            </select>
        </div>

        <div class="form-group">
            <label>Renewal Period</label>
            <select name="renewal_period" class="form-control">
                <option value="monthly" <?php echo ($service['renewal_period'] ?? null) === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                <option value="quarterly" <?php echo ($service['renewal_period'] ?? null) === 'quarterly' ? 'selected' : ''; ?>>Quarterly</option>
                <option value="half_yearly" <?php echo ($service['renewal_period'] ?? null) === 'half_yearly' ? 'selected' : ''; ?>>Half Yearly</option>
                <option value="yearly" <?php echo ($service['renewal_period'] ?? 'yearly') === 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                <option value="as_needed" <?php echo ($service['renewal_period'] ?? null) === 'as_needed' ? 'selected' : ''; ?>>As Needed</option>
            </select>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 15px;">
        <div class="form-group">
            <label>Price (₨) *</label>
            <input type="number" name="price" class="form-control" value="<?php echo $service['price'] ?? 0; ?>" step="0.01" min="0" required>
        </div>

        <div class="form-group">
            <label>Status</label>
            <select name="status" class="form-control">
                <option value="active" <?php echo ($service['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo ($service['status'] ?? null) === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                <option value="discontinued" <?php echo ($service['status'] ?? null) === 'discontinued' ? 'selected' : ''; ?>>Discontinued</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Description</label>
        <textarea name="description" class="form-control" rows="4"><?php echo clean($service['description'] ?? ''); ?></textarea>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 25px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-save"></i> <?php echo $service ? 'Update Service' : 'Create Service'; ?>
        </button>
        <a href="/EcomZone-CMS/admin/services/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
