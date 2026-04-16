<?php
/**
 * INVOICES - VIEW & PRINT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Invoice Details';

global $db;

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('danger', 'Invoice not found');
    redirect('admin/invoices/index.php');
}

$stmt = $db->prepare("SELECT i.*, c.client_name, c.email, c.primary_phone, c.company_name, c.address, c.city, c.country FROM invoices i
    LEFT JOIN clients c ON i.client_id = c.id
    WHERE i.id = ?");
$stmt->execute([$id]);
$invoice = $stmt->fetch();

if (!$invoice) {
    setFlash('danger', 'Invoice not found');
    redirect('admin/invoices/index.php');
}

$stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Handle mark as paid
if ($_POST['action'] ?? null === 'mark_paid') {
    try {
        $stmt = $db->prepare("UPDATE invoices SET status = 'paid', paid_amount = total, balance = 0 WHERE id = ?");
        $stmt->execute([$id]);
        logActivity('UPDATE', 'invoices', $id, "Invoice marked as paid");
        setFlash('success', 'Invoice marked as paid!');
        header('Location: /EcomZone-CMS/admin/invoices/view.php?id=' . $id);
        exit;
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    @media print {
        body {
            background: white;
            padding: 0;
            margin: 0;
        }
        .navbar, 
        .sidebar, 
        .no-print,
        .container-fluid > div:first-child,
        form {
            display: none !important;
        }
        .btn,
        button,
        a[class*="btn"] {
            display: none !important;
        }
        div[style*="margin-bottom: 20px; display: flex"] {
            display: none !important;
        }
    }
</style>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="/EcomZone-CMS/admin/invoices/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Invoices
    </a>
    <div>
        <a href="/EcomZone-CMS/admin/invoices/add.php?id=<?php echo $invoice['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button onclick="window.print()" class="btn btn-info">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- INVOICE DOCUMENT -->
<div style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto;">
    
    <!-- HEADER -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #6418C3;">
        <div>
            <h2 style="margin: 0; color: #6418C3; font-weight: 700;">CMS EcomZone</h2>
            <p style="margin: 5px 0; color: #666;">premium@cms-ecomzone.com | +92-300-1234567</p>
        </div>
        <div style="text-align: right;">
            <h3 style="margin: 0; font-size: 2rem; color: #6418C3;">INVOICE</h3>
            <p style="margin: 8px 0 0 0; color: #999;">
                <?php echo clean($invoice['invoice_number']); ?>
            </p>
        </div>
    </div>

    <!-- DATES & DETAILS -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">FROM</h5>
            <p style="margin: 0; font-weight: 600;">CMS EcomZone</p>
            <p style="margin: 5px 0; color: #666;">premium@cms-ecomzone.com</p>
        </div>
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">BILL TO</h5>
            <p style="margin: 0; font-weight: 600;"><?php echo clean($invoice['client_name']); ?></p>
            <p style="margin: 5px 0; color: #666;"><?php echo clean($invoice['company_name']); ?></p>
            <p style="margin: 5px 0; color: #666;"><?php echo clean($invoice['address']); ?></p>
            <?php if ($invoice['city']): ?><p style="margin: 5px 0; color: #666;"><?php echo clean($invoice['city']); ?>, <?php echo clean($invoice['country']); ?></p><?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 20px; margin-bottom: 30px; padding: 15px; background: #f8f9ff; border-radius: 6px;">
        <div>
            <small style="color: #999;">Invoice Date</small>
            <p style="margin: 5px 0; font-weight: 600;"><?php echo formatDate($invoice['issue_date']); ?></p>
        </div>
        <div>
            <small style="color: #999;">Due Date</small>
            <p style="margin: 5px 0; font-weight: 600;"><?php echo formatDate($invoice['due_date']); ?></p>
        </div>
        <div>
            <small style="color: #999;">Status</small>
            <p style="margin: 5px 0;">
                <?php 
                $color = $invoice['status'] === 'paid' ? '#2BC155' : ($invoice['status'] === 'pending' ? '#FF9B52' : '#1EAAE7');
                ?>
                <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                    <?php echo ucfirst($invoice['status']); ?>
                </span>
            </p>
        </div>
        <div>
            <small style="color: #999;">Amount Due</small>
            <p style="margin: 5px 0; font-weight: 600; color: #FF5E5E;"><?php echo formatCurrency($invoice['balance']); ?></p>
        </div>
    </div>

    <!-- ITEMS TABLE -->
    <table style="width: 100%; border-collapse: collapse; margin-bottom: 20px;">
        <thead>
            <tr style="background: #f8f9ff; border-top: 2px solid #6418C3; border-bottom: 2px solid #6418C3;">
                <th style="padding: 12px; text-align: left; font-weight: 600;">Description</th>
                <th style="padding: 12px; text-align: center; font-weight: 600; width: 80px;">Qty</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; width: 100px;">Unit Price</th>
                <th style="padding: 12px; text-align: right; font-weight: 600; width: 100px;">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $item): ?>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px;"><?php echo clean($item['description']); ?></td>
                <td style="padding: 12px; text-align: center;"><?php echo $item['quantity']; ?></td>
                <td style="padding: 12px; text-align: right;"><?php echo formatCurrency($item['unit_price']); ?></td>
                <td style="padding: 12px; text-align: right;"><?php echo formatCurrency($item['total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- SUMMARY & BALANCE -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px;">
        <!-- CALCULATION SUMMARY -->
        <div style="width: 100%;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($invoice['subtotal']); ?></span>
            </div>
            <?php if ($invoice['tax_percent'] > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Tax (<?php echo $invoice['tax_percent']; ?>%):</span>
                <span><?php echo formatCurrency($invoice['tax_amount']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($invoice['discount_percent'] > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Discount (<?php echo $invoice['discount_percent']; ?>%):</span>
                <span>-<?php echo formatCurrency($invoice['discount_amount']); ?></span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; font-size: 1.1rem; font-weight: 700; color: #6418C3; border-top: 2px solid #6418C3;">
                <span>Invoice Total:</span>
                <span><?php echo formatCurrency($invoice['total']); ?></span>
            </div>
        </div>

        <!-- PAYMENT SUMMARY -->
        <div style="background: #f8f9ff; padding: 15px; border-radius: 8px; border-left: 4px solid #1EAAE7;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span style="font-weight: 600;">Amount Paid:</span>
                <span style="color: #2BC155; font-weight: 600;"><?php echo formatCurrency($invoice['paid_amount']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span style="font-weight: 600;">Due Balance:</span>
                <span style="color: #FF5E5E; font-weight: 600; font-size: 1.1rem;"><?php echo formatCurrency($invoice['balance']); ?></span>
            </div>
            <div style="display: flex; justify-content: space-between; padding: 8px 0;">
                <span style="font-weight: 600;">Payment Status:</span>
                <span style="background: <?php echo ($invoice['balance'] <= 0) ? '#2BC155' : (($invoice['paid_amount'] > 0) ? '#FF9B52' : '#999'); ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                    <?php 
                    if ($invoice['balance'] <= 0) echo 'PAID';
                    elseif ($invoice['paid_amount'] > 0) echo 'PARTIAL';
                    else echo 'PENDING';
                    ?>
                </span>
            </div>
        </div>
    </div>

    <?php
    // Get project total if this invoice is part of a project
    if ($invoice['project_id']) {
        $stmt = $db->prepare("
            SELECT 
                SUM(i.total) as project_total,
                SUM(i.paid_amount) as project_paid,
                SUM(i.balance) as project_balance,
                COUNT(i.id) as invoice_count
            FROM invoices i
            WHERE i.project_id = ? AND i.status IN ('sent', 'partial', 'paid')
        ");
        $stmt->execute([$invoice['project_id']]);
        $projectStats = $stmt->fetch();
    ?>
    
    <!-- PROJECT COST BREAKDOWN -->
    <div style="padding: 20px; background: #f0f4ff; border-radius: 8px; border-left: 4px solid #6418C3; margin-bottom: 20px;">
        <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;"><i class="fas fa-project-diagram"></i> Project Cost Summary</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Total Project Value</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #6418C3;"><?php echo formatCurrency($projectStats['project_total'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Total Paid</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #2BC155;"><?php echo formatCurrency($projectStats['project_paid'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Remaining Balance</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #FF5E5E;"><?php echo formatCurrency($projectStats['project_balance'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Invoices</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #1EAAE7;"><?php echo $projectStats['invoice_count'] ?? 0; ?></div>
            </div>
        </div>
    </div>
    <?php } ?>

    <!-- ACTION BUTTONS (Not printed) -->
    <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;" class="no-print">
        <?php if ($invoice['status'] !== 'paid'): ?>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="mark_paid" class="btn btn-success" 
                    onclick="return confirm('Mark this invoice as paid?');">
                <i class="fas fa-check"></i> Mark as Paid
            </button>
        </form>
        <a href="/EcomZone-CMS/admin/payments/add.php?invoice_id=<?php echo $invoice['id']; ?>" class="btn btn-info">
            <i class="fas fa-money-bill"></i> Record Payment
        </a>
        <?php endif; ?>
        <a href="/EcomZone-CMS/admin/invoices/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
