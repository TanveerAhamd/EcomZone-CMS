<?php
/**
 * PROJECTS - ADD/EDIT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Create Project';

global $db;

// Ensure service_category_id column exists
try {
    $db->exec("ALTER TABLE projects ADD COLUMN service_category_id INT(11) DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

$project = null;
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $client_id = sanitizeInt($_POST['client_id'] ?? 0);
            $service_category_id = sanitizeInt($_POST['service_category_id'] ?? 0) ?: null;
            $project_name = $_POST['project_name'] ?? '';
            $project_code = $_POST['project_code'] ?? '';
            $description = $_POST['description'] ?? '';
            $deadline = $_POST['deadline'] ?: null;
            $assigned_to = sanitizeInt($_POST['assigned_to'] ?? 0) ?: null;
            $status = $_POST['status'] ?? 'active';
            
            if (!$project_name) {
                setFlash('danger', 'Project name is required');
            } elseif (!$client_id) {
                setFlash('danger', 'Client is required');
            } else {
                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE projects SET 
                            client_id = ?, service_category_id = ?, project_name = ?,
                            project_code = ?, description = ?, deadline = ?,
                            assigned_to = ?, status = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $client_id, $service_category_id, $project_name,
                        $project_code, $description, $deadline,
                        $assigned_to, $status, $id
                    ]);
                    logActivity('UPDATE', 'projects', $id, "Project updated");
                    setFlash('success', 'Project updated successfully!');
                } else {
                    $project_code = $project_code ?: generateCode('PRJ', 'projects', 'project_code');
                    
                    $stmt = $db->prepare("
                        INSERT INTO projects (client_id, service_category_id, project_name, project_code, description, deadline, assigned_to, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([
                        $client_id, $service_category_id, $project_name, $project_code, $description, $deadline, $assigned_to, $status
                    ]);
                    logActivity('CREATE', 'projects', $db->lastInsertId(), "Project created");
                    setFlash('success', 'Project created successfully!');
                }
                redirect('admin/projects/index.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $project ? 'Edit Project: ' . clean($project['project_name']) : 'Create New Project'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <?php echo csrfField(); ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Project Name <span style="color: #FF5E5E;">*</span></label>
            <input type="text" name="project_name" class="form-control" value="<?php echo clean($project['project_name'] ?? ''); ?>" placeholder="Enter project name" required style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Client <span style="color: #FF5E5E;">*</span></label>
            <select name="client_id" class="form-control" required style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
                <option value="">-- Select Client --</option>
                <?php
                $stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name");
                $stmt->execute();
                $clients = $stmt->fetchAll();
                foreach ($clients as $c):
                ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($project['client_id'] ?? null) == $c['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($c['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Service Category</label>
            <select name="service_category_id" class="form-control" style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
                <option value="">-- Select Category --</option>
                <?php
                $stmt = $db->prepare("SELECT id, category_name FROM service_categories WHERE status = 'active' ORDER BY category_name");
                $stmt->execute();
                $categories = $stmt->fetchAll();
                foreach ($categories as $cat):
                ?>
                <option value="<?php echo $cat['id']; ?>" <?php echo ($project['service_category_id'] ?? null) == $cat['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($cat['category_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #999; display: block; margin-top: 5px;">Optional - Select category to assign custom services later</small>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Project Code</label>
            <input type="text" name="project_code" class="form-control" value="<?php echo clean($project['project_code'] ?? ''); ?>" placeholder="Auto-generated if empty" style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
            <small style="color: #999; display: block; margin-top: 5px;">Optional - Auto-generated if left empty</small>
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Deadline</label>
            <input type="date" name="deadline" class="form-control" value="<?php echo $project['deadline'] ?? ''; ?>" placeholder="mm/dd/yyyy" style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
            <small style="color: #999; display: block; margin-top: 5px;">Optional - Leave empty if not set</small>
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Assigned To</label>
            <select name="assigned_to" class="form-control" style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
                <option value="">-- Select User --</option>
                <?php
                $stmt = $db->prepare("SELECT id, name FROM users ORDER BY name");
                $stmt->execute();
                $users = $stmt->fetchAll();
                foreach ($users as $u):
                ?>
                <option value="<?php echo $u['id']; ?>" <?php echo ($project['assigned_to'] ?? null) == $u['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($u['name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #999; display: block; margin-top: 5px;">Optional - Assign project to team member</small>
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Status</label>
            <select name="status" class="form-control" style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;">
                <option value="active" <?php echo ($project['status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="on_hold" <?php echo ($project['status'] ?? null) === 'on_hold' ? 'selected' : ''; ?>>On Hold</option>
                <option value="completed" <?php echo ($project['status'] ?? null) === 'completed' ? 'selected' : ''; ?>>Completed</option>
                <option value="cancelled" <?php echo ($project['status'] ?? null) === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label style="font-weight: 600; margin-bottom: 8px; display: block;">Description</label>
        <textarea name="description" class="form-control" rows="4" placeholder="Add project description..." style="border-radius: 6px; border: 1px solid #ddd; padding: 10px;"><?php echo clean($project['description'] ?? ''); ?></textarea>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 25px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-save"></i> <?php echo $project ? 'Update Project' : 'Create Project'; ?>
        </button>
        <a href="/EcomZone-CMS/admin/projects/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
