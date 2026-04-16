<?php
/**
 * INVOICES - ADD/EDIT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Create Invoice';

global $db;

// Add project_id column if it doesn't exist
try {
    $db->exec("ALTER TABLE invoices ADD COLUMN project_id INT(11) DEFAULT NULL");
} catch (Exception $e) {
    // Column might already exist
}

$invoice = null;
$items = [];
$id = $_GET['id'] ?? null;
$project_id = $_GET['project_id'] ?? null;
$project = null;

// If editing, get invoice details
if ($id) {
    $stmt = $db->prepare("SELECT * FROM invoices WHERE id = ?");
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
    $project_id = $invoice['project_id'] ?? $project_id;
    
    if ($invoice) {
        $stmt = $db->prepare("SELECT * FROM invoice_items WHERE invoice_id = ?");
        $stmt->execute([$id]);
        $items = $stmt->fetchAll();
    }
}

// Get project details if project_id provided
if ($project_id) {
    $stmt = $db->prepare("SELECT p.*, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id WHERE p.id = ?");
    $stmt->execute([$project_id]);
    $project = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $client_id = $_POST['client_id'] ?? null;
            $project_id_post = sanitizeInt($_POST['project_id'] ?? 0) ?: null;
            $issue_date = $_POST['issue_date'] ?? date('Y-m-d');
            $due_date = $_POST['due_date'] ?? '';
            $tax_percent = sanitizeInt($_POST['tax_percentage'] ?? 0);
            $discount_percent = sanitizeInt($_POST['discount'] ?? 0);
            $notes = $_POST['notes'] ?? '';
            $invoice_items = $_POST['items'] ?? [];
            
            if (!$client_id) {
                setFlash('danger', 'Client is required');
            } elseif (empty($invoice_items)) {
                setFlash('danger', 'Add at least one item');
            } else {
                // Calculate totals
                $subtotal = 0;
                foreach ($invoice_items as $item) {
                    if (isset($item['description']) && isset($item['quantity']) && isset($item['unit_price'])) {
                        $subtotal += (float)$item['quantity'] * (float)$item['unit_price'];
                    }
                }
                
                $tax_amount = ($subtotal * $tax_percent) / 100;
                $discount_amount = ($subtotal * $discount_percent) / 100;
                $total = $subtotal + $tax_amount - $discount_amount;
                $balance = $total;
                
                if ($id) {
                    // Update existing
                    $stmt = $db->prepare("
                        UPDATE invoices SET 
                            client_id = ?, project_id = ?, issue_date = ?, due_date = ?, 
                            tax_percent = ?, tax_amount = ?, discount_percent = ?, discount_amount = ?, notes = ?,
                            subtotal = ?, total = ?, balance = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([
                        $client_id, $project_id_post, $issue_date, $due_date,
                        $tax_percent, $tax_amount, $discount_percent, $discount_amount, $notes, $subtotal, $total, $balance, $id
                    ]);
                    
                    // Delete old items
                    $stmt = $db->prepare("DELETE FROM invoice_items WHERE invoice_id = ?");
                    $stmt->execute([$id]);
                    
                    $invoice_id = $id;
                } else {
                    // Create new
                    $invoice_number = generateCode('INV', 'invoices', 'invoice_number');
                    
                    $stmt = $db->prepare("
                        INSERT INTO invoices 
                        (client_id, project_id, invoice_number, issue_date, due_date, tax_percent, tax_amount, discount_percent, discount_amount, notes, subtotal, total, balance, status, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'draft', NOW())
                    ");
                    $stmt->execute([
                        $client_id, $project_id_post, $invoice_number, $issue_date, $due_date,
                        $tax_percent, $tax_amount, $discount_percent, $discount_amount, $notes, $subtotal, $total, $balance
                    ]);
                    
                    $invoice_id = $db->lastInsertId();
                }
                
                // Insert items
                foreach ($invoice_items as $item) {
                    if (isset($item['description']) && isset($item['quantity']) && isset($item['unit_price'])) {
                        $line_total = (float)$item['quantity'] * (float)$item['unit_price'];
                        
                        $stmt = $db->prepare("
                            INSERT INTO invoice_items (invoice_id, description, quantity, unit_price, total)
                            VALUES (?, ?, ?, ?, ?)
                        ");
                        $stmt->execute([
                            $invoice_id, 
                            $item['description'], 
                            (float)$item['quantity'], 
                            (float)$item['unit_price'],
                            $line_total
                        ]);
                    }
                }
                
                if ($id) {
                    logActivity('UPDATE', 'invoices', $id, "Invoice updated");
                    setFlash('success', 'Invoice updated successfully!');
                } else {
                    logActivity('CREATE', 'invoices', $invoice_id, "Invoice created");
                    setFlash('success', 'Invoice created successfully!');
                }
                
                // Redirect back to project if from project
                if ($project_id_post) {
                    redirect('admin/projects/view.php?id=' . $project_id_post);
                } else {
                    redirect('admin/invoices/index.php');
                }
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $invoice ? 'Edit Invoice: ' . clean($invoice['invoice_number']) : 'Create New Invoice'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <?php echo csrfField(); ?>

    <!-- HEADER SECTION -->
    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid #e0e0e0;">
        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Project (Optional)</label>
            <select name="project_id" class="form-control" id="projectSelect" style="border-radius: 6px;">
                <option value="">-- Select Project --</option>
                <?php
                $stmt = $db->prepare("SELECT p.id, p.project_name, p.project_code, c.client_name FROM projects p LEFT JOIN clients c ON p.client_id = c.id ORDER BY p.project_name");
                $stmt->execute();
                $projects = $stmt->fetchAll();
                foreach ($projects as $proj):
                ?>
                <option value="<?php echo $proj['id']; ?>" <?php echo ($project_id == $proj['id']) ? 'selected' : ''; ?>>
                    [<?php echo clean($proj['project_code']); ?>] <?php echo clean($proj['project_name']); ?> - <?php echo clean($proj['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
            <small style="color: #999; display: block; margin-top: 5px;">Select a project to auto-populate services</small>
        </div>

        <div class="form-group">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Client *</label>
            <select name="client_id" class="form-control" id="clientSelect" style="border-radius: 6px;" required>
                <option value="">-- Select Client --</option>
                <?php
                $stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name");
                $stmt->execute();
                $clients = $stmt->fetchAll();
                foreach ($clients as $c):
                ?>
                <option value="<?php echo $c['id']; ?>" <?php echo (($invoice['client_id'] ?? $project['client_id'] ?? null) == $c['id']) ? 'selected' : ''; ?>>
                    <?php echo clean($c['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div style="display: flex; gap: 10px; align-items: flex-end;">
            <div class="form-group" style="flex: 1;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Invoice Date</label>
                <input type="date" name="issue_date" class="form-control" value="<?php echo $invoice['issue_date'] ?? date('Y-m-d'); ?>" style="border-radius: 6px;">
            </div>
            <div class="form-group" style="flex: 1;">
                <label style="font-weight: 600; margin-bottom: 8px; display: block;">Due Date</label>
                <input type="date" name="due_date" class="form-control" value="<?php echo $invoice['due_date'] ?? ''; ?>" style="border-radius: 6px;">
            </div>
            <button type="button" id="loadServicesBtn" class="btn btn-info" style="padding: 10px 15px; border-radius: 6px; border: none; color: white; cursor: pointer; font-weight: 600;">
                <i class="fas fa-sync"></i> Load Services
            </button>
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
                        <span class="line-total"><?php echo formatCurrency($item['total']); ?></span>
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
                <input type="number" name="tax_percentage" value="<?php echo $invoice['tax_percent'] ?? 0; ?>" 
                       class="form-control" id="taxPercent" style="border-radius: 6px; text-align: right;">
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
                <span>Tax:</span>
                <span id="taxAmount">₨0</span>
            </div>

            <div style="display: grid; grid-template-columns: 1fr 80px; gap: 10px; margin-bottom: 12px;">
                <label>Discount (%)</label>
                <input type="number" name="discount" value="<?php echo $invoice['discount_percent'] ?? 0; ?>" step="0.01"
                       class="form-control" id="discount" style="border-radius: 6px; text-align: right;">
            </div>
            <div style="display: flex; justify-content: space-between; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 1px solid #e0e0e0;">
                <span>Discount Amount:</span>
                <span id="discountAmount">₨0</span>
            </div>

            <div style="display: flex; justify-content: space-between; font-size: 1.2rem; font-weight: 700; padding-top: 12px; border-top: 2px solid #6418C3;">
                <span>Total:</span>
                <span id="total" style="color: #6418C3;">₨0</span>
            </div>
        </div>
    </div>

    <div class="form-group">
        <label>Notes</label>
        <textarea name="notes" class="form-control" rows="3" style="border-radius: 6px;"><?php echo clean($invoice['notes'] ?? ''); ?></textarea>
    </div>

    <div style="display: flex; gap: 10px; margin-top: 25px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-save"></i> <?php echo $invoice ? 'Update Invoice' : 'Create Invoice'; ?>
        </button>
        <a href="<?php echo $project_id ? 'admin/projects/view.php?id=' . $project_id : 'admin/invoices/index.php'; ?>" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<script>
const CURRENCY = '₨';
let itemCounter = <?php echo count($items) > 0 ? count($items) : 1; ?>;

// Load services from selected project
document.getElementById('loadServicesBtn').addEventListener('click', async function(e) {
    e.preventDefault();
    const projectId = document.getElementById('projectSelect').value;
    
    if (!projectId) {
        alert('Please select a project first');
        return;
    }
    
    try {
        const response = await fetch('get_project_services.php?project_id=' + projectId);
        const data = await response.json();
        
        if (data.success) {
            // Clear existing items
            document.getElementById('itemsTable').innerHTML = '';
            itemCounter = 0;
            
            // Add services as items
            data.services.forEach(service => {
                const table = document.getElementById('itemsTable');
                const row = document.createElement('tr');
                row.className = 'item-row';
                row.style.borderBottom = '1px solid #e0e0e0';
                row.innerHTML = `
                    <td style="padding: 12px;">
                        <input type="text" name="items[${itemCounter}][description]" 
                               value="${escapeHtml(service.service_name)}"
                               class="form-control" style="border-radius: 6px; border: 1px solid #ddd;" placeholder="Service/Product description">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[${itemCounter}][quantity]" value="1"
                               class="form-control qty-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: center;">
                    </td>
                    <td style="padding: 12px;">
                        <input type="number" name="items[${itemCounter}][unit_price]" value="${service.price}" step="0.01"
                               class="form-control price-input" style="border-radius: 6px; border: 1px solid #ddd; text-align: right;">
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <span class="line-total">${CURRENCY}${(service.price).toFixed(2)}</span>
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
            
            calculateTotals();
        } else {
            alert('Error loading services: ' + (data.error || 'Unknown error'));
        }
    } catch (error) {
        console.error('Error:', error);
        alert('Failed to load services');
    }
});

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

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
    const discountPercent = parseFloat(document.getElementById('discount').value) || 0;
    const discount = (subtotal * discountPercent) / 100;
    const total = subtotal + tax - discount;
    
    document.getElementById('subtotal').textContent = CURRENCY + subtotal.toFixed(2);
    document.getElementById('taxAmount').textContent = CURRENCY + tax.toFixed(2);
    document.getElementById('discountAmount').textContent = CURRENCY + discount.toFixed(2);
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
