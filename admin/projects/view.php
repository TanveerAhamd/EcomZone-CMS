<?php
/**
 * PROJECTS - VIEW PAGE (WITH KANBAN BOARD)
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Project Details';

global $db;

$project_id = $_GET['id'] ?? null;
if (!$project_id) {
    setFlash('danger', 'Project not found');
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

$stmt = $db->prepare("SELECT p.*, c.client_name, u.name as assigned_to_user, sc.category_name FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    LEFT JOIN service_categories sc ON p.service_category_id = sc.id
    WHERE p.id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

// Get tasks grouped by status
$stmt = $db->prepare("SELECT * FROM tasks WHERE project_id = ? ORDER BY priority DESC, due_date ASC");
$stmt->execute([$project_id]);
$all_tasks = $stmt->fetchAll();

$tasks_by_status = [
    'todo' => [],
    'in_progress' => [],
    'review' => [],
    'done' => []
];

foreach ($all_tasks as $task) {
    $status = $task['status'] ?? 'todo';
    if (!isset($tasks_by_status[$status])) {
        $tasks_by_status[$status] = [];
    }
    $tasks_by_status[$status][] = $task;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_task_status') {
        try {
            $task_id = sanitizeInt($_POST['task_id'] ?? 0);
            $new_status = $_POST['new_status'] ?? 'todo';
            
            if (in_array($new_status, ['todo', 'in_progress', 'review', 'done'])) {
                $stmt = $db->prepare("UPDATE tasks SET status = ? WHERE id = ?");
                $stmt->execute([$new_status, $task_id]);
                logActivity('UPDATE', 'tasks', $task_id, "Task status: " . $new_status);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Invalid status']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'delete_task') {
        try {
            $task_id = sanitizeInt($_POST['task_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM tasks WHERE id = ?");
            $stmt->execute([$task_id]);
            logActivity('DELETE', 'tasks', $task_id, "Task deleted");
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="/EcomZone-CMS/admin/projects/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Projects
    </a>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="fas fa-plus"></i> Add Task
    </button>
</div>

<!-- PROJECT INFO -->
<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Project Name</small>
            <p style="margin: 8px 0 0 0; font-size: 1.2rem; font-weight: 700; color: #333;"><?php echo clean($project['project_name']); ?></p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Project Code</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem; font-family: monospace; color: #555;">
                <span style="background: #f8f9ff; padding: 4px 8px; border-radius: 4px;"><?php echo clean($project['project_code']); ?></span>
            </p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Client</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem; color: #555;"><?php echo clean($project['client_name'] ?? 'N/A'); ?></p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Service Category</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem; color: #555;">
                <?php if ($project['category_name']): ?>
                    <span style="display: inline-block; background: #E8D5FF; color: #6418C3; padding: 4px 10px; border-radius: 20px; font-size: 0.9rem; font-weight: 500;">
                        <?php echo clean($project['category_name']); ?>
                    </span>
                <?php else: ?>
                    <span style="color: #999; font-style: italic;">Not assigned</span>
                <?php endif; ?>
            </p>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Deadline</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem; color: #555;">
                <?php 
                if ($project['deadline']) {
                    $deadline_time = strtotime($project['deadline']);
                    $now_time = time();
                    $days_left = ceil(($deadline_time - $now_time) / (24 * 60 * 60));
                    $color = $days_left < 0 ? '#FF5E5E' : ($days_left < 7 ? '#FF9B52' : '#2BC155');
                    echo '<i class="fas fa-calendar"></i> ' . formatDate($project['deadline']);
                    if ($days_left >= 0) {
                        echo ' <span style="color: ' . $color . '; font-weight: 600; font-size: 0.85rem;">(' . $days_left . ' days)</span>';
                    } else {
                        echo ' <span style="color: #FF5E5E; font-weight: 600; font-size: 0.85rem;">(Overdue)</span>';
                    }
                } else {
                    echo '<span style="color: #999; font-style: italic;">Not set</span>';
                }
                ?>
            </p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Assigned To</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem; color: #555;">
                <?php 
                if ($project['assigned_to_user']) {
                    echo '<i class="fas fa-user-circle"></i> ' . clean($project['assigned_to_user']);
                } else {
                    echo '<span style="color: #999; font-style: italic;">Not assigned</span>';
                }
                ?>
            </p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Status</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;">
                <span class="badge" style="background: <?php 
                $status_color = 
                    $project['status'] === 'completed' ? '#2BC155' : 
                    ($project['status'] === 'on_hold' ? '#FF9B52' : 
                    ($project['status'] === 'cancelled' ? '#FF5E5E' : '#1EAAE7'));
                echo $status_color;
                ?>; color: white; padding: 6px 12px; font-size: 0.9rem;">
                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                </span>
            </p>
        </div>
    </div>

    <?php if ($project['description']): ?>
    <div style="background: #f8f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #1EAAE7;">
        <small style="color: #999; font-weight: 600; text-transform: uppercase; font-size: 0.8rem;">Description</small>
        <p style="margin: 8px 0 0 0; line-height: 1.6; color: #555;">
            <?php echo nl2br(clean($project['description'])); ?>
        </p>
    </div>
    <?php endif; ?>
</div>

<!-- ASSIGNED SERVICES -->
<div style="margin: 30px 0 25px 0;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; font-weight: 600;">Project Services</h3>
        <a href="/EcomZone-CMS/admin/projects/services.php?id=<?php echo $project_id; ?>" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-cogs"></i> Manage Services
        </a>
    </div>

    <?php
    $stmt = $db->prepare("
        SELECT * FROM project_services 
        WHERE project_id = ? AND status != 'cancelled'
        ORDER BY expiry_date ASC, created_at DESC
    ");
    $stmt->execute([$project_id]);
    $project_services = $stmt->fetchAll();
    ?>

    <?php if (count($project_services) > 0): ?>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 15px;">
        <?php foreach ($project_services as $svc):
            $daysLeft = $svc['expiry_date'] ? (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60) : 999;
            $status_color = $svc['status'] === 'expired' || $daysLeft < 0 ? '#FF5E5E' : ($daysLeft < 7 ? '#FF9B52' : ($daysLeft < 30 ? '#FFD700' : '#2BC155'));
        ?>
        <div style="background: white; border-radius: 8px; border-left: 4px solid <?php echo $status_color; ?>; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.06);">
            <h5 style="margin: 0 0 8px 0; color: #333; font-weight: 600;">
                <?php echo clean($svc['service_name']); ?>
            </h5>
            <div style="margin-bottom: 10px;">
                <span class="badge" style="background: <?php echo $status_color; ?>; color: white;">
                    <?php echo $svc['status'] === 'expired' ? '⚠️ EXPIRED' : (ucfirst($svc['status']) . ($daysLeft >= 0 && $daysLeft < 7 ? ' ⏰' : '')); ?>
                </span>
                <small style="color: #999; display: block; margin-top: 5px;">
                    Price: <?php echo formatCurrency($svc['price']); ?>
                </small>
            </div>
            <div style="background: #f8f9ff; padding: 10px; border-radius: 6px; font-size: 0.9rem; margin-bottom: 10px;">
                <?php if ($svc['start_date']): ?>
                <div><strong>Start:</strong> <?php echo formatDate($svc['start_date']); ?></div>
                <?php endif; ?>
                <?php if ($svc['expiry_date']): ?>
                <div><strong>Expires:</strong> <?php echo formatDate($svc['expiry_date']); ?></div>
                <div style="color: <?php echo $status_color; ?>; font-weight: 600; margin-top: 5px;">
                    <?php echo $svc['status'] === 'expired' || $daysLeft < 0 ? '⚠ EXPIRED' : (round($daysLeft) . ' days left'); ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <div style="background: #f9f9f9; border-radius: 8px; padding: 20px; text-align: center; color: #999;">
        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block; opacity: 0.5;"></i>
        <p style="margin: 0;">No services assigned to this project yet</p>
        <a href="/EcomZone-CMS/admin/projects/services.php?id=<?php echo $project_id; ?>" class="btn btn-sm btn-primary" style="margin-top: 10px; background: #6418C3; border: none;">
            <i class="fas fa-plus"></i> Add Services
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- KANBAN BOARD -->
<h3 style="margin: 30px 0 20px 0; font-weight: 600;">Project Tasks</h3>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px;">
    <!-- TO DO COLUMN -->
    <div style="background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); min-height: 500px;">
        <h5 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #FF9B52; font-weight: 600;">
            <i class="fas fa-tasks"></i> To Do (<?php echo count($tasks_by_status['todo']); ?>)
        </h5>
        <div class="kanban-column" data-status="todo" style="min-height: 450px;">
            <?php foreach ($tasks_by_status['todo'] as $task): ?>
            <div class="kanban-card" data-task-id="<?php echo $task['id']; ?>" 
                 draggable="true" style="background: #fff; border: 1px solid #e0e0e0; padding: 12px; border-radius: 6px; margin-bottom: 10px; cursor: move; border-left: 4px solid #FF9B52; user-select: none; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_title']); ?></p>
                    <small style="display: inline-block; background: #f8f9ff; padding: 2px 6px; border-radius: 3px; color: #666; margin-bottom: 8px;">
                        <?php 
                        $priority_color = $task['priority'] === 'high' ? '#FF5E5E' : ($task['priority'] === 'medium' ? '#FF9B52' : '#2BC155');
                        echo '<span style="color: ' . $priority_color . ';">●</span> ' . ucfirst($task['priority'] ?? 'medium');
                        ?>
                    </small>
                    <?php if ($task['due_date']): ?>
                    <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #999;">
                        <i class="fas fa-calendar-alt"></i> <?php echo formatDate($task['due_date']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-close delete-task" data-task-id="<?php echo $task['id']; ?>" style="padding: 0; flex-shrink: 0;"></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- IN PROGRESS COLUMN -->
    <div style="background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); min-height: 500px;">
        <h5 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #1EAAE7; font-weight: 600;">
            <i class="fas fa-spinner"></i> In Progress (<?php echo count($tasks_by_status['in_progress']); ?>)
        </h5>
        <div class="kanban-column" data-status="in_progress" style="min-height: 450px;">
            <?php foreach ($tasks_by_status['in_progress'] as $task): ?>
            <div class="kanban-card" data-task-id="<?php echo $task['id']; ?>" 
                 draggable="true" style="background: #fff; border: 1px solid #e0e0e0; padding: 12px; border-radius: 6px; margin-bottom: 10px; cursor: move; border-left: 4px solid #1EAAE7; user-select: none; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_title']); ?></p>
                    <small style="display: inline-block; background: #f8f9ff; padding: 2px 6px; border-radius: 3px; color: #666; margin-bottom: 8px;">
                        <?php 
                        $priority_color = $task['priority'] === 'high' ? '#FF5E5E' : ($task['priority'] === 'medium' ? '#FF9B52' : '#2BC155');
                        echo '<span style="color: ' . $priority_color . ';">●</span> ' . ucfirst($task['priority'] ?? 'medium');
                        ?>
                    </small>
                    <?php if ($task['due_date']): ?>
                    <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #999;">
                        <i class="fas fa-calendar-alt"></i> <?php echo formatDate($task['due_date']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-close delete-task" data-task-id="<?php echo $task['id']; ?>" style="padding: 0; flex-shrink: 0;"></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- REVIEW COLUMN -->
    <div style="background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); min-height: 500px;">
        <h5 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #6418C3; font-weight: 600;">
            <i class="fas fa-eye"></i> Review (<?php echo count($tasks_by_status['review']); ?>)
        </h5>
        <div class="kanban-column" data-status="review" style="min-height: 450px;">
            <?php foreach ($tasks_by_status['review'] as $task): ?>
            <div class="kanban-card" data-task-id="<?php echo $task['id']; ?>" 
                 draggable="true" style="background: #fff; border: 1px solid #e0e0e0; padding: 12px; border-radius: 6px; margin-bottom: 10px; cursor: move; border-left: 4px solid #6418C3; user-select: none; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_title']); ?></p>
                    <small style="display: inline-block; background: #f8f9ff; padding: 2px 6px; border-radius: 3px; color: #666; margin-bottom: 8px;">
                        <?php 
                        $priority_color = $task['priority'] === 'high' ? '#FF5E5E' : ($task['priority'] === 'medium' ? '#FF9B52' : '#2BC155');
                        echo '<span style="color: ' . $priority_color . ';">●</span> ' . ucfirst($task['priority'] ?? 'medium');
                        ?>
                    </small>
                    <?php if ($task['due_date']): ?>
                    <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #999;">
                        <i class="fas fa-calendar-alt"></i> <?php echo formatDate($task['due_date']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-close delete-task" data-task-id="<?php echo $task['id']; ?>" style="padding: 0; flex-shrink: 0;"></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- DONE COLUMN -->
    <div style="background: white; border-radius: 12px; padding: 15px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); min-height: 500px;">
        <h5 style="margin: 0 0 15px 0; padding-bottom: 10px; border-bottom: 3px solid #2BC155; font-weight: 600;">
            <i class="fas fa-check-circle"></i> Done (<?php echo count($tasks_by_status['done']); ?>)
        </h5>
        <div class="kanban-column" data-status="done" style="min-height: 450px; opacity: 0.7;">
            <?php foreach ($tasks_by_status['done'] as $task): ?>
            <div class="kanban-card" data-task-id="<?php echo $task['id']; ?>" 
                 draggable="true" style="background: #fff; border: 1px solid #e0e0e0; padding: 12px; border-radius: 6px; margin-bottom: 10px; cursor: move; border-left: 4px solid #2BC155; text-decoration: line-through; user-select: none; display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_title']); ?></p>
                    <small style="display: inline-block; background: #f8f9ff; padding: 2px 6px; border-radius: 3px; color: #666; margin-bottom: 8px;">
                        <?php 
                        $priority_color = $task['priority'] === 'high' ? '#FF5E5E' : ($task['priority'] === 'medium' ? '#FF9B52' : '#2BC155');
                        echo '<span style="color: ' . $priority_color . ';">●</span> ' . ucfirst($task['priority'] ?? 'medium');
                        ?>
                    </small>
                    <?php if ($task['due_date']): ?>
                    <p style="margin: 8px 0 0 0; font-size: 0.85rem; color: #999;">
                        <i class="fas fa-calendar-alt"></i> <?php echo formatDate($task['due_date']); ?>
                    </p>
                    <?php endif; ?>
                </div>
                <button type="button" class="btn btn-close delete-task" data-task-id="<?php echo $task['id']; ?>" style="padding: 0; flex-shrink: 0;"></button>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- PROJECT INVOICES SECTION -->
<div style="margin-top: 40px; margin-bottom: 25px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h3 style="margin: 0; font-weight: 600;">Project Invoices</h3>
        <a href="/EcomZone-CMS/admin/invoices/add.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-file-invoice-dollar"></i> Create Invoice
        </a>
    </div>

    <?php
    // Get invoices for this project
    $stmt = $db->prepare("
        SELECT i.*, c.client_name 
        FROM invoices i
        LEFT JOIN clients c ON i.client_id = c.id
        WHERE i.project_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$project_id]);
    $project_invoices = $stmt->fetchAll();

    if (count($project_invoices) > 0):
        // Calculate totals
        $total_invoiced = 0;
        $total_paid = 0;
        $total_balance = 0;
        
        foreach ($project_invoices as $inv) {
            $total_invoiced += $inv['total'];
            $total_paid += $inv['paid_amount'];
            $total_balance += $inv['balance'];
        }
    ?>
    
    <!-- INVOICE SUMMARY CARDS -->
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 15px; margin-bottom: 25px;">
        <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size: 0.8rem; color: #999; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Total Invoiced</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #1EAAE7;"><?php echo formatCurrency($total_invoiced); ?></div>
        </div>
        <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size: 0.8rem; color: #999; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Total Paid</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: #2BC155;"><?php echo formatCurrency($total_paid); ?></div>
        </div>
        <div style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
            <div style="font-size: 0.8rem; color: #999; margin-bottom: 5px; text-transform: uppercase; font-weight: 600;">Remaining Balance</div>
            <div style="font-size: 1.8rem; font-weight: 700; color: <?php echo $total_balance > 0 ? '#FF9B52' : '#2BC155'; ?>">
                <?php echo formatCurrency($total_balance); ?>
            </div>
        </div>
    </div>

    <!-- INVOICES TABLE -->
    <div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8f9ff; border-bottom: 1px solid #e0e0e0;">
                <tr>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Invoice #</th>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Date</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;">Total</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;">Paid</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600;">Balance</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600;">Status</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600;">Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($project_invoices as $inv): 
                    $status_color = $inv['status'] === 'paid' ? '#2BC155' : ($inv['status'] === 'partial' ? '#FF9B52' : ($inv['status'] === 'draft' ? '#999' : '#FF5E5E'));
                ?>
                <tr style="border-bottom: 1px solid #e0e0e0;">
                    <td style="padding: 12px; font-weight: 600;">
                        <a href="/EcomZone-CMS/admin/invoices/view.php?id=<?php echo $inv['id']; ?>" style="color: #6418C3; text-decoration: none;">
                            <?php echo clean($inv['invoice_number']); ?>
                        </a>
                    </td>
                    <td style="padding: 12px;"><?php echo formatDate($inv['issue_date']); ?></td>
                    <td style="padding: 12px; text-align: right; font-weight: 600;"><?php echo formatCurrency($inv['total']); ?></td>
                    <td style="padding: 12px; text-align: right; color: #2BC155; font-weight: 600;"><?php echo formatCurrency($inv['paid_amount']); ?></td>
                    <td style="padding: 12px; text-align: right; color: <?php echo $inv['balance'] > 0 ? '#FF9B52' : '#2BC155'; ?>; font-weight: 600;">
                        <?php echo formatCurrency($inv['balance']); ?>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <span style="display: inline-block; background: <?php echo $status_color; ?>; color: white; padding: 4px 10px; border-radius: 4px; font-size: 0.8rem; font-weight: 600;">
                            <?php echo ucfirst($inv['status']); ?>
                        </span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <a href="/EcomZone-CMS/admin/invoices/view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary" style="background: #6418C3; border: none; text-decoration: none;">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="/EcomZone-CMS/admin/invoices/add.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-warning" style="background: #ffc107; border: none; color: white; text-decoration: none;">
                            <i class="fas fa-edit"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php else: ?>
    
    <div style="background: #f9f9f9; border-radius: 8px; padding: 40px; text-align: center; color: #999;">
        <i class="fas fa-file-invoice" style="font-size: 2.5rem; margin-bottom: 15px; display: block; opacity: 0.5;"></i>
        <p style="margin: 0 0 15px 0;">No invoices created for this project yet</p>
        <a href="/EcomZone-CMS/admin/invoices/add.php?project_id=<?php echo $project_id; ?>" class="btn btn-primary" style="background: #6418C3; border: none; text-decoration: none;">
            <i class="fas fa-file-invoice-dollar"></i> Create First Invoice
        </a>
    </div>

    <?php endif; ?>
</div>

<!-- ADD TASK MODAL -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background: #6418C3; color: white; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">Add New Task</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="/EcomZone-CMS/admin/projects/add-task.php" class="modal-body">
                <input type="hidden" name="project_id" value="<?php echo $project_id; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrf(); ?>">
                
                <div class="mb-3">
                    <label>Task Name *</label>
                    <input type="text" name="task_name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Priority</label>
                    <select name="priority" class="form-control">
                        <option value="low">Low</option>
                        <option value="medium" selected>Medium</option>
                        <option value="high">High</option>
                    </select>
                </div>

                <div class="mb-3">
                    <label>Due Date</label>
                    <input type="date" name="due_date" class="form-control">
                </div>

                <div class="mb-3">
                    <label>Description</label>
                    <textarea name="description" class="form-control" rows="3"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none; width: 100%;">
                    <i class="fas fa-plus"></i> Add Task
                </button>
            </form>
        </div>
    </div>
</div>

<script>
let draggedCard = null;
const projectId = <?php echo $project_id; ?>;

function initializeKanban() {
    //  ALL KANBAN CARDS
    const cards = document.querySelectorAll('.kanban-card');
    const columns = document.querySelectorAll('.kanban-column');

    // DRAG START - Card get selected
    cards.forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            draggedCard.style.opacity = '0.5';
            draggedCard.style.border = '2px dashed #6418C3';
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/html', this.innerHTML);
        });

        card.addEventListener('dragend', function(e) {
            draggedCard.style.opacity = '1';
            draggedCard.style.border = '1px solid #e0e0e0';
            draggedCard = null;
        });
    });

    // DRAG OVER - Columns highlight on hover
    columns.forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            if (draggedCard) {
                this.style.background = '#f0f7ff';
                this.style.borderRadius = '6px';
                this.style.border = '2px dashed #6418C3';
            }
        });

        column.addEventListener('dragleave', function(e) {
            if (e.target === this && !draggedCard) {
                this.style.background = '';
                this.style.border = '';
            }
        });

        // DROP - Move card to new column
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            this.style.background = '';
            this.style.border = '';

            if (draggedCard && draggedCard.parentElement !== this) {
                const taskId = parseInt(draggedCard.dataset.taskId);
                const newStatus = this.dataset.status;

                // Optimistic UI update
                const taskIdToMove = draggedCard.dataset.taskId;
                this.appendChild(draggedCard);

                // SEND AJAX REQUEST
                const formData = new FormData();
                formData.append('action', 'update_task_status');
                formData.append('task_id', taskId);
                formData.append('new_status', newStatus);

                fetch(window.location.href, {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (!data.success) {
                        alert('Error updating task status');
                        location.reload();
                    }
                })
                .catch(err => {
                    console.error('Update failed:', err);
                    alert('Failed to update task');
                    location.reload();
                });
            }
        });
    });

    // DELETE TASK BUTTONS
    document.querySelectorAll('.delete-task').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Delete this task permanently?')) {
                const taskId = parseInt(this.dataset.taskId);
                const cardElement = this.closest('.kanban-card');

                const formData = new FormData();
                formData.append('action', 'delete_task');
                formData.append('task_id', taskId);

                fetch(window.location.href, {
                    method: 'POST',
                    body: new URLSearchParams(formData)
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        cardElement.style.opacity = '0';
                        cardElement.style.transform = 'scale(0.8)';
                        cardElement.style.transition = 'all 0.3s ease';
                        setTimeout(() => cardElement.remove(), 300);
                    } else {
                        alert('Failed to delete task');
                    }
                })
                .catch(err => {
                    console.error('Delete failed:', err);
                    alert('Failed to delete task');
                });
            }
        });
    });
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', initializeKanban);

// Reinitialize after modal closes (when new task is added)
if (document.getElementById('addTaskModal')) {
    document.getElementById('addTaskModal').addEventListener('hidden.bs.modal', function() {
        setTimeout(() => {
            location.reload();
        }, 500);
    });
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
