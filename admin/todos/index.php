<?php

/**
 * PRIVATE TODO LIST - Complete CRUD Application
 * Modern Task Management System
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$pageTitle = 'My Todos';
$userId = currentUser()['id'];
global $db;

// Create todos table if it doesn't exist
$db->exec("
    CREATE TABLE IF NOT EXISTS `todos` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NOT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT,
        `priority` ENUM('low', 'medium', 'high') DEFAULT 'medium',
        `status` ENUM('pending', 'in_progress', 'completed') DEFAULT 'pending',
        `due_date` DATE,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
    )
");

// Get all todos for current user
$stmt = $db->prepare("
    SELECT * FROM todos 
    WHERE user_id = ? 
    ORDER BY 
        CASE status WHEN 'completed' THEN 3 ELSE 0 END,
        CASE priority WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END,
        due_date ASC,
        created_at DESC
");
$stmt->execute([$userId]);
$allTodos = $stmt->fetchAll();

// Group by status
$byStatus = ['pending' => [], 'in_progress' => [], 'completed' => []];
foreach ($allTodos as $todo) {
    $byStatus[$todo['status']][] = $todo;
}

// Count by status
$stats = [
    'total' => count($allTodos),
    'pending' => count($byStatus['pending']),
    'in_progress' => count($byStatus['in_progress']),
    'completed' => count($byStatus['completed'])
];

include __DIR__ . '/../../includes/header.php';
?>

<style>
    :root {
        --primary: #6418C3;
        --secondary: #1EAAE7;
        --success: #2BC155;
        --warning: #FF9B52;
        --danger: #FF5E5E;
        --dark: #1D1D1D;
        --border: #EEEEEE;
        --body-bg: #F4F4F4;
    }

    .todo-main {
        /* margin-left: 270px; */
        padding: 30px;
        background: var(--body-bg);
        min-height: calc(100vh - 60px);
    }

    .todo-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
    }

    .todo-header h1 {
        margin: 0;
        font-size: 2rem;
        color: var(--dark);
        font-weight: 700;
    }

    .btn-add {
        background: var(--primary);
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-add:hover {
        background: #5310a3;
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(100, 24, 195, 0.3);
    }

    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }

    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        text-align: center;
        border-top: 4px solid;
    }

    .stat-card.pending {
        border-top-color: var(--warning);
    }

    .stat-card.in_progress {
        border-top-color: var(--secondary);
    }

    .stat-card.completed {
        border-top-color: var(--success);
    }

    .stat-card.total {
        border-top-color: var(--primary);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: var(--primary);
    }

    .stat-label {
        color: #666;
        margin-top: 8px;
        font-weight: 600;
    }

    .filter-bar {
        background: white;
        padding: 15px 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        gap: 15px;
        align-items: center;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .filter-bar input,
    .filter-bar select {
        padding: 8px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
    }

    .todos-container {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .todos-list {
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .todo-item {
        background: #f9f9f9;
        padding: 18px;
        border-radius: 10px;
        display: flex;
        gap: 15px;
        align-items: flex-start;
        border-left: 4px solid;
        transition: all 0.3s ease;
    }

    .todo-item:hover {
        background: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transform: translateX(4px);
    }

    .todo-item.completed {
        background: rgba(43, 193, 85, 0.05);
        opacity: 0.7;
    }

    .todo-item.completed .todo-title {
        text-decoration: line-through;
        color: #999;
    }

    .todo-item.priority-high {
        border-left-color: var(--danger);
    }

    .todo-item.priority-medium {
        border-left-color: var(--warning);
    }

    .todo-item.priority-low {
        border-left-color: var(--success);
    }

    .todo-checkbox {
        width: 20px;
        height: 20px;
        cursor: pointer;
        margin-top: 2px;
        accent-color: var(--primary);
    }

    .todo-content {
        flex: 1;
    }

    .todo-title {
        font-weight: 600;
        font-size: 1rem;
        color: var(--dark);
        margin: 0 0 6px 0;
    }

    .todo-desc {
        font-size: 0.9rem;
        color: #666;
        margin: 0 0 8px 0;
        line-height: 1.4;
    }

    .todo-meta {
        display: flex;
        gap: 12px;
        flex-wrap: wrap;
    }

    .todo-priority {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .todo-priority.high {
        background: rgba(255, 94, 94, 0.15);
        color: var(--danger);
    }

    .todo-priority.medium {
        background: rgba(255, 155, 82, 0.15);
        color: var(--warning);
    }

    .todo-priority.low {
        background: rgba(43, 193, 85, 0.15);
        color: var(--success);
    }

    .todo-due {
        font-size: 0.85rem;
        color: #666;
    }

    .todo-due.overdue {
        color: var(--danger);
        font-weight: 600;
    }

    .todo-status {
        font-size: 0.85rem;
        padding: 3px 8px;
        border-radius: 4px;
        font-weight: 600;
    }

    .todo-status.pending {
        background: rgba(255, 155, 82, 0.15);
        color: var(--warning);
    }

    .todo-status.in_progress {
        background: rgba(30, 170, 231, 0.15);
        color: var(--secondary);
    }

    .todo-status.completed {
        background: rgba(43, 193, 85, 0.15);
        color: var(--success);
    }

    .todo-actions {
        display: flex;
        gap: 8px;
    }

    .btn-action {
        background: none;
        border: none;
        cursor: pointer;
        padding: 6px 10px;
        border-radius: 4px;
        transition: all 0.3s ease;
        font-size: 0.9rem;
        font-weight: 600;
    }

    .btn-edit {
        color: var(--secondary);
        background: rgba(30, 170, 231, 0.1);
    }

    .btn-edit:hover {
        background: rgba(30, 170, 231, 0.2);
    }

    .btn-delete {
        color: var(--danger);
        background: rgba(255, 94, 94, 0.1);
    }

    .btn-delete:hover {
        background: rgba(255, 94, 94, 0.2);
    }

    .empty-state {
        text-align: center;
        padding: 60px 40px;
        color: #999;
    }

    .empty-state i {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Modal Styles */
    .modal-header {
        background: var(--primary);
        color: white;
        border: none;
    }

    .modal-title {
        font-weight: 700;
    }

    .btn-close {
        filter: brightness(0) invert(1);
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: var(--dark);
    }

    .form-control {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid var(--border);
        border-radius: 6px;
        font-size: 0.9rem;
        transition: all 0.3s ease;
    }

    .form-control:focus {
        outline: none;
        border-color: var(--primary);
        box-shadow: 0 0 0 3px rgba(100, 24, 195, 0.1);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    @media (max-width: 768px) {
        .todo-main {
            margin-left: 0;
            padding: 20px;
        }

        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }

        .todo-header {
            flex-direction: column;
            gap: 15px;
        }

        .filter-bar {
            flex-direction: column;
        }

        .form-row {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="todo-main">
    <!-- HEADER -->
    <div class="todo-header">
        <h1><i class="fas fa-tasks"></i> My Todos</h1>
        <button class="btn-add" onclick="openAddModal()">
            <i class="fas fa-plus"></i> Add Todo
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card pending">
            <div class="stat-number"><?php echo $stats['pending']; ?></div>
            <div class="stat-label">Pending</div>
        </div>
        <div class="stat-card in_progress">
            <div class="stat-number"><?php echo $stats['in_progress']; ?></div>
            <div class="stat-label">In Progress</div>
        </div>
        <div class="stat-card completed">
            <div class="stat-number"><?php echo $stats['completed']; ?></div>
            <div class="stat-label">Completed</div>
        </div>
        <div class="stat-card total">
            <div class="stat-number"><?php echo $stats['total']; ?></div>
            <div class="stat-label">Total Todos</div>
        </div>
    </div>

    <!-- FILTER BAR -->
    <div class="filter-bar">
        <input type="text" id="searchInput" placeholder="Search todos..." style="flex: 1;">
        <select id="filterStatus" onchange="filterTodos()">
            <option value="">All Status</option>
            <option value="pending">Pending</option>
            <option value="in_progress">In Progress</option>
            <option value="completed">Completed</option>
        </select>
        <select id="filterPriority" onchange="filterTodos()">
            <option value="">All Priority</option>
            <option value="low">Low</option>
            <option value="medium">Medium</option>
            <option value="high">High</option>
        </select>
    </div>

    <!-- TODOS LIST -->
    <div class="todos-container">
        <div class="todos-list" id="todosList">
            <?php if (empty($allTodos)): ?>
                <div class="empty-state">
                    <i class="fas fa-clipboard-list"></i>
                    <p style="font-size: 1.1rem;">No todos yet. Create one to get started!</p>
                </div>
            <?php else: ?>
                <?php foreach ($allTodos as $todo): ?>
                    <div class="todo-item <?php echo $todo['status']; ?> priority-<?php echo $todo['priority']; ?>" data-id="<?php echo $todo['id']; ?>" data-status="<?php echo $todo['status']; ?>" data-priority="<?php echo $todo['priority']; ?>">
                        <input type="checkbox" class="todo-checkbox" <?php echo $todo['status'] === 'completed' ? 'checked' : ''; ?> onchange="toggleTodo(<?php echo $todo['id']; ?>)">

                        <div class="todo-content">
                            <h4 class="todo-title"><?php echo clean($todo['title']); ?></h4>
                            <?php if ($todo['description']): ?>
                                <p class="todo-desc"><?php echo clean($todo['description']); ?></p>
                            <?php endif; ?>

                            <div class="todo-meta">
                                <span class="todo-priority <?php echo $todo['priority']; ?>">
                                    <?php echo ucfirst($todo['priority']); ?>
                                </span>
                                <span class="todo-status <?php echo $todo['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $todo['status'])); ?>
                                </span>
                                <?php if ($todo['due_date']):
                                    $dueDate = strtotime($todo['due_date']);
                                    $today = strtotime('today');
                                    $isOverdue = $dueDate < $today && $todo['status'] !== 'completed';
                                ?>
                                    <span class="todo-due <?php echo $isOverdue ? 'overdue' : ''; ?>">
                                        <?php echo $isOverdue ? '⚠️ Overdue: ' : '📅 ';
                                        echo formatDate($todo['due_date']); ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="todo-actions">
                            <button class="btn-action btn-edit" onclick="openEditModal(<?php echo $todo['id']; ?>, '<?php echo addslashes($todo['title']); ?>', '<?php echo addslashes($todo['description'] ?? ''); ?>', '<?php echo $todo['priority']; ?>', '<?php echo $todo['status']; ?>', '<?php echo $todo['due_date']; ?>')">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn-action btn-delete" onclick="deleteTodo(<?php echo $todo['id']; ?>)">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- ADD/EDIT MODAL -->
<div class="modal fade" id="todoModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Add New Todo</h5>
                <button type="button" class="btn-close" onclick="closeTodoModal()"></button>
            </div>
            <form id="todoForm">
                <div class="modal-body">
                    <input type="hidden" id="todoId" value="">

                    <div class="form-group">
                        <label>Title *</label>
                        <input type="text" id="todoTitle" class="form-control" placeholder="What do you need to do?" required>
                    </div>

                    <div class="form-group">
                        <label>Description</label>
                        <textarea id="todoDesc" class="form-control" rows="3" placeholder="Add more details..."></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>Priority</label>
                            <select id="todoPriority" class="form-control">
                                <option value="low">Low</option>
                                <option value="medium" selected>Medium</option>
                                <option value="high">High</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label>Status</label>
                            <select id="todoStatus" class="form-control">
                                <option value="pending">Pending</option>
                                <option value="in_progress">In Progress</option>
                                <option value="completed">Completed</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Due Date</label>
                        <input type="date" id="todoDueDate" class="form-control">
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeTodoModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Todo</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    const APP_URL = '<?php echo APP_URL; ?>';

    function openAddModal() {
        document.getElementById('modalTitle').textContent = 'Add New Todo';
        document.getElementById('todoForm').reset();
        document.getElementById('todoId').value = '';
        const modal = new bootstrap.Modal(document.getElementById('todoModal'));
        modal.show();
    }

    function openEditModal(id, title, desc, priority, status, dueDate) {
        document.getElementById('modalTitle').textContent = 'Edit Todo';
        document.getElementById('todoId').value = id;
        document.getElementById('todoTitle').value = title;
        document.getElementById('todoDesc').value = desc;
        document.getElementById('todoPriority').value = priority;
        document.getElementById('todoStatus').value = status;
        document.getElementById('todoDueDate').value = dueDate;
        const modal = new bootstrap.Modal(document.getElementById('todoModal'));
        modal.show();
    }

    function closeTodoModal() {
        bootstrap.Modal.getInstance(document.getElementById('todoModal')).hide();
    }

    document.getElementById('todoForm').addEventListener('submit', function(e) {
        e.preventDefault();
        const id = document.getElementById('todoId').value;
        const action = id ? 'edit' : 'add';
        const url = action === 'add' ? 'add.php' : 'edit.php';

        const formData = new FormData();
        if (id) formData.append('id', id);
        formData.append('title', document.getElementById('todoTitle').value);
        formData.append('description', document.getElementById('todoDesc').value);
        formData.append('priority', document.getElementById('todoPriority').value);
        formData.append('status', document.getElementById('todoStatus').value);
        formData.append('due_date', document.getElementById('todoDueDate').value);

        fetch(url, {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Operation failed'));
                }
            });
    });

    function toggleTodo(id) {
        fetch('toggle.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'id=' + id
        }).then(() => location.reload());
    }

    function deleteTodo(id) {
        if (confirm('Are you sure you want to delete this todo?')) {
            fetch('delete.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: 'id=' + id
            }).then(() => location.reload());
        }
    }

    function filterTodos() {
        const search = document.getElementById('searchInput').value.toLowerCase();
        const status = document.getElementById('filterStatus').value;
        const priority = document.getElementById('filterPriority').value;

        document.querySelectorAll('.todo-item').forEach(item => {
            const matchSearch = item.textContent.toLowerCase().includes(search);
            const matchStatus = !status || item.dataset.status === status;
            const matchPriority = !priority || item.dataset.priority === priority;
            item.style.display = (matchSearch && matchStatus && matchPriority) ? 'flex' : 'none';
        });
    }

    document.getElementById('searchInput').addEventListener('input', filterTodos);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>