<?php
/**
 * USERS MANAGEMENT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'Users';

global $db;

$stmt = $db->prepare("SELECT * FROM users ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
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
    }
</style>

<div class="page-header">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">Users</h1>
    <button class="btn-add" data-bs-toggle="modal" data-bs-target="#addUserModal">
        <i class="fas fa-user-plus"></i> Add User
    </button>
</div>

<?php echo flashAlert(); ?>

<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Avatar</th>
                <th>Name</th>
                <th>Email</th>
                <th>Phone</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($users as $user): ?>
            <tr>
                <td>
                    <div style="width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, #6418C3, #9B59B6); display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem;">
                        <?php echo getInitials($user['name']); ?>
                    </div>
                </td>
                <td><strong><?php echo clean($user['name']); ?></strong></td>
                <td><?php echo clean($user['email']); ?></td>
                <td><?php echo clean($user['phone'] ?? '-'); ?></td>
                <td><span class="badge bg-info"><?php echo ucfirst($user['role']); ?></span></td>
                <td><?php echo statusBadge($user['status']); ?></td>
                <td><?php echo $user['last_login'] ? formatDateTime($user['last_login']) : 'Never'; ?></td>
                <td>
                    <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                    <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
