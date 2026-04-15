<?php
/**
 * QUOTATIONS - ADD/EDIT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Create Quotation';

global $db;

$quotation = null;
$items = [];
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM quotations WHERE id = ?");
    $stmt->execute([$id]);
    $quotation = $stmt->fetch();
    
    if ($quotation) {
        $stmt = $db->prepare("SELECT * FROM quotation_items WHERE quotation_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
    }
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $client_id = $_POST['client_id'] ?? null;
            $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
            $valid_until = $_POST['valid_until'] ?? '';
            $tax_percentage = sanitizeInt($_POST['tax_percentage'] ?? 0);
            $discount = sanitizeInt($_POST['discount'] ?? 0);
            $notes = $_POST['notes'] ?? '';
            $quote_items = $_POST['items'] ?? [];
            
            if (!$client_id) {
                setFlash('danger', 'Client is required');
            } elseif (empty($quote_items)) {
                setFlash('danger', 'Add at least one item');
            } else {
                // Calculate totals
                $subtotal = 0;
                foreach ($quote_items as $item) {
                    if (isset($item['description']) && isset($item['quantity']) && isset($item['unit_price'])) {
                        $subtotal += (float)$item['quantity'] * (float)$item['unit_price'];
                    }
                }
                
                $tax = ($subtotal * $tax_percentage) / 100;
                $total = $subtotal + $tax - (float)$discount;
                
                if ($id) {
                    // Update existing
                    $stmt = $db->prepare("
                        UPDATE quotations SET 
                            client_id = ?, issue_date = ?, valid_until = ?, 
                            tax_percentage = ?, discount = ?, notes = ?,
                            subtotal = ?, tax = ?, total = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $client_id, $issue_date, $valid_until,
                        $tax_percentage, $discount, $notes, $subtotal, $tax, $total, $id
                    ]);
                    
                    // Delete old items
                    $stmt = $db->prepare("DELETE FROM quotation_items WHERE quotation_id = ?");
                    $stmt->execute([$id]);
                    
                    $quote_id = $id;
                } else {
                    // Create new
                    $quotation_number = generateCode('QUO', 'quotations', 'quotation_number');
                    
                    $stmt = $db->prepare("
                        INSERT INTO quotations 
                        (client_id, quotation_number, issue_date, valid_until, tax_percentage, discount, notes, subtotal, tax, total, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
                    ");
                    $stmt->execute([
                        $client_id, $quotation_number, $issue_date, $valid_until,
                        $tax_percentage, $discount, $notes, $subtotal, $tax, $total
                    ]);
                    
                    $quote_id = $db->lastInsertId();
                }
                
                // Insert items
                foreach ($quote_items as $item) {
                    if (isset($item['description']) && isset($item['quantity']) && isset($item['unit_price'])) {
                        $line_total = (float)$item['quantity'] * (float)$item['unit_price'];
                        
                        $stmt = $db->prepare("
                            INSERT INTO quotation_items (quotation_id, description, quantity, unit_price, line_total)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $quote_id, 
                            $item['description'], 
                            (float)$item['quantity'], 
                            (float)$item['unit_price'],
                            $line_total
                        ]);
                    }
                }
                
                if ($id) {
                    logActivity('UPDATE', 'quotations', $id, "Quotation updated");
                    setFlash('success', 'Quotation updated successfully!');
                } else {
                    logActivity('CREATE', 'quotations', $quote_id, "Quotation created");
                    setFlash('success', 'Quotation created successfully!');
                }
                redirect('admin/quotations/index.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $quotation ? 'Edit Quotation: ' . clean($quotation['quotation_number']) : 'Create New Quotation'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <?php echo csrfField(); ?>

    <!-- HEADER SECTION -->
    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
        <div class="form-group">
            <label>Client *</label>
            <select name="client_id" class="form-control" style="border-radius: 6px;" required>
                <option value="">-- Select Client --</option>
                <?php
                $stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name");
                $stmt->execute();
                $clients = $stmt->fetchAll();
                foreach ($clients as $c):
                ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($quotation['client_id'] ?? null) == $c['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($c['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">
            <div class="form-group">
                <label>Issue Date</label>
                <input type="date" name="issue_date" class="form-control" value="<?php echo $quotation['issue_date'] ?? date('Y-m-d'); ?>" style="border-radius: 6px;">
            </div>
            <div class="form-group">
                <label>Valid Until</label>
                <input type="date" name="valid_until" class="form-control" value="<?php echo $quotation['valid_until'] ?? ''; ?>" style="border-radius: 6px;">
            </div>
        </div>
    </div>

    <!-- LINE ITEMS SECTION -->
    <h4 style="margin-top: 25px; margin-bottom: 15px; font-weight: 600;">Line Items</h4>
    
    <div style="overflow-x: auto; margin-bottom: 20px;">
        <table style="width: 100%; border-collapse: collapse;">
            <thead style="background: #f8f9ff; border-top: 1px solid #e0e0e0; border-bottom: 1px solid #e0e0e0;">
                <tr>
                    <th style="padding: 12px; text-align: left; font-weight: 600;">Description</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; width: 100px;">Quantity</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; width: 120px;">Unit Price</th>
                    <th style="padding: 12px; text-align: right; font-weight: 600; width: 120px;">Total</th>
                    <th style="padding: 12px; text-align: center; font-weight: 600; width: 50px;"></th>
                </tr>
            </thead>
            <tbody id="itemsTable">
                <?php 
                if (!empty($items)):
                    foreach ($items as $idx => $item):
                ?>
                <tr class="item-row" style="border-bottom: 1px solid #e0e0e0;">
                    <td style="padding: 12px;">
                        <input type="text" name="items[<?php echo $idx; ?>][description]" 
                               value="<?php echo clean($item['description']); ?>" 
                               class="form-control" style="border-radius: 6px; border: 1px solid #ddd;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[<?php echo $idx; ?>][quantity]" 
                               value="<?php echo $item['quantity']; ?>" 
                               class="form-control qty-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: center;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[<?php echo $idx; ?>][unit_price]" 
                               value="<?php echo $item['unit_price']; ?>" step="0.01"
                               class="form-control price-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: right;">
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <span class="line-total"><?php echo formatCurrency($item['line_total']); ?></span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <button type="button" class="btn btn-sm btn-danger remove-row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php 
                    endforeach;
                else:
                ?>
                <tr class="item-row" style="border-bottom: 1px solid #e0e0e0;">
                    <td style="padding: 12px;">
                        <input type="text" name="items[0][description]" 
                               class="form-control" style="border-radius: 6px; border: 1px solid #ddd;" placeholder="Service/Product description">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[0][quantity]" value="1"
                               class="form-control qty-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: center;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[0][unit_price]" value="0" step="0.01"
                               class="form-control price-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: right;">
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <span class="line-total">₨0</span>
                    </td>
                    <td style="padding: 12px; text-align: center;">
                        <button type="button" class="btn btn-sm btn-danger remove-row">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <button type="button" id="addRowBtn" class="btn btn-sm btn-info" style="margin-bottom: 20px;">
        <i class="fas fa-plus"></i> Add Item
    </button>

    <!-- SUMMARY SECTION -->
    <div style="display: grid; grid-template-columns: 1fr 400px; gap: 20px; margin: 25px 0;">
        <div></div>
        
        <div style="background: #f8f9ff; padding: 20px; border-radius: 8px; border: 1px solid #e0e0e0;">
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px;">
                <span>Subtotal:</span>
                <span id="subtotal">₨0</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 80px; gap: 10px; margin-bottom: 12px;">
                <label>Tax (%)</label>
                <input type="number" name="tax_percentage" value="<?php echo $quotation['tax_percentage'] ?? 0; ?>" 
                       class="form-control" id="taxPercent" style="border-radius: 6px; text-align: right;">
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
                <span>Tax:</span>
                <span id="taxAmount">₨0</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 80px; gap: 10px; margin-bottom: 12px;">
                <label>Discount</label>
                <input type="number" name="discount" value="<?php echo $quotation['discount'] ?? 0; ?>" step="0.01"
                       class="form-control" id="discount" style="border-radius: 6px; text-align: right;">
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; padding-top: 12px; border-top: 2px solid #6418C3;">
                <span>Total:</span>
                <span id="total" style="color: #6418C3;">₨0</span>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="3" style="border-radius: 6px;"><?php echo clean($quotation['notes'] ?? ''); ?></textarea>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 25px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-save"></i> <?php echo $quotation ? 'Update Quotation' : 'Create Quotation'; ?>
        </button>
        <a href="/EcomZone-CMS/admin/quotations/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
const CURRENCY = '₨';
let itemCounter = <?php echo count($items) > 0 ? count($items) : 1; ?>;

function calculateTotals() {
    let subtotal = 0;
    
    document.querySelectorAll('.item-row').forEach(row => {
        const qty = parseFloat(row.querySelector('.qty-input').value) || 0;
        const price = parseFloat(row.querySelector('.price-input').value) || 0;
        const lineTotal = qty * price;
        row.querySelector('.line-total').textContent = CURRENCY + lineTotal.toFixed(2);
        subtotal += lineTotal;
    });
    
    const taxPercent = parseFloat(document.getElementById('taxPercent').value) || 0;
    const tax = (subtotal * taxPercent) / 100;
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const total = subtotal + tax - discount;
    
    document.getElementById('subtotal').textContent = CURRENCY + subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = CURRENCY + tax.toFixed(2);
    document.getElementById('total').textContent = CURRENCY + total.toFixed(2);
}

document.getElementById('addRowBtn').addEventListener('click', function(e) {
    e.preventDefault();
    const table = document.getElementById('itemsTable');
    const row = document.createElement('tr');
    row.className = 'item-row';
    row.style.borderBottom = '1px solid #e0e0e0';
    row.innerHTML = `
        <td style="padding: 12px;">
            <input type="text" name="items[${itemCounter}][description]" 
                   class="form-control" style="border-radius: 6px; border: 1px solid #ddd;" placeholder="Service/Product description">
        </td>
        <td style="padding: 12px;">
            <input type="number" name="items[${itemCounter}][quantity]" value="1"
                   class="form-control qty-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: center;">
        </td>
        <td style="padding: 12px;">
            <input type="number" name="items[${itemCounter}][unit_price]" value="0" step="0.01"
                   class="form-control price-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: right;">
        </td>
        <td style="padding: 12px; text-align: right;">
            <span class="line-total">₨0</span>
        </td>
        <td style="padding: 12px; text-align: center;">
            <button type="button" class="btn btn-sm btn-danger remove-row">
                <i class="fas fa-trash"></i>
            </button>
        </td>
    `;
    table.appendChild(row);
    attachRowEvents(row);
    itemCounter++;
});

function attachRowEvents(row) {
    row.querySelector('.remove-row').addEventListener('click', function(e) {
        e.preventDefault();
        row.remove();
        calculateTotals();
    });
    
    row.querySelector('.qty-input').addEventListener('input', calculateTotals);
    row.querySelector('.price-input').addEventListener('input', calculateTotals);
}

document.querySelectorAll('.item-row').forEach(row => attachRowEvents(row));
document.getElementById('taxPercent').addEventListener('input', calculateTotals);
document.getElementById('discount').addEventListener('input', calculateTotals);

calculateTotals();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
