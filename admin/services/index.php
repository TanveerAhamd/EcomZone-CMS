<?php
/**
 * SERVICES LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Services';

global $db;

$stmt = $db->prepare("
    SELECT s.*, COUNT(cs.id) as client_count
    FROM services s
    LEFT JOIN client_services cs ON s.id = cs.service_id
    GROUP BY s.id
    ORDER BY s.service_name
");
$stmt->execute();
$services = $stmt->fetchAll();

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
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">Services</h1>
    <a href="<?php echo APP_URL; ?>/admin/services/add.php" class="btn-add"><i class="fas fa-plus"></i> New Service</a>
</div>

<?php echo flashAlert(); ?>

<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Service Name</th>
                <th>Category</th>
                <th>Price</th>
                <th>Renewal</th>
                <th>Clients Using</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($services as $service): ?>
            <tr>
                <td><strong><?php echo clean($service['service_name']); ?></strong></td>
                <td><span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $service['category'])); ?></span></td>
                <td><?php echo formatCurrency($service['price']); ?></td>
                <td><small><?php echo ucfirst($service['renewal_period']); ?></small></td>
                <td><?php echo $service['client_count']; ?></td>
                <td><?php echo statusBadge($service['status']); ?></td>
                <td>
                    <a href="<?php echo APP_URL; ?>/admin/services/add.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-warning"><i class="fas fa-edit"></i> Edit</a>
                    <a href="<?php echo APP_URL; ?>/admin/services/delete.php?id=<?php echo $service['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Delete this service?');"><i class="fas fa-trash"></i> Delete</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
