<?php
/**
 * PROJECTS LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Projects';

global $db;

$stmt = $db->prepare("
    SELECT p.*, c.client_name, u.name as assigned_user, s.service_name
    FROM projects p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN users u ON p.assigned_to = u.id
    LEFT JOIN services s ON p.service_id = s.id
    ORDER BY p.deadline ASC
");
$stmt->execute();
$projects = $stmt->fetchAll();

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

<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Code</th>
                <th>Project Name</th>
                <th>Client</th>
                <th>Service</th>
                <th>Deadline</th>
                <th>Progress</th>
                <th>Status</th>
                <th>Assigned</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($projects as $project): ?>
            <tr>
                <td>
                    <code style="background: #f0f0f0; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                        <?php echo clean($project['project_code']); ?>
                    </code>
                </td>
                <td><strong><?php echo clean($project['project_name']); ?></strong></td>
                <td><?php echo clean($project['client_name'] ?? '-'); ?></td>
                <td><?php echo clean($project['service_name'] ?? '-'); ?></td>
                <td><?php echo formatDate($project['deadline']) ?: '-'; ?></td>
                <td>
                    <div class="progress" style="height: 6px;"><div class="progress-bar" style="width: <?php echo $project['progress']; ?>%; background: #6418C3;"></div></div>
                    <small><?php echo $project['progress']; ?>%</small>
                </td>
                <td><?php echo statusBadge($project['status']); ?></td>
                <td><?php echo clean($project['assigned_user'] ?? '-'); ?></td>
                <td>
                    <a href="<?php echo APP_URL; ?>/admin/projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
