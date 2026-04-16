<?php
/**
 * PROJECTS - MANAGE SERVICES
 * Assign, edit, and manage services for a project
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Manage Project Services';

global $db;

$project_id = $_GET['id'] ?? null;
if (!$project_id) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

// Get project
$stmt = $db->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->execute([$project_id]);
$project = $stmt->fetch();

if (!$project) {
    setFlash('danger', 'Project not found');
    redirect('admin/projects/index.php');
}

// Handle service assignment
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'assign') {
            try {
                $client_id = $project['client_id'];
                $service_id = sanitizeInt($_POST['service_id'] ?? 0);
                $start_date = $_POST['start_date'] ?? date('Y-m-d');
                $expiry_date = $_POST['expiry_date'] ?? null;
                $price = (float)($_POST['price'] ?? 0);
                
                if (!$service_id) {
                    setFlash('danger', 'Service is required');
                } else {
                    // Check if already assigned
                    $stmt = $db->prepare("
                        SELECT id FROM client_services 
                        WHERE client_id = ? AND service_id = ? 
                        AND status IN ('active', 'renewed')
                    ");
                    $stmt->execute([$client_id, $service_id]);
                    $exists = $stmt->fetch();
                    
                    if ($exists) {
                        setFlash('warning', 'This service is already assigned to this client');
                    } else {
                        $stmt = $db->prepare("
                            INSERT INTO client_services (client_id, service_id, start_date, expiry_date, price, status, created_by, created_at)
                            VALUES (?, ?, ?, ?, ?, 'active', ?, NOW())
                        ");
                        $stmt->execute([$client_id, $service_id, $start_date, $expiry_date, $price, currentUser()['id']]);
                        
                        logActivity('CREATE', 'client_services', $db->lastInsertId(), 
                            "Service assigned to client in project: " . $project['project_name']);
                        
                        setFlash('success', 'Service assigned successfully!');
                        header('Location: /EcomZone-CMS/admin/projects/services-manage.php?id=' . $project_id);
                        exit;
                    }
                }
            } catch (Exception $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        } elseif ($action === 'renew') {
            try {
                $service_id = sanitizeInt($_POST['service_id'] ?? 0);
                $new_expiry = $_POST['new_expiry'] ?? null;
                $renewal_price = (float)($_POST['renewal_price'] ?? 0);
                
                if (!$new_expiry) {
                    setFlash('danger', 'New expiry date is required');
                } else {
                    $stmt = $db->prepare("
                        UPDATE client_services 
                        SET expiry_date = ?, status = 'renewed', price = ?, alert_30_sent = 0, alert_15_sent = 0, alert_7_sent = 0, alert_1_sent = 0
                        WHERE client_id = ? AND service_id = ?
                    ");
                    $stmt->execute([$new_expiry, $renewal_price ?: null, $project['client_id'], $service_id]);
                    
                    logActivity('UPDATE', 'client_services', $project_id, 
                        "Service renewed: Project " . $project['project_name']);
                    
                    setFlash('success', 'Service renewed successfully!');
                    header('Location: /EcomZone-CMS/admin/projects/services-manage.php?id=' . $project_id);
                    exit;
                }
            } catch (Exception $e) {
                setFlash('danger', 'Error: ' . $e->getMessage());
            }
        }
    }
}

// Get assigned services
$stmt = $db->prepare("
    SELECT cs.*, s.service_name, s.category, s.renewal_period, s.price as service_price
    FROM client_services cs
    JOIN services s ON cs.service_id = s.id
    WHERE cs.client_id = ?
    ORDER BY cs.expiry_date ASC
");
$stmt->execute([$project['client_id']]);
$assigned_services = $stmt->fetchAll();

// Get available services
$stmt = $db->prepare("
    SELECT * FROM services WHERE status = 'active' ORDER BY service_name
");
$stmt->execute();
$all_services = $stmt->fetchAll();

// Get already assigned service IDs
$assigned_ids = array_column($assigned_services, 'service_id');

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 25px;">
    <a href="/EcomZone-CMS/admin/projects/view.php?id=<?php echo $project_id; ?>" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Project
    </a>
</div>

<div style="background: linear-gradient(135deg, #6418C3, #9B59B6); color: white; border-radius: 12px; padding: 30px; margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">
        <i class="fas fa-cogs"></i> Manage Services for <?php echo clean($project['project_name']); ?>
    </h1>
    <p style="margin: 10px 0 0 0; opacity: 0.9;">Assign, renew, and track services for this project</p>
</div>

<?php echo flashAlert(); ?>

<!-- ASSIGNED SERVICES -->
<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); margin-bottom: 25px;">
    <h3 style="margin: 0 0 20px 0; font-weight: 600; border-bottom: 2px solid #6418C3; padding-bottom: 15px;">
        <i class="fas fa-list"></i> Active Services (<?php echo count($assigned_services); ?>)
    </h3>
    
    <?php if (count($assigned_services) > 0): ?>
        <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(350px, 1fr)); gap: 20px;">
            <?php foreach ($assigned_services as $svc): 
                $daysLeft = (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60);
                $isExpiring = $daysLeft <= 30;
                $isExpired = $daysLeft < 0;
            ?>
            <div style="border: 2px solid <?php echo $isExpired ? '#FF5E5E' : ($isExpiring ? '#FF9B52' : '#2BC155'); ?>; border-radius: 8px; padding: 20px; position: relative;">
                <!-- Status Badge -->
                <div style="position: absolute; top: 10px; right: 10px;">
                    <?php if ($isExpired): ?>
                        <span class="badge bg-danger">EXPIRED</span>
                    <?php elseif ($isExpiring): ?>
                        <span class="badge bg-warning">EXPIRING SOON</span>
                    <?php elseif ($svc['status'] === 'renewed'): ?>
                        <span class="badge bg-info">RENEWED</span>
                    <?php else: ?>
                        <span class="badge bg-success">ACTIVE</span>
                    <?php endif; ?>
                </div>

                <h5 style="margin: 0 0 10px 0; font-weight: 600; color: #333;">
                    <?php echo clean($svc['service_name']); ?>
                </h5>
                
                <div style="margin-bottom: 15px;">
                    <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $svc['category'])); ?></span>
                    <small style="color: #999; display: block; margin-top: 5px;">
                        Period: <?php echo ucfirst($svc['renewal_period']); ?>
                    </small>
                </div>

                <div style="background: #f8f9ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-size: 0.9rem;">
                    <div>
                        <strong>Start:</strong> <?php echo formatDate($svc['start_date']); ?>
                    </div>
                    <div>
                        <strong>Expires:</strong> <span style="color: <?php echo $isExpired ? '#FF5E5E' : ($isExpiring ? '#FF9B52' : '#2BC155'); ?>; font-weight: 600;">
                            <?php echo formatDate($svc['expiry_date']); ?>
                        </span>
                    </div>
                    <div>
                        <strong>Days Left:</strong> <span style="color: <?php echo $daysLeft < 0 ? '#FF5E5E' : '#6418C3'; ?>; font-weight: 600;">
                            <?php echo $daysLeft < 0 ? 'EXPIRED' : (round($daysLeft) . ' days'); ?>
                        </span>
                    </div>
                </div>

                <div style="background: #f0f4ff; padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600; text-align: center; color: #6418C3;">
                    Price: <?php echo $svc['price'] ? formatCurrency($svc['price']) : formatCurrency($svc['service_price']); ?>
                </div>

                <!-- Action Buttons -->
                <div style="display: flex; gap: 8px;">
                    <?php if (!$isExpired || $daysLeft < 5): ?>
                    <button onclick="showRenewModal(<?php echo $svc['service_id']; ?>, '<?php echo clean($svc['service_name']); ?>')" 
                            class="btn btn-sm btn-primary" style="flex: 1;">
                        <i class="fas fa-sync-alt"></i> Renew
                    </button>
                    <?php endif; ?>
                    <button onclick="if(confirm('Delete this service assignment?')) window.location.href='/EcomZone-CMS/admin/projects/services-manage.php?id=<?php echo $project_id; ?>&delete=<?php echo $svc['service_id']; ?>'" 
                            class="btn btn-sm btn-danger" style="flex: 1;">
                        <i class="fas fa-trash"></i> Remove
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div style="text-align: center; padding: 40px; color: #999;">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 15px; opacity: 0.5; display: block;"></i>
            <p>No services assigned to this project yet</p>
        </div>
    <?php endif; ?>
</div>

<!-- ASSIGN NEW SERVICE -->
<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <h3 style="margin: 0 0 20px 0; font-weight: 600; border-bottom: 2px solid #6418C3; padding-bottom: 15px;">
        <i class="fas fa-plus-circle"></i> Assign New Service
    </h3>

    <form method="POST">
        <?php echo csrfField(); ?>
        <input type="hidden" name="action" value="assign">

        <!-- Get available services -->
        <?php
        // Remove already assigned from available list
        $available = array_filter($all_services, function($s) use ($assigned_ids) {
            return !in_array($s['id'], $assigned_ids);
        });
        ?>

        <?php if (count($available) > 0): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px;">
                <div class="form-group">
                    <label><strong>Select Service *</strong></label>
                    <select name="service_id" class="form-control" id="serviceSelect" required onchange="updateServiceInfo()">
                        <option value="">-- Choose Service --</option>
                        <?php foreach ($available as $svc): ?>
                        <option value="<?php echo $svc['id']; ?>" data-price="<?php echo $svc['price']; ?>" data-renewal="<?php echo $svc['renewal_period']; ?>">
                            <?php echo clean($svc['service_name']); ?> - <?php echo formatCurrency($svc['price']); ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label><strong>Start Date *</strong></label>
                    <input type="date" name="start_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label><strong>Expiry Date *</strong></label>
                    <input type="date" name="expiry_date" class="form-control" id="expiryDate" required>
                </div>

                <div class="form-group">
                    <label><strong>Price (Optional)</strong></label>
                    <input type="number" name="price" class="form-control" placeholder="Leave blank to use service price" step="0.01" min="0">
                </div>
            </div>

            <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
                <button type="submit" class="btn btn-success" style="background: #2BC155; border: none;">
                    <i class="fas fa-check"></i> Assign Service
                </button>
            </div>

        <?php else: ?>
            <div style="padding: 20px; background: #fff3cd; border-radius: 8px; color: #856404; text-align: center;">
                <i class="fas fa-info-circle"></i> All available services are already assigned to this client
            </div>
        <?php endif; ?>
    </form>
</div>

<!-- RENEW SERVICE MODAL -->
<div class="modal fade" id="renewModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background: #6418C3; color: white; border: none;">
                <h5 class="modal-title">Renew Service</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="renew">
                <input type="hidden" name="service_id" id="renewServiceId">
                
                <div class="modal-body">
                    <div class="form-group">
                        <label><strong>Service Name</strong></label>
                        <input type="text" class="form-control" id="renewServiceName" readonly>
                    </div>
                    <div class="form-group">
                        <label><strong>New Expiry Date *</strong></label>
                        <input type="date" name="new_expiry" class="form-control" required>
                    </div>
                    <div class="form-group">
                        <label><strong>Renewal Price (Optional)</strong></label>
                        <input type="number" name="renewal_price" class="form-control" placeholder="Leave blank for original price" step="0.01" min="0">
                    </div>
                </div>
                <div class="modal-footer" style="border-top: 1px solid #e0e0e0;">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
                        <i class="fas fa-sync-alt"></i> Renew Service
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function updateServiceInfo() {
    const select = document.getElementById('serviceSelect');
    const selected = select.options[select.selectedIndex];
    const renewal = selected.getAttribute('data-renewal') || 'yearly';
    
    // Calculate expiry date based on renewal period
    const today = new Date();
    let expiryDate = new Date(today);
    
    switch(renewal) {
        case 'monthly':
            expiryDate.setMonth(expiryDate.getMonth() + 1);
            break;
        case 'quarterly':
            expiryDate.setMonth(expiryDate.getMonth() + 3);
            break;
        case 'yearly':
            expiryDate.setFullYear(expiryDate.getFullYear() + 1);
            break;
        case 'half_yearly':
            expiryDate.setMonth(expiryDate.getMonth() + 6);
            break;
    }
    
    document.getElementById('expiryDate').value = expiryDate.toISOString().split('T')[0];
}

function showRenewModal(serviceId, serviceName) {
    document.getElementById('renewServiceId').value = serviceId;
    document.getElementById('renewServiceName').value = serviceName;
    new bootstrap.Modal(document.getElementById('renewModal')).show();
}

// Set initial expiry date on page load
window.addEventListener('DOMContentLoaded', updateServiceInfo);
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
