<?php
/**
 * QUOTATIONS - LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Quotations';

global $db;

// Get all quotations with client info
$stmt = $db->prepare("
    SELECT q.*, c.client_name as client, COUNT(qi.id) as item_count
    FROM quotations q
    LEFT JOIN clients c ON q.client_id = c.id
    LEFT JOIN quotation_items qi ON q.id = qi.quotation_id
    GROUP BY q.id
    ORDER BY q.created_at DESC
");
$stmt->execute();
$quotations = $stmt->fetchAll();

// Get summary stats
$stmt = $db->prepare("SELECT COUNT(*) as total, SUM(total) as revenue FROM quotations WHERE status IN ('accepted', 'sent')");
$stmt->execute();
$stats = $stmt->fetch();

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fas fa-file-invoice"></i> Quotations
</h1>

<?php echo flashAlert(); ?>

<!-- STATS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <div style="color: #999; font-size: 0.9rem; margin-bottom: 8px;">Total Quotations</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: #6418C3;"><?php echo count($quotations); ?></div>
    </div>
    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
        <div style="color: #999; font-size: 0.9rem; margin-bottom: 8px;">Sent Value</div>
        <div style="font-size: 1.8rem; font-weight: 700; color: #1EAAE7;"><?php echo formatCurrency($stats['revenue'] ?? 0); ?></div>
    </div>
</div>

<!-- ADD BUTTON -->
<div style="margin-bottom: 20px;">
    <a href="/EcomZone-CMS/admin/quotations/add.php" class="btn btn-primary" style="background: #6418C3; border: none;">
        <i class="fas fa-plus"></i> New Quotation
    </a>
</div>

<!-- TABLE -->
<div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <table class="table table-hover">
        <thead style="background: #f8f9ff;">
            <tr>
                <th>Quote #</th>
                <th>Client</th>
                <th>Date</th>
                <th>Valid Until</th>
                <th>Amount</th>
                <th>Items</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($quotations as $quote): ?>
            <tr>
                <td><strong><?php echo clean($quote['quotation_number']); ?></strong></td>
                <td><?php echo clean($quote['client']); ?></td>
                <td><?php echo formatDate($quote['issue_date']); ?></td>
                <td><?php echo formatDate($quote['valid_until']); ?></td>
                <td><?php echo formatCurrency($quote['total']); ?></td>
                <td><span class="badge bg-info"><?php echo $quote['item_count']; ?></span></td>
                <td>
                    <?php 
                    $color = $quote['status'] === 'accepted' ? 'success' : ($quote['status'] === 'rejected' ? 'danger' : 'warning');
                    ?>
                    <span class="badge bg-<?php echo $color; ?>"><?php echo ucfirst($quote['status']); ?></span>
                </td>
                <td>
                    <a href="/EcomZone-CMS/admin/quotations/view.php?id=<?php echo $quote['id']; ?>" class="btn btn-sm btn-info" title="View">
                        <i class="fas fa-eye"></i>
                    </a>
                    <a href="/EcomZone-CMS/admin/quotations/add.php?id=<?php echo $quote['id']; ?>" class="btn btn-sm btn-warning" title="Edit">
                        <i class="fas fa-edit"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    // Initialize DataTables
    $(document).ready(function() {
        $('table').DataTable({
            pageLength: 25,
            dom: 'Bfrtip',
            buttons: ['csv', 'excel', 'pdf']
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
