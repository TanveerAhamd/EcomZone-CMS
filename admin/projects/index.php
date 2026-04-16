<?php
/**
 * PROJECTS LIST PAGE - WITH PROGRESS & STATUS
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Projects';

global $db;

// Handle status update via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $project_id = sanitizeInt($_POST['project_id'] ?? 0);
    if (!$project_id) {
        setFlash('danger', 'Invalid project');
    } else {
        try {
            $new_status = $_POST['status'] ?? '';
            if (in_array($new_status, ['active', 'on_hold', 'completed', 'cancelled'])) {
                $stmt = $db->prepare("UPDATE projects SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $project_id]);
                logActivity('UPDATE', 'projects', $project_id, "Status updated to: " . $new_status);
                setFlash('success', 'Project status updated!');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error updating status');
        }
    }
    redirect('admin/projects/index.php');
}

// Ensure required tables exist
try {
    $db->exec("ALTER TABLE projects ADD COLUMN service_category_id INT(11) DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

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

// Get all projects with service categories
$stmt = $db->prepare("
    SELECT p.*, c.client_name, u.name as assigned_user, sc.category_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    LEFT JOIN service_categories sc ON p.service_category_id = sc.id
    ORDER BY p.deadline ASC
");
$stmt->execute();
$projects = $stmt->fetchAll();

// Calculate progress for each project based on tasks
foreach ($projects as &$proj) {
    $task_stmt = $db->prepare("
        SELECT status, COUNT(*) as count 
        FROM tasks 
        WHERE project_id = ? 
        GROUP BY status
    ");
    $task_stmt->execute([$proj['id']]);
    $task_counts = $task_stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    
    $total_tasks = array_sum($task_counts);
    $done_tasks = $task_counts['done'] ?? 0;
    
    if ($total_tasks > 0) {
        $proj['progress_percent'] = round(($done_tasks / $total_tasks) * 100);
    } else {
        $proj['progress_percent'] = 0;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
    }

    .page-header h1 {
        font-size: 2rem;
        font-weight: 700;
        margin: 0;
        color: #1D1D1D;
    }

    .btn-add {
        background: #6418C3;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .btn-add:hover {
        background: #5910b8;
        transform: translateY(-2px);
    }

    .progress-container {
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .progress-bar-container {
        flex: 1;
        height: 6px;
        background: #e0e0e0;
        border-radius: 3px;
        overflow: hidden;
    }

    .progress-bar-fill {
        height: 100%;
        background: linear-gradient(90deg, #6418C3, #8b3ed9);
        border-radius: 3px;
        transition: width 0.3s ease;
    }

    .progress-text {
        min-width: 40px;
        text-align: right;
        font-weight: 600;
        color: #333;
        font-size: 0.9rem;
    }

    .status-select {
        padding: 8px 12px;
        border: none;
        border-radius: 6px;
        font-size: 0.9rem;
        font-weight: 600;
        cursor: pointer;
        color: white;
        transition: all 0.3s ease;
    }

    .status-select:hover {
        transform: scale(1.05);
    }

    .action-buttons {
        display: flex;
        gap: 6px;
        justify-content: center;
    }

    .action-btn {
        padding: 8px 12px;
        font-size: 0.85rem;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        transition: all 0.3s ease;
        color: white;
    }

    .btn-view {
        background: #1EAAE7;
    }

    .btn-view:hover {
        background: #1591c9;
        transform: translateY(-2px);
    }

    .btn-edit {
        background: #FFC107;
    }

    .btn-edit:hover {
        background: #e0a800;
        transform: translateY(-2px);
    }

    .btn-delete {
        background: #FF5E5E;
    }

    .btn-delete:hover {
        background: #e84d4d;
        transform: translateY(-2px);
    }

    table {
        width: 100%;
        border-collapse: collapse;
    }

    thead {
        background: #f8f9ff;
        border-bottom: 2px solid #6418C3;
    }

    th {
        padding: 15px;
        text-align: left;
        font-weight: 600;
        color: #6418C3;
    }

    td {
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    tbody tr {
        transition: all 0.3s ease;
    }

    tbody tr:hover {
        background: #f9f9f9;
    }
</style>

<div class="page-header">
    <div>
        <h1>Projects <span style="background: rgba(100, 24, 195, 0.1); color: #6418C3; padding: 4px 12px; border-radius: 20px; font-size: 0.85rem; margin-left: 10px;"><?php echo count($projects); ?></span></h1>
    </div>
    <a href="<?php echo APP_URL; ?>/admin/projects/add.php" class="btn-add">
        <i class="fas fa-plus"></i> New Project
    </a>
</div>

<?php echo flashAlert(); ?>

<div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden;">
    <table>
        <thead>
            <tr>
                <th>Code</th>
                <th>Project Name</th>
                <th>Client</th>
                <th>Service Category</th>
                <th style="text-align: center;">Deadline</th>
                <th style="text-align: center;">Progress</th>
                <th style="text-align: center;">Status</th>
                <th style="text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($projects)): ?>
            <tr>
                <td colspan="8" style="text-align: center; padding: 60px 20px;">
                    <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; display: block; opacity: 0.5; color: #999;"></i>
                    <p style="margin: 10px 0 0 0; font-size: 1.1rem; color: #999;">
                        No projects found. 
                        <a href="<?php echo APP_URL; ?>/admin/projects/add.php" style="color: #6418C3; text-decoration: none; font-weight: 600;">Create one now</a>
                    </p>
                </td>
            </tr>
            <?php else: ?>
            <?php foreach ($projects as $project): 
                $status_color = match($project['status']) {
                    'completed' => '#2BC155',
                    'on_hold' => '#FF9B52',
                    'cancelled' => '#FF5E5E',
                    default => '#1EAAE7'
                };
                
                $progress_color = match(true) {
                    $project['progress_percent'] == 100 => '#2BC155',
                    $project['progress_percent'] >= 75 => '#4CAF50',
                    $project['progress_percent'] >= 50 => '#FFD700',
                    $project['progress_percent'] >= 25 => '#FF9B52',
                    default => '#E0E0E0'
                };
            ?>
            <tr>
                <td>
                    <code style="background: #f0f0f0; padding: 6px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; color: #333;">
                        <?php echo clean($project['project_code']); ?>
                    </code>
                </td>
                <td>
                    <strong style="color: #333; font-size: 0.95rem;"><?php echo clean($project['project_name']); ?></strong>
                </td>
                <td>
                    <span style="color: #666; font-size: 0.9rem;"><?php echo clean($project['client_name'] ?? '-'); ?></span>
                </td>
                <td>
                    <span style="display: inline-block; background: #E5F2FF; color: #6418C3; padding: 6px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 500;">
                        <?php echo clean($project['category_name'] ?? '📌 Uncategorized'); ?>
                    </span>
                </td>
                <td style="text-align: center;">
                    <small style="color: #666;"><?php echo formatDate($project['deadline']) ?: '-'; ?></small>
                </td>
                <td>
                    <div class="progress-container">
                        <div class="progress-bar-container">
                            <div class="progress-bar-fill" style="width: <?php echo $project['progress_percent']; ?>%; background: <?php echo $progress_color; ?>;"></div>
                        </div>
                        <span class="progress-text"><?php echo $project['progress_percent']; ?>%</span>
                    </div>
                </td>
                <td style="text-align: center;">
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="project_id" value="<?php echo $project['id']; ?>">
                        <select name="status" class="status-select" onchange="this.form.submit();" style="background: <?php echo $status_color; ?>;">
                            <option value="active" <?php echo $project['status'] === 'active' ? 'selected' : ''; ?> style="background: white; color: #333;">✓ Active</option>
                            <option value="on_hold" <?php echo $project['status'] === 'on_hold' ? 'selected' : ''; ?> style="background: white; color: #333;">⏸ On Hold</option>
                            <option value="completed" <?php echo $project['status'] === 'completed' ? 'selected' : ''; ?> style="background: white; color: #333;">✔ Completed</option>
                            <option value="cancelled" <?php echo $project['status'] === 'cancelled' ? 'selected' : ''; ?> style="background: white; color: #333;">✕ Cancelled</option>
                        </select>
                    </form>
                </td>
                <td style="text-align: center;">
                    <div class="action-buttons">
                        <a href="<?php echo APP_URL; ?>/admin/projects/view.php?id=<?php echo $project['id']; ?>" class="action-btn btn-view" title="View Kanban">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/projects/add.php?id=<?php echo $project['id']; ?>" class="action-btn btn-edit" title="Edit">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="<?php echo APP_URL; ?>/admin/projects/delete.php?id=<?php echo $project['id']; ?>" class="action-btn btn-delete" title="Delete" onclick="return confirm('Are you sure you want to delete this project?');">
                            <i class="fas fa-trash"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
