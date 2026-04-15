<?php
/**
 * TODOS - KANBAN BOARD VIEW
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'My Tasks';

global $db;

// Get current user's tasks grouped by status
$stmt = $db->prepare("
    SELECT * FROM todos 
    WHERE assigned_to = ? 
    ORDER BY priority DESC, due_date ASC
");
$stmt->execute([currentUser()['id']]);
$allTodos = $stmt->fetchAll();

// Group by status
$byStatus = ['todo' => [], 'in_progress' => [], 'done' => []];
foreach ($allTodos as $todo) {
    $byStatus[$todo['status']][] = $todo;
}

// Handle POST for drag-drop status update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'update_status') {
        $todoId = (int)$_POST['todo_id'];
        $newStatus = $_POST['new_status'];
        
        $stmt = $db->prepare("UPDATE todos SET status = ? WHERE id = ?");
        $stmt->execute([$newStatus, $todoId]);
        logActivity('UPDATE', 'todos', $todoId, "Status changed to {$newStatus}");
        
        echo json_encode(['success' => true]);
        exit;
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .kanban-board {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .kanban-column {
        background: #f8f9ff;
        border-radius: 12px;
        padding: 15px;
        min-height: 500px;
    }

    .kanban-column-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
        padding-bottom: 12px;
        border-bottom: 2px solid #ddd;
    }

    .kanban-column-title {
        font-weight: 700;
        font-size: 1.1rem;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .kanban-column-title.todo { color: #FF9B52; }
    .kanban-column-title.in-progress { color: #1EAAE7; }
    .kanban-column-title.done { color: #2BC155; }

    .task-count {
        background: rgba(0,0,0,0.1);
        padding: 4px 10px;
        border-radius: 20px;
        font-weight: 600;
        font-size: 0.85rem;
    }

    .task-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .task-card {
        background: white;
        border-radius: 8px;
        padding: 12px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.08);
        border-left: 3px solid;
        cursor: move;
        transition: all 0.3s ease;
    }

    .task-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        transform: translateY(-2px);
    }

    .task-card.priority-high { border-left-color: #FF5E5E; }
    .task-card.priority-medium { border-left-color: #FF9B52; }
    .task-card.priority-low { border-left-color: #2BC155; }

    .task-title {
        font-weight: 600;
        margin-bottom: 8px;
        font-size: 0.95rem;
    }

    .task-meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-top: 8px;
        font-size: 0.8rem;
    }

    .task-priority {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.7rem;
    }

    .task-priority.high { background: rgba(255,94,94,0.15); color: #FF5E5E; }
    .task-priority.medium { background: rgba(255,155,82,0.15); color: #FF9B52; }
    .task-priority.low { background: rgba(43,193,85,0.15); color: #2BC155; }

    .task-due {
        color: #666;
    }

    .task-due.overdue {
        color: #FF5E5E;
        font-weight: 600;
    }

    .add-task-btn {
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
        width: 100%;
        padding: 12px;
        background: white;
        border: 2px dashed #ddd;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.3s ease;
        color: #666;
        font-weight: 600;
        margin-top: 10px;
    }

    .add-task-btn:hover {
        border-color: #6418C3;
        color: #6418C3;
        background: rgba(100,24,195,0.05);
    }

    .empty-state {
        text-align: center;
        padding: 40px 20px;
        color: #999;
    }

    .empty-state-icon {
        font-size: 2rem;
        margin-bottom: 10px;
    }

    .quick-stats {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-box {
        background: white;
        padding: 15px;
        border-radius: 12px;
        text-align: center;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #6418C3;
    }

    .stat-label {
        font-size: 0.85rem;
        color: #666;
        margin-top: 5px;
    }

    @media (max-width: 768px) {
        .kanban-board {
            grid-template-columns: 1fr;
        }

        .quick-stats {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">
        <i class="fas fa-tasks"></i> My Tasks
    </h1>
    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addTaskModal">
        <i class="fas fa-plus"></i> Add Task
    </button>
</div>

<?php echo flashAlert(); ?>

<!-- QUICK STATS -->
<div class="quick-stats">
    <div class="stat-box">
        <div class="stat-number"><?php echo count($byStatus['todo']); ?></div>
        <div class="stat-label">To Do</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo count($byStatus['in_progress']); ?></div>
        <div class="stat-label">In Progress</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo count($byStatus['done']); ?></div>
        <div class="stat-label">Done</div>
    </div>
    <div class="stat-box">
        <div class="stat-number"><?php echo count($allTodos); ?></div>
        <div class="stat-label">Total</div>
    </div>
</div>

<!-- KANBAN BOARD -->
<div class="kanban-board">
    <?php
    $columns = [
        'todo' => ['title' => 'To Do', 'icon' => 'circle', 'color' => '#FF9B52'],
        'in_progress' => ['title' => 'In Progress', 'icon' => 'spinner', 'color' => '#1EAAE7'],
        'done' => ['title' => 'Done', 'icon' => 'check-circle', 'color' => '#2BC155']
    ];

    foreach ($columns as $status => $col):
    ?>
    <div class="kanban-column">
        <div class="kanban-column-header">
            <h3 class="kanban-column-title <?php echo str_replace('_', '-', $status); ?>">
                <i class="fas fa-<?php echo $col['icon']; ?>"></i> <?php echo $col['title']; ?>
            </h3>
            <span class="task-count"><?php echo count($byStatus[$status]); ?></span>
        </div>

        <div class="task-list" data-status="<?php echo $status; ?>">
            <?php if (empty($byStatus[$status])): ?>
            <div class="empty-state">
                <div class="empty-state-icon"><i class="fas fa-inbox"></i></div>
                <p>No tasks yet</p>
            </div>
            <?php else: ?>
                <?php foreach ($byStatus[$status] as $task): ?>
                <div class="task-card priority-<?php echo $task['priority']; ?>" draggable="true" data-id="<?php echo $task['id']; ?>">
                    <div class="task-title"><?php echo clean($task['title']); ?></div>
                    <?php if ($task['description']): ?>
                    <p style="margin: 8px 0; font-size: 0.85rem; color: #666;">
                        <?php echo clean(substr($task['description'], 0, 60)) . (strlen($task['description']) > 60 ? '...' : ''); ?>
                    </p>
                    <?php endif; ?>
                    <div class="task-meta">
                        <span class="task-priority <?php echo $task['priority']; ?>">
                            <?php echo ucfirst($task['priority']); ?>
                        </span>
                        <?php 
                        $dueDate = strtotime($task['due_date']);
                        $today = strtotime('today');
                        $isOverdue = $dueDate < $today && $task['status'] !== 'done';
                        ?>
                        <span class="task-due <?php echo $isOverdue ? 'overdue' : ''; ?>">
                            <?php echo timeAgo($task['due_date']); ?>
                        </span>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- ADD TASK MODAL -->
<div class="modal fade" id="addTaskModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Task</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="add.php">
                <div class="modal-body">
                    <?php echo csrfField(); ?>
                    <div class="form-group">
                        <label>Task Title *</label>
                        <input type="text" name="title" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group col-6">
                            <label>Priority</label>
                            <select name="priority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>
                        <div class="form-group col-6">
                            <label>Due Date</label>
                            <input type="date" name="due_date" class="form-control">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    // Drag and drop
    let draggedElement = null;

    document.querySelectorAll('.task-card').forEach(card => {
        card.addEventListener('dragstart', function(e) {
            draggedElement = this;
            this.style.opacity = '0.5';
        });

        card.addEventListener('dragend', function(e) {
            this.style.opacity = '1';
        });
    });

    document.querySelectorAll('.task-list').forEach(list => {
        list.addEventListener('dragover', function(e) {
            e.preventDefault();
            this.style.background = 'rgba(100,24,195,0.1)';
        });

        list.addEventListener('dragleave', function(e) {
            this.style.background = '';
        });

        list.addEventListener('drop', function(e) {
            e.preventDefault();
            this.style.background = '';

            if (draggedElement) {
                const newStatus = this.dataset.status;
                const todoId = draggedElement.dataset.id;

                // Send update to server
                fetch('', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'action=update_status&todo_id=' + todoId + '&new_status=' + newStatus
                }).then(() => {
                    this.appendChild(draggedElement);
                });
            }
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
