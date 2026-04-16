<?php
/**
 * PAYMENTS - VIEW DETAILS PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Payment Details';

global $db;

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('danger', 'Payment not found');
    redirect('admin/payments/index.php');
}

$stmt = $db->prepare("
    SELECT p.*, c.client_name, c.email, c.primary_phone, i.invoice_number, i.total as invoice_total, i.balance as invoice_balance
    FROM payments p
    LEFT JOIN clients c ON p.client_id = c.id
    LEFT JOIN invoices i ON p.invoice_id = i.id
    WHERE p.id = ?
");
$stmt->execute([$id]);
$payment = $stmt->fetch();

if (!$payment) {
    setFlash('danger', 'Payment not found');
    redirect('admin/payments/index.php');
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="/EcomZone-CMS/admin/payments/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Payments
    </a>
    <div>
        <?php if ($payment['receipt_file']): ?>
        <a href="/EcomZone-CMS/admin/payments/download.php?file=<?php echo urlencode($payment['receipt_file']); ?>" class="btn btn-success">
            <i class="fas fa-download"></i> Download Receipt
        </a>
        <?php endif; ?>
        <button onclick="window.print()" class="btn btn-info">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- PAYMENT DETAILS -->
<div style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto;">
    
    <!-- HEADER -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #6418C3;">
        <div>
            <h2 style="margin: 0; color: #6418C3; font-weight: 700;">Payment Receipt</h2>
            <p style="margin: 5px 0; color: #666;">CMS EcomZone Payment System</p>
        </div>
        <div style="text-align: right;">
            <p style="margin: 0; font-size: 1.5rem; color: #6418C3; font-weight: 700;">
                <?php echo clean($payment['payment_number']); ?>
            </p>
            <p style="margin: 8px 0 0 0; color: #999;">
                <?php echo formatDate($payment['created_at']); ?>
            </p>
        </div>
    </div>

    <!-- PAYMENT INFO -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">PAYMENT DETAILS</h5>
            <table style="width: 100%; margin-bottom: 15px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666; width: 40%;">Payment #:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo clean($payment['payment_number']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Date:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo formatDate($payment['payment_date']); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Method:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo ucfirst(clean($payment['payment_method'])); ?></td>
                </tr>
                <?php if ($payment['transaction_id']): ?>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Transaction ID:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo clean($payment['transaction_id']); ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>

        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">CLIENT DETAILS</h5>
            <table style="width: 100%; margin-bottom: 15px;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666; width: 40%;">Name:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo clean($payment['client_name'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Email:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo clean($payment['email'] ?? '-'); ?></td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Phone:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo clean($payment['primary_phone'] ?? '-'); ?></td>
                </tr>
            </table>
        </div>
    </div>

    <!-- INVOICE & AMOUNT -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px; padding: 20px; background: #f8f9ff; border-radius: 8px; border-left: 4px solid #1EAAE7;">
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">INVOICE REFERENCE</h5>
            <table style="width: 100%;">
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666; width: 40%;">Invoice #:</td>
                    <td style="padding: 8px 0; color: #333;">
                        <a href="/EcomZone-CMS/admin/invoices/view.php?id=<?php echo $payment['invoice_id']; ?>" style="color: #6418C3; text-decoration: none;">
                            <?php echo clean($payment['invoice_number'] ?? '-'); ?>
                        </a>
                    </td>
                </tr>
                <tr>
                    <td style="padding: 8px 0; font-weight: 600; color: #666;">Invoice Total:</td>
                    <td style="padding: 8px 0; color: #333;"><?php echo formatCurrency($payment['invoice_total']); ?></td>
                </tr>
            </table>
        </div>

        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333; text-align: right;">PAYMENT AMOUNT</h5>
            <div style="padding: 20px; background: white; border-radius: 6px; text-align: right;">
                <div style="font-size: 1.2rem; color: #999; margin-bottom: 5px;">Amount Paid</div>
                <div style="font-size: 2.5rem; font-weight: 700; color: #2BC155;">
                    <?php echo formatCurrency($payment['amount']); ?>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Get project cost information if invoice has project_id
    $stmt = $db->prepare("SELECT project_id FROM invoices WHERE id = ? LIMIT 1");
    $stmt->execute([$payment['invoice_id']]);
    $invoiceProject = $stmt->fetch();
    
    if ($invoiceProject && $invoiceProject['project_id']) {
        $stmt = $db->prepare("
            SELECT 
                SUM(i.total) as project_total,
                SUM(i.paid_amount) as project_paid,
                SUM(i.balance) as project_balance,
                COUNT(i.id) as invoice_count
            FROM invoices i
            WHERE i.project_id = ? AND i.status IN ('sent', 'partial', 'paid')
        ");
        $stmt->execute([$invoiceProject['project_id']]);
        $projectStats = $stmt->fetch();
    ?>
    
    <!-- PROJECT COST SUMMARY -->
    <div style="padding: 20px; background: #f0f4ff; border-radius: 8px; border-left: 4px solid #6418C3; margin-bottom: 20px;">
        <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;"><i class="fas fa-project-diagram"></i> Associated Project Cost Summary</h5>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(150px, 1fr)); gap: 15px;">
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Total Project Value</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #6418C3;"><?php echo formatCurrency($projectStats['project_total'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Total Collected</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #2BC155;"><?php echo formatCurrency($projectStats['project_paid'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Project Balance</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #FF5E5E;"><?php echo formatCurrency($projectStats['project_balance'] ?? 0); ?></div>
            </div>
            <div style="background: white; padding: 12px; border-radius: 6px; text-align: center;">
                <small style="color: #999; display: block; margin-bottom: 5px;">Project Invoices</small>
                <div style="font-size: 1.3rem; font-weight: 700; color: #1EAAE7;"><?php echo $projectStats['invoice_count'] ?? 0; ?></div>
            </div>
        </div>
        <p style="margin: 15px 0 0 0; text-align: center; font-size: 0.9rem; color: #666;">
            <a href="/EcomZone-CMS/admin/projects/finances.php?id=<?php echo $invoiceProject['project_id']; ?>" style="color: #6418C3; text-decoration: none;">
                <i class="fas fa-chart-line"></i> View Full Project Finances
            </a>
        </p>
    </div>
    <?php } ?>

    <!-- NOTES -->
    <?php if ($payment['notes']): ?>
    <div style="padding: 15px; background: #f8f9ff; border-left: 4px solid #6418C3; border-radius: 4px; margin-bottom: 20px;">
        <p style="margin: 0; font-weight: 600; margin-bottom: 8px;">Notes:</p>
        <p style="margin: 0; color: #666; white-space: pre-wrap;"><?php echo clean($payment['notes']); ?></p>
    </div>
    <?php endif; ?>

    <!-- RECEIPT PREVIEW -->
    <?php if ($payment['receipt_file']): ?>
    <div style="padding: 20px; background: #f8f9ff; border-radius: 8px; margin-bottom: 20px;">
        <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">RECEIPT FILE</h5>
        <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #e0e0e0; text-align: center;">
            <p style="margin: 0 0 10px 0; color: #999; font-size: 0.9rem;">
                <i class="fas fa-file"></i> <?php echo clean($payment['receipt_file']); ?>
            </p>
            <a href="/EcomZone-CMS/admin/payments/download.php?file=<?php echo urlencode($payment['receipt_file']); ?>" class="btn btn-success" style="display: inline-block;">
                <i class="fas fa-download"></i> Download Receipt
            </a>
        </div>
    </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS (Not printed) -->
    <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
        <a href="/EcomZone-CMS/admin/payments/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back to List
        </a>
        <?php if ($payment['receipt_file']): ?>
        <a href="/EcomZone-CMS/admin/payments/download.php?file=<?php echo urlencode($payment['receipt_file']); ?>" class="btn btn-success">
            <i class="fas fa-download"></i> Download Receipt
        </a>
        <?php endif; ?>
    </div>
</div>

<style media="print">
    .btn { display: none !important; }
    a[class*="btn"] { display: none !important; }
    div[style*="display: flex; gap: 10px"] { display: none !important; }
    div[style*="margin-bottom: 20px; display: flex"] { display: none !important; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
