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

$stmt = $db->prepare("SELECT p.*, c.client_name, u.name as assigned_to_user FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
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
<div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px;">
        <div>
            <small style="color: #999; font-weight: 600;">Project Name</small>
            <p style="margin: 8px 0 0 0; font-size: 1.1rem; font-weight: 600;"><?php echo clean($project['project_name']); ?></p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600;">Client</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;"><?php echo clean($project['client_name']); ?></p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600;">Service</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;">
                <?php
                $stmt = $db->prepare("SELECT service_name FROM services WHERE id = ?");
                $stmt->execute([$project['service_id']]);
                $service = $stmt->fetch();
                echo clean($service['service_name'] ?? 'N/A');
                ?>
            </p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600;">Deadline</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;">
                <i class="fas fa-calendar"></i> <?php echo formatDate($project['deadline']); ?>
            </p>
        </div>
        <div>
            <small style="color: #999; font-weight: 600;">Status</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;">
                <span class="badge" style="background: <?php 
                $color = $project['status'] === 'completed' ? '#2BC155' : ($project['status'] === 'on_hold' ? '#FF9B52' : '#1EAAE7');
                echo $color;
                ?>; color: white; padding: 6px 10px;">
                    <?php echo ucfirst(str_replace('_', ' ', $project['status'])); ?>
                </span>
            </p>
        </div>
    </div>
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
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_name']); ?></p>
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
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_name']); ?></p>
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
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_name']); ?></p>
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
                    <p style="margin: 0 0 8px 0; font-weight: 600; font-size: 0.95rem;"><?php echo clean($task['task_name']); ?></p>
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

function initDragAndDrop() {
    // Drag start
    document.querySelectorAll('.kanban-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedCard = this;
            this.style.opacity = '0.5';
            this.style.transform = 'scale(0.95)';
            e.dataTransfer.effectAllowed = 'move';
        });
        
        card.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
            this.style.transform = 'scale(1)';
        });
    });

    // Drop zones
    document.querySelectorAll('.kanban-column').forEach(column => {
        column.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.style.background = '#f0f0f0';
            this.style.borderColor = '#6418C3';
        });
        
        column.addEventListener('dragleave', function(e) {
            if (e.target === this) {
                this.style.background = '#fafafa';
                this.style.borderColor = '#e0e0e0';
            }
        });
        
        column.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '#fafafa';
            this.style.borderColor = '#e0e0e0';
            
            if (draggedCard) {
                const taskId = draggedCard.dataset.taskId;
                const newStatus = this.dataset.status;
                
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=update_task_status&task_id=' + taskId + '&new_status=' + newStatus
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        this.appendChild(draggedCard);
                    }
                });
            }
        });
    });

    // Delete buttons
    document.querySelectorAll('.delete-task').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.stopPropagation();
            if (confirm('Delete this task?')) {
                const taskId = this.dataset.taskId;
                fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=delete_task&task_id=' + taskId
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    }
                });
            }
        });
    });
}

document.addEventListener('DOMContentLoaded', initDragAndDrop);

// Reinitialize after modal closes
document.getElementById('addTaskModal')?.addEventListener('hidden.bs.modal', function() {
    setTimeout(() => location.reload(), 300);
});
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
