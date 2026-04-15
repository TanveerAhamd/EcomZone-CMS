<?php
/**
 * PAYMENTS LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Payments';

global $db;

// Summary
$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE DATE(payment_date) = CURDATE()");
$stmt->execute();
$todayTotal = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(amount), 0) as total FROM payments WHERE MONTH(payment_date) = MONTH(CURDATE()) AND YEAR(payment_date) = YEAR(CURDATE())");
$stmt->execute();
$monthTotal = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(balance), 0) as total FROM invoices WHERE status IN ('sent', 'partial', 'overdue')");
$stmt->execute();
$outstanding = $stmt->fetch()['total'];

// Get payments
$stmt = $db->prepare("
    SELECT p.*, c.client_name, i.invoice_number
    FROM payments p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN invoices i ON p.invoice_id = i.id
    ORDER BY p.payment_date DESC
");
$stmt->execute();
$payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .stat-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-bottom: 25px;
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
    }

    .stat-label {
        font-size: 0.85rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 10px;
    }

    .stat-number {
        font-size: 1.8rem;
        font-weight: 700;
        color: #6418C3;
    }
</style>

<div class="page-header" style="display: flex; justify-content: space-between; margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">Payments</h1>
    <a href="<?php echo APP_URL; ?>/admin/payments/add.php" class="btn" style="background: #6418C3; color: white; padding: 12px 24px; border-radius: 8px; text-decoration: none;">
        <i class="fas fa-plus"></i> Record Payment
    </a>
</div>

<!-- STATS -->
<div class="stat-row">
    <div class="stat-card">
        <div class="stat-label"><i class="fas fa-calendar-today"></i> Today's Collections</div>
        <div class="stat-number"><?php echo formatCurrency($todayTotal); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fas fa-calendar-alt"></i> This Month</div>
        <div class="stat-number"><?php echo formatCurrency($monthTotal); ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-label"><i class="fas fa-exclamation-circle"></i> Outstanding</div>
        <div class="stat-number" style="color: #FF9B52;"><?php echo formatCurrency($outstanding); ?></div>
    </div>
</div>

<!-- TABLE -->
<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Payment #</th>
                <th>Client</th>
                <th>Invoice</th>
                <th>Date</th>
                <th>Amount</th>
                <th>Method</th>
                <th>Transaction ID</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $payment): ?>
            <tr>
                <td><strong><?php echo clean($payment['payment_number']); ?></strong></td>
                <td><?php echo clean($payment['client_name'] ?? '-'); ?></td>
                <td><?php echo clean($payment['invoice_number'] ?? '-'); ?></td>
                <td><?php echo formatDate($payment['payment_date']); ?></td>
                <td><?php echo formatCurrency($payment['amount']); ?></td>
                <td><small><?php echo ucfirst($payment['payment_method']); ?></small></td>
                <td><small><?php echo clean($payment['transaction_id'] ?? '-'); ?></small></td>
                <td>
                    <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
