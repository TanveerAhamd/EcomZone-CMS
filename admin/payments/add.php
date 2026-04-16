<?php
/**
 * PAYMENTS - ADD/RECORD PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Record Payment';

global $db;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $invoice_id = sanitizeInt($_POST['invoice_id'] ?? 0);
            $amount = (float)($_POST['amount'] ?? 0);
            $payment_date = $_POST['payment_date'] ?? date('Y-m-d');
            $payment_method = $_POST['payment_method'] ?? 'cash';
            $transaction_id = $_POST['transaction_id'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $receipt_file = null;
            
            // Handle file upload
            if (isset($_FILES['receipt']) && $_FILES['receipt']['size'] > 0) {
                $allowed = ['pdf', 'jpg', 'jpeg', 'png'];
                $filename = $_FILES['receipt']['name'];
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                
                if (!in_array($ext, $allowed)) {
                    setFlash('danger', 'Invalid file type. Allowed: PDF, JPG, PNG');
                    goto form_end;
                }
                
                if ($_FILES['receipt']['size'] > 10 * 1024 * 1024) {
                    setFlash('danger', 'File too large (max 10MB)');
                    goto form_end;
                }
                
                $result = uploadFile($_FILES['receipt'], UPLOAD_RECEIPT_PATH);
                if ($result['success']) {
                    $receipt_file = $result['file_name'];
                } else {
                    setFlash('danger', 'Receipt upload failed: ' . $result['error']);
                    goto form_end;
                }
            }
            
            // Get invoice current data
            $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
            $stmt->execute([$invoice_id]);
            $invoice = $stmt->fetch();
            
            if (!$invoice) {
                setFlash('danger', 'Invoice not found');
                goto form_end;
            }
            
            // Calculate new balance
            $new_paid = $invoice['paid_amount'] + $amount;
            $new_balance = $invoice['total'] - $new_paid;
            
            // Determine status
            if ($new_balance <= 0) {
                $new_status = 'paid';
                $new_balance = 0;
            } elseif ($new_paid > 0) {
                $new_status = 'partial';
            } else {
                $new_status = 'pending';
            }
            
            // Generate unique payment number
            $payment_number = generateCode('PAY', 'payments', 'payment_number');
            
            // Insert payment record
            $stmt = $db->prepare("
                INSERT INTO payments (payment_number, invoice_id, client_id, amount, payment_date, payment_method, transaction_id, receipt_file, notes, created_by, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$payment_number, $invoice_id, $invoice['client_id'], $amount, $payment_date, $payment_method, $transaction_id, $receipt_file, $notes, currentUser()['id']]);
            
            // Update invoice
            $stmt = $db->prepare("
                UPDATE invoices SET paid_amount = ?, balance = ?, status = ? WHERE id = ?
            ");
            $stmt->execute([$new_paid, $new_balance, $new_status, $invoice_id]);
            
            logActivity('CREATE', 'payments', $db->lastInsertId(), "Payment recorded: " . formatCurrency($amount) . " for invoice #" . $invoice['invoice_number']);
            setFlash('success', 'Payment recorded successfully!');
            redirect('admin/invoices/view.php?id=' . $invoice_id);
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

form_end:

$invoice_id = $_GET['invoice_id'] ?? null;
$invoice = null;

if ($invoice_id) {
    $stmt = $db->prepare("SELECT i.*, c.client_name FROM invoices i LEFT JOIN clients c ON i.client_id = c.id WHERE i.id = ?");
    $stmt->execute([$invoice_id]);
    $invoice = $stmt->fetch();
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fas fa-money-bill-wave"></i> Record Payment
</h1>

<?php echo flashAlert(); ?>

<form method="POST" enctype="multipart/form-data" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <?php echo csrfField(); ?>

    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; padding: 20px; background: #f8f9ff; border-radius: 8px; border-left: 4px solid #1EAAE7;">
        <div class="form-group" style="margin: 0;">
            <label>Invoice *</label>
            <select name="invoice_id" class="form-control" style="border-radius: 6px;" required>
                <option value="">-- Select Invoice --</option>
                <?php
                $stmt = $db->prepare("
                    SELECT i.id, i.invoice_number, c.client_name, i.total, i.balance
                    FROM invoices i
                    LEFT JOIN clients c ON i.client_id = c.id
                    WHERE i.status != 'paid'
                    ORDER BY i.created_at DESC
                ");
                $stmt->execute();
                $invoices = $stmt->fetchAll();
                foreach ($invoices as $inv):
                ?>
                <option value="<?php echo $inv['id']; ?>" <?php echo $invoice_id == $inv['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($inv['invoice_number']); ?> - <?php echo clean($inv['client_name']); ?> (Balance: <?php echo formatCurrency($inv['balance']); ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <?php if ($invoice): ?>
        <div style="padding: 15px; background: white; border-radius: 6px; border: 1px solid #e0e0e0;">
            <small style="color: #999; font-weight: 600;">Invoice Details</small>
            <p style="margin: 8px 0 0 0; font-size: 0.95rem;">
                <strong><?php echo clean($invoice['invoice_number']); ?></strong><br>
                <?php echo clean($invoice['client_name']); ?><br>
                <span style="color: #FF5E5E;">Balance: <?php echo formatCurrency($invoice['balance']); ?></span>
            </p>
        </div>
        <?php endif; ?>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-group">
            <label>Payment Amount *</label>
            <input type="number" name="amount" class="form-control" step="0.01" min="0" value="<?php echo $invoice['balance'] ?? 0; ?>" required style="border-radius: 6px;">
        </div>

        <div class="form-group">
            <label>Payment Date *</label>
            <input type="date" name="payment_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required style="border-radius: 6px;">
        </div>

        <div class="form-group">
            <label>Payment Method</label>
            <select name="payment_method" class="form-control" style="border-radius: 6px;">
                <option value="cash">Cash</option>
                <option value="bank_transfer">Bank Transfer</option>
                <option value="cheque">Cheque</option>
                <option value="credit_card">Credit Card</option>
                <option value="online">Online Payment</option>
                <option value="other">Other</option>
            </select>
        </div>
    </div>

    <div class="form-group">
        <label>Transaction ID / Reference Number</label>
        <input type="text" name="transaction_id" class="form-control" placeholder="e.g., TXN123456, Cheque #500" style="border-radius: 6px;">
    </div>

    <div class="form-group">
        <label>Receipt File (PDF/JPG/PNG, Max 10MB)</label>
        <input type="file" name="receipt" class="form-control" accept=".pdf,.jpg,.jpeg,.png" style="border-radius: 6px;">
    </div>

    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="3" style="border-radius: 6px;" placeholder="Add any additional notes about this payment"></textarea>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 25px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-check"></i> Record Payment
        </button>
        <a href="/EcomZone-CMS/admin/invoices/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
