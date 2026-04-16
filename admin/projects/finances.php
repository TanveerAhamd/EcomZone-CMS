<?php
/**
 * PROJECTS - FINANCIAL OVERVIEW
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Project Finances';

global $db;

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

$stmt = $db->prepare("SELECT p.*, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

// Get all invoices for this project
$stmt = $db->prepare("
    SELECT i.id, i.invoice_number, i.issue_date, i.total, i.paid_amount, i.balance, i.status, i.quotation_id
    FROM invoices i
    WHERE i.project_id = ?
    ORDER BY i.created_at DESC
");
$stmt->execute([$id]);
$invoices = $stmt->fetchAll();

// Calculate project totals
$stmt = $db->prepare("
    SELECT 
        SUM(i.total) as project_total,
        SUM(i.paid_amount) as project_paid,
        SUM(i.balance) as project_balance,
        COUNT(i.id) as invoice_count
    FROM invoices i
    WHERE i.project_id = ?
");
$stmt->execute([$id]);
$projectStats = $stmt->fetch();

// Get all payments for this project
$stmt = $db->prepare("
    SELECT p.*, i.invoice_number
    FROM payments p
    LEFT JOIN invoices i ON p.invoice_id = i.id
    WHERE i.project_id = ?
    ORDER BY p.payment_date DESC
");
$stmt->execute([$id]);
$payments = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 25px;">
    <a href="/EcomZone-CMS/admin/projects/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Projects
    </a>
</div>

<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <h2 style="margin: 0 0 20px 0; font-weight: 700; color: #333;">
        <i class="fas fa-project-diagram"></i> <?php echo clean($project['project_name']); ?> - Financial Overview
    </h2>
    <p style="margin: 0; color: #666;">Client: <strong><?php echo clean($project['client_name']); ?></strong></p>
</div>

<!-- FINANCIAL SUMMARY -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 25px;">
    <div style="background: linear-gradient(135deg, #6418C3 0%, #8b5cf6 100%); border-radius: 12px; padding: 25px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 0.9rem; margin-bottom: 10px; opacity: 0.9;">Total Project Value</div>
        <div style="font-size: 2rem; font-weight: 700;"><?php echo formatCurrency($projectStats['project_total'] ?? 0); ?></div>
        <div style="font-size: 0.85rem; margin-top: 8px; opacity: 0.8;"><?php echo $projectStats['invoice_count'] ?? 0; ?> invoices</div>
    </div>

    <div style="background: linear-gradient(135deg, #2BC155 0%, #4ade80 100%); border-radius: 12px; padding: 25px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 0.9rem; margin-bottom: 10px; opacity: 0.9;">Amount Received</div>
        <div style="font-size: 2rem; font-weight: 700;"><?php echo formatCurrency($projectStats['project_paid'] ?? 0); ?></div>
        <div style="font-size: 0.85rem; margin-top: 8px; opacity: 0.8;"><?php 
            $percentage = ($projectStats['project_total'] > 0) ? round(($projectStats['project_paid'] / $projectStats['project_total']) * 100, 1) : 0;
            echo $percentage . '% received';
        ?></div>
    </div>

    <div style="background: linear-gradient(135deg, #FF5E5E 0%, #ff7f7f 100%); border-radius: 12px; padding: 25px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 0.9rem; margin-bottom: 10px; opacity: 0.9;">Remaining Balance</div>
        <div style="font-size: 2rem; font-weight: 700;"><?php echo formatCurrency($projectStats['project_balance'] ?? 0); ?></div>
        <div style="font-size: 0.85rem; margin-top: 8px; opacity: 0.8;"><?php 
            $remainingPercentage = ($projectStats['project_total'] > 0) ? round(($projectStats['project_balance'] / $projectStats['project_total']) * 100, 1) : 0;
            echo $remainingPercentage . '% pending';
        ?></div>
    </div>

    <div style="background: linear-gradient(135deg, #1EAAE7 0%, #38bdf8 100%); border-radius: 12px; padding: 25px; color: white; box-shadow: 0 4px 15px rgba(0,0,0,0.1);">
        <div style="font-size: 0.9rem; margin-bottom: 10px; opacity: 0.9;">Total Payments Recorded</div>
        <div style="font-size: 2rem; font-weight: 700;"><?php echo count($payments); ?></div>
        <div style="font-size: 0.85rem; margin-top: 8px; opacity: 0.8;">payment transactions</div>
    </div>
</div>

<!-- INVOICES TABLE -->
<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <h3 style="margin: 0 0 20px 0; font-weight: 600;">Project Invoices</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9ff; border-top: 2px solid #6418C3; border-bottom: 2px solid #6418C3;">
                <th style="padding: 12px; text-align: left;">Invoice #</th>
                <th style="padding: 12px; text-align: left;">Date</th>
                <th style="padding: 12px; text-align: right;">Total</th>
                <th style="padding: 12px; text-align: right;">Paid</th>
                <th style="padding: 12px; text-align: right;">Balance</th>
                <th style="padding: 12px; text-align: center;">Status</th>
                <th style="padding: 12px; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($invoices as $inv): ?>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px; font-weight: 600;"><?php echo clean($inv['invoice_number']); ?></td>
                <td style="padding: 12px;"><?php echo formatDate($inv['issue_date']); ?></td>
                <td style="padding: 12px; text-align: right;"><?php echo formatCurrency($inv['total']); ?></td>
                <td style="padding: 12px; text-align: right; color: #2BC155; font-weight: 600;"><?php echo formatCurrency($inv['paid_amount']); ?></td>
                <td style="padding: 12px; text-align: right; color: #FF5E5E; font-weight: 600;"><?php echo formatCurrency($inv['balance']); ?></td>
                <td style="padding: 12px; text-align: center;">
                    <?php 
                    $colors = [
                        'draft' => '#999',
                        'sent' => '#1EAAE7',
                        'partial' => '#FF9B52',
                        'paid' => '#2BC155',
                        'cancelled' => '#FF5E5E'
                    ];
                    $color = $colors[$inv['status']] ?? '#999';
                    ?>
                    <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                        <?php echo ucfirst($inv['status']); ?>
                    </span>
                </td>
                <td style="padding: 12px; text-align: center;">
                    <a href="/EcomZone-CMS/admin/invoices/view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i> View
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($invoices)): ?>
            <tr>
                <td colspan="7" style="padding: 20px; text-align: center; color: #999;">No invoices for this project</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- PAYMENTS TABLE -->
<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <h3 style="margin: 0 0 20px 0; font-weight: 600;">Payment Records</h3>
    <table style="width: 100%; border-collapse: collapse;">
        <thead>
            <tr style="background: #f8f9ff; border-top: 2px solid #6418C3; border-bottom: 2px solid #6418C3;">
                <th style="padding: 12px; text-align: left;">Payment #</th>
                <th style="padding: 12px; text-align: left;">Invoice</th>
                <th style="padding: 12px; text-align: left;">Date</th>
                <th style="padding: 12px; text-align: center;">Method</th>
                <th style="padding: 12px; text-align: right;">Amount</th>
                <th style="padding: 12px; text-align: center;">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payments as $pay): ?>
            <tr style="border-bottom: 1px solid #e0e0e0;">
                <td style="padding: 12px; font-weight: 600;"><?php echo clean($pay['payment_number']); ?></td>
                <td style="padding: 12px;"><?php echo clean($pay['invoice_number'] ?? '-'); ?></td>
                <td style="padding: 12px;"><?php echo formatDate($pay['payment_date']); ?></td>
                <td style="padding: 12px; text-align: center;"><small><?php echo ucfirst($pay['payment_method']); ?></small></td>
                <td style="padding: 12px; text-align: right; color: #2BC155; font-weight: 600;"><?php echo formatCurrency($pay['amount']); ?></td>
                <td style="padding: 12px; text-align: center;">
                    <a href="/EcomZone-CMS/admin/payments/view.php?id=<?php echo $pay['id']; ?>" class="btn btn-sm btn-primary">
                        <i class="fas fa-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($payments)): ?>
            <tr>
                <td colspan="6" style="padding: 20px; text-align: center; color: #999;">No payments recorded for this project</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
