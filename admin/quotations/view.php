<?php
/**
 * QUOTATIONS - VIEW PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Quotation Details';

global $db;

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('danger', 'Quotation not found');
    redirect('admin/quotations/index.php');
}

$stmt = $db->prepare("SELECT q.*, c.client_name, c.email, c.phone, c.company_name, c.address, c.city, c.country FROM quotations q
    LEFT JOIN clients c ON q.client_id = c.id
    WHERE q.id = ?");
$stmt->execute([$id]);
$quotation = $stmt->fetch();

if (!$quotation) {
    setFlash('danger', 'Quotation not found');
    redirect('admin/quotations/index.php');
}

$stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();

// Handle convert to invoice
if ($_POST['action'] ?? null === 'convert') {
    try {
        $invoice_number = generateCode('INV', 'invoices', 'invoice_number');
        
        $stmt = $db->prepare("
            INSERT INTO invoices (client_id, invoice_number, issue_date, due_date, status, subtotal, tax_percentage, discount, tax, total, created_at)
            VALUES (?, ?, NOW(), DATE_ADD(NOW(), INTERVAL 30 DAY), 'pending', ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $quotation['client_id'], $invoice_number,
            $quotation['subtotal'], $quotation['tax_percentage'], $quotation['discount'], $quotation['tax'], $quotation['total']
        ]);
        
        $invoice_id = $db->lastInsertId();
        
        // Copy items
        foreach ($items as $item) {
            $stmt = $db->prepare("
                INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, line_total)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$invoice_id, $item['description'], $item['quantity'], $item['unit_price'], $item['line_total']]);
        }
        
        // Update quotation status
        $stmt = $db->prepare("UPDATE quotations SET status = 'accepted' WHERE id = ?");
        $stmt->execute([$id]);
        
        logActivity('CREATE', 'invoices', $invoice_id, "Invoice created from quotation #" . $quotation['quotation_number']);
        setFlash('success', 'Quotation converted to invoice successfully!');
        redirect('admin/invoices/index.php');
    } catch (Exception $e) {
        setFlash('danger', 'Error: ' . $e->getMessage());
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 20px; display: flex; justify-content: space-between; align-items: center;">
    <a href="/EcomZone-CMS/admin/quotations/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Quotations
    </a>
    <div>
        <a href="/EcomZone-CMS/admin/quotations/add.php?id=<?php echo $quotation['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit
        </a>
        <button onclick="window.print()" class="btn btn-info">
            <i class="fas fa-print"></i> Print
        </button>
    </div>
</div>

<!-- QUOTATION DOCUMENT -->
<div style="background: white; border-radius: 12px; padding: 40px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); max-width: 900px; margin: 0 auto;">
    
    <!-- HEADER -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 40px; margin-bottom: 40px; padding-bottom: 20px; border-bottom: 2px solid #6418C3;">
        <div>
            <h2 style="margin: 0; color: #6418C3; font-weight: 700;">CMS EcomZone</h2>
            <p style="margin: 5px 0; color: #666;">Professional Service Solutions</p>
        </div>
        <div style="text-align: right;">
            <h3 style="margin: 0; font-size: 2rem; color: #6418C3;">QUOTATION</h3>
            <p style="margin: 8px 0 0 0; color: #999;">
                <?php echo clean($quotation['quotation_number']); ?>
            </p>
        </div>
    </div>

    <!-- DATES & DETAILS -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-bottom: 30px;">
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600;color: #333;">FROM</h5>
            <p style="margin: 0; font-weight: 600;">CMS EcomZone</p>
            <p style="margin: 5px 0; color: #666;">premium@cms-ecomzone.com</p>
        </div>
        <div>
            <h5 style="margin: 0 0 15px 0; font-weight: 600; color: #333;">BILL TO</h5>
            <p style="margin: 0; font-weight: 600;"><?php echo clean($quotation['client_name']); ?></p>
            <p style="margin: 5px 0; color: #666;"><?php echo clean($quotation['company_name']); ?></p>
            <p style="margin: 5px 0; color: #666;"><?php echo clean($quotation['address']); ?></p>
            <?php if ($quotation['city']): ?><p style="margin: 5px 0; color: #666;"><?php echo clean($quotation['city']); ?>, <?php echo clean($quotation['country']); ?></p><?php endif; ?>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-bottom: 30px; padding: 15px; background: #f8f9ff; border-radius: 6px;">
        <div>
            <small style="color: #999;">Date</small>
            <p style="margin: 5px 0; font-weight: 600;"><?php echo formatDate($quotation['issue_date']); ?></p>
        </div>
        <div>
            <small style="color: #999;">Valid Until</small>
            <p style="margin: 5px 0; font-weight: 600;"><?php echo formatDate($quotation['valid_until']); ?></p>
        </div>
        <div>
            <small style="color: #999;">Status</small>
            <p style="margin: 5px 0;">
                <?php 
                $color = $quotation['status'] === 'accepted' ? '#2BC155' : ($quotation['status'] === 'rejected' ? '#FF5E5E' : '#FF9B52');
                ?>
                <span style="background: <?php echo $color; ?>; color: white; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem;">
                    <?php echo ucfirst($quotation['status']); ?>
                </span>
            </p>
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
                <td style="padding: 12px; text-align: right;"><?php echo formatCurrency($item['line_total']); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <!-- SUMMARY -->
    <div style="display: flex; justify-content: flex-end; margin-bottom: 20px;">
        <div style="width: 300px;">
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Subtotal:</span>
                <span><?php echo formatCurrency($quotation['subtotal']); ?></span>
            </div>
            <?php if ($quotation['tax_percentage'] > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Tax (<?php echo $quotation['tax_percentage']; ?>%):</span>
                <span><?php echo formatCurrency($quotation['tax']); ?></span>
            </div>
            <?php endif; ?>
            <?php if ($quotation['discount'] > 0): ?>
            <div style="display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px solid #e0e0e0;">
                <span>Discount:</span>
                <span>-<?php echo formatCurrency($quotation['discount']); ?></span>
            </div>
            <?php endif; ?>
            <div style="display: flex; justify-content: space-between; padding: 12px 0; font-size: 1.2rem; font-weight: 700; color: #6418C3; border-top: 2px solid #6418C3;">
                <span>Total:</span>
                <span><?php echo formatCurrency($quotation['total']); ?></span>
            </div>
        </div>
    </div>

    <!-- NOTES -->
    <?php if ($quotation['notes']): ?>
    <div style="padding: 15px; background: #f8f9ff; border-left: 4px solid #6418C3; border-radius: 4px; margin-bottom: 20px;">
        <p style="margin: 0; font-weight: 600; margin-bottom: 8px;">Notes:</p>
        <p style="margin: 0; color: #666; white-space: pre-wrap;"><?php echo clean($quotation['notes']); ?></p>
    </div>
    <?php endif; ?>

    <!-- ACTION BUTTONS (Not printed) -->
    <div style="display: flex; gap: 10px; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; print-hide: true;">
        <?php if ($quotation['status'] === 'sent' || $quotation['status'] === 'draft'): ?>
        <form method="POST" style="display: inline;">
            <button type="submit" name="action" value="convert" class="btn btn-success" 
                    onclick="return confirm('Convert this quotation to an invoice?');">
                <i class="fas fa-arrow-right"></i> Convert to Invoice
            </button>
        </form>
        <?php endif; ?>
        <a href="/EcomZone-CMS/admin/quotations/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<style media="print">
    .btn { display: none; }
    a { display: none; }
    form { display: none; }
    div[style*="display: flex; gap: 10px"] { display: none !important; }
</style>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
