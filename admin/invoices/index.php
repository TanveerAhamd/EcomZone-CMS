<?php
/**
 * INVOICES LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Invoices';

global $db;

// Summary stats
$stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices");
$stmt->execute();
$totalInvoices = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE status = 'paid'");
$stmt->execute();
$totalPaid = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(balance), 0) as balance FROM invoices WHERE status IN ('sent', 'partial', 'overdue')");
$stmt->execute();
$totalDue = $stmt->fetch()['balance'];

// Get invoices
$stmt = $db->prepare("
    SELECT i.*, c.client_name
    FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    ORDER BY i.created_at DESC
");
$stmt->execute();
$invoices = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card-mini {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    }

    .stat-card-mini h6 {
        font-size: 0.85rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin: 0 0 10px 0;
    }

    .stat-card-mini .number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #6418C3;
    }

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
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">Invoices</h1>
    <a href="<?php echo APP_URL; ?>/admin/invoices/add.php" class="btn-add">
        <i class="fas fa-plus"></i> New Invoice
    </a>
</div>

<!-- STATS -->
<div class="stat-row">
    <div class="stat-card-mini">
        <h6><i class="fas fa-file"></i> Total Invoices</h6>
        <div class="number"><?php echo $totalInvoices; ?></div>
    </div>
    <div class="stat-card-mini">
        <h6><i class="fas fa-check"></i> Total Paid</h6>
        <div class="number"><?php echo formatCurrency($totalPaid); ?></div>
    </div>
    <div class="stat-card-mini">
        <h6><i class="fas fa-exclamation"></i> Outstanding</h6>
        <div class="number" style="color: #FF9B52;"><?php echo formatCurrency($totalDue); ?></div>
    </div>
</div>

<?php echo flashAlert(); ?>

<!-- TABLE -->
<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Invoice #</th>
                <th>Client</th>
                <th>Date</th>
                <th>Due Date</th>
                <th>Total</th>
                <th>Paid</th>
                <th>Balance</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $invoice): ?>
            <tr>
                <td><strong><?php echo clean($invoice['invoice_number']); ?></strong></td>
                <td><?php echo clean($invoice['client_name'] ?? '-'); ?></td>
                <td><?php echo formatDate($invoice['issue_date']); ?></td>
                <td><?php echo formatDate($invoice['due_date']) ?: '-'; ?></td>
                <td><?php echo formatCurrency($invoice['total']); ?></td>
                <td><?php echo formatCurrency($invoice['paid_amount']); ?></td>
                <td><strong><?php echo formatCurrency($invoice['balance']); ?></strong></td>
                <td><?php echo statusBadge($invoice['status']); ?></td>
                <td>
                    <a href="<?php echo APP_URL; ?>/admin/invoices/view.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="<?php echo APP_URL; ?>/admin/invoices/add.php?id=<?php echo $invoice['id']; ?>" class="btn btn-sm btn-warning">
                        <i class="fas fa-edit"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
