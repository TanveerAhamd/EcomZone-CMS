<?php
/**
 * CLIENT PROFILE - SERVICES TAB WITH EXPIRY ALERTS
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

global $db;

$client_id = sanitizeInt($_GET['client_id'] ?? 0);

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'add_service') {
        if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
            echo json_encode(['success' => false, 'error' => 'CSRF failed']);
            exit;
        }
        
        try {
            $service_id = sanitizeInt($_POST['service_id'] ?? 0);
            $renewal_date = $_POST['renewal_date'] ?? date('Y-m-d', strtotime('+1 year'));
            $alert_days = sanitizeInt($_POST['alert_days'] ?? 30);
            
            $stmt = $db->prepare("
                INSERT INTO client_services (client_id, service_id, start_date, renewal_date, alert_days, status, created_at)
                VALUES (?, ?, NOW(), ?, ?, 'active', NOW())
            ");
            $stmt->execute([$client_id, $service_id, $renewal_date, $alert_days]);
            
            logActivity('CREATE', 'client_services', $db->lastInsertId(), "Service added to client");
            echo json_encode(['success' => true, 'message' => 'Service added']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'update_expiry_alert') {
        try {
            $cs_id = sanitizeInt($_POST['cs_id'] ?? 0);
            $renewal_date = $_POST['renewal_date'] ?? '';
            $alert_days = sanitizeInt($_POST['alert_days'] ?? 30);
            
            $stmt = $db->prepare("UPDATE client_services SET renewal_date = ?, alert_days = ? WHERE id = ?");
            $stmt->execute([$renewal_date, $alert_days, $cs_id]);
            
            logActivity('UPDATE', 'client_services', $cs_id, "Service renewal date updated");
            echo json_encode(['success' => true, 'message' => 'Updated successfully']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'remove_service') {
        try {
            $cs_id = sanitizeInt($_POST['cs_id'] ?? 0);
            $stmt = $db->prepare("DELETE FROM client_services WHERE id = ?");
            $stmt->execute([$cs_id]);
            
            logActivity('DELETE', 'client_services', $cs_id, "Service removed from client");
            echo json_encode(['success' => true, 'message' => 'Service removed']);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        exit;
    }
}

// Get client services
$stmt = $db->prepare("
    SELECT cs.*, s.service_name, s.price, s.renewal_period
    FROM client_services cs
    JOIN services s ON cs.service_id = s.id
    WHERE cs.client_id = ? AND cs.status = 'active'
    ORDER BY cs.renewal_date ASC
");
$stmt->execute([$client_id]);
$services = $stmt->fetchAll();

// Get all available services
$stmt = $db->prepare("SELECT id, service_name, price FROM services WHERE status = 'active' ORDER BY service_name");
$stmt->execute();
$available_services = $stmt->fetchAll();
?>

<style>
.service-card {
    background: white;
    border: 1px solid #e0e0e0;
    border-radius: 8px;
    padding: 15px;
    margin-bottom: 12px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    transition: all 0.2s;
}

.service-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.service-info {
    flex: 1;
}

.service-name {
    font-weight: 600;
    font-size: 1rem;
    margin: 0 0 5px 0;
}

.service-expiry {
    font-size: 0.85rem;
    margin: 5px 0 0 0;
}

.expiry-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 4px;
    font-size: 0.8rem;
    font-weight: 600;
}

.expiry-soon {
    background: #FFE5E5;
    color: #C33;
}

.expiry-urgent {
    background: #FF9999;
    color: white;
}

.expiry-ok {
    background: #E5F5E5;
    color: #2BC155;
}

.service-actions {
    display: flex;
    gap: 8px;
    flex-shrink: 0;
    margin-left: 10px;
}
</style>

<div style="padding: 20px; background: white; border-radius: 12px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <h5 style="margin: 0; font-weight: 600;">Active Services</h5>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal" style="background: #6418C3; border: none;">
            <i class="fas fa-plus"></i> Add Service
        </button>
    </div>

    <!-- SERVICES LIST -->
    <div id="servicesList">
        <?php if (empty($services)): ?>
        <div style="text-align: center; padding: 40px 20px; color: #999;">
            <i class="fas fa-box-open" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
            <p>No active services. Add one now!</p>
        </div>
        <?php else: ?>
            <?php foreach ($services as $svc): 
                $renewal = strtotime($svc['renewal_date']);
                $now = strtotime(date('Y-m-d'));
                $days_left = floor(($renewal - $now) / 86400);
                
                if ($days_left < 0) {
                    $status_class = 'expiry-urgent';
                    $status_text = 'EXPIRED ' . abs($days_left) . ' days ago';
                } elseif ($days_left <= $svc['alert_days']) {
                    $status_class = 'expiry-soon';
                    $status_text = 'EXPIRES in ' . $days_left . ' days';
                } else {
                    $status_class = 'expiry-ok';
                    $status_text = 'RENEWS in ' . $days_left . ' days';
                }
            ?>
            <div class="service-card">
                <div class="service-info">
                    <p class="service-name">
                        <?php echo clean($svc['service_name']); ?>
                        <span style="font-size: 0.8rem; color: #999; margin-left: 8px;"><?php echo formatCurrency($svc['price']); ?></span>
                    </p>
                    <p class="service-expiry">
                        <strong>Renewal:</strong> <?php echo formatDate($svc['renewal_date']); ?><br>
                        <span class="expiry-badge <?php echo $status_class; ?>"><?php echo $status_text; ?></span><br>
                        <small style="color: #666;">Alert: <?php echo $svc['alert_days']; ?> days before</small>
                    </p>
                </div>
                <div class="service-actions">
                    <button class="btn btn-sm btn-warning edit-service" data-cs-id="<?php echo $svc['id']; ?>" data-renewal="<?php echo $svc['renewal_date']; ?>" data-alert="<?php echo $svc['alert_days']; ?>">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-sm btn-danger remove-service" data-cs-id="<?php echo $svc['id']; ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- ADD SERVICE MODAL -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background: #6418C3; color: white; border: none; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">Add Service to Client</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label>Service *</label>
                    <select id="serviceSelect" class="form-control">
                        <option value="">-- Select Service --</option>
                        <?php foreach ($available_services as $svc): ?>
                        <option value="<?php echo $svc['id']; ?>"><?php echo clean($svc['service_name']); ?> - <?php echo formatCurrency($svc['price']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Renewal Date *</label>
                    <input type="date" id="renewalDate" class="form-control" value="<?php echo date('Y-m-d', strtotime('+1 year')); ?>">
                </div>
                <div class="mb-3">
                    <label>Alert Before (days)</label>
                    <input type="number" id="alertDays" class="form-control" value="30" min="1" max="365">
                </div>
                <button id="addServiceBtn" class="btn btn-primary" style="background: #6418C3; border: none; width: 100%;">
                    <i class="fas fa-check"></i> Add Service
                </button>
            </div>
        </div>
    </div>
</div>

<!-- EDIT ALERT MODAL -->
<div class="modal fade" id="editAlertModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background: #FF9B52; color: white; border: none; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">Update Expiry Alert</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editCsId">
                <div class="mb-3">
                    <label>Renewal Date</label>
                    <input type="date" id="editRenewalDate" class="form-control">
                </div>
                <div class="mb-3">
                    <label>Alert Before (days)</label>
                    <input type="number" id="editAlertDays" class="form-control" value="30" min="1">
                </div>
                <button id="updateAlertBtn" class="btn btn-warning" style="border: none; width: 100%; color: white;">
                    <i class="fas fa-save"></i> Update
                </button>
            </div>
        </div>
    </div>
</div>

<script>
const CSRF_TOKEN = '<?php echo generateCsrf(); ?>';
const CLIENT_ID = <?php echo $client_id; ?>;

// Add service
document.getElementById('addServiceBtn').addEventListener('click', function() {
    const serviceId = document.getElementById('serviceSelect').value;
    const renewalDate = document.getElementById('renewalDate').value;
    const alertDays = document.getElementById('alertDays').value;
    
    if (!serviceId || !renewalDate) {
        alert('Please fill all fields');
        return;
    }
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=add_service&csrf_token=' + CSRF_TOKEN + '&client_id=' + CLIENT_ID + 
              '&service_id=' + serviceId + '&renewal_date=' + renewalDate + '&alert_days=' + alertDays
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('addServiceModal')).hide();
            location.reload();
        } else {
            alert(data.error);
        }
    });
});

// Edit service alert
document.querySelectorAll('.edit-service').forEach(btn => {
    btn.addEventListener('click', function() {
        document.getElementById('editCsId').value = this.dataset.csId;
        document.getElementById('editRenewalDate').value = this.dataset.renewal;
        document.getElementById('editAlertDays').value = this.dataset.alert;
        new bootstrap.Modal(document.getElementById('editAlertModal')).show();
    });
});

document.getElementById('updateAlertBtn').addEventListener('click', function() {
    const csId = document.getElementById('editCsId').value;
    const renewalDate = document.getElementById('editRenewalDate').value;
    const alertDays = document.getElementById('editAlertDays').value;
    
    fetch(window.location.href, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'action=update_expiry_alert&cs_id=' + csId + '&renewal_date=' + renewalDate + '&alert_days=' + alertDays
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            bootstrap.Modal.getInstance(document.getElementById('editAlertModal')).hide();
            location.reload();
        } else {
            alert(data.error);
        }
    });
});

// Remove service
document.querySelectorAll('.remove-service').forEach(btn => {
    btn.addEventListener('click', function() {
        if (confirm('Remove this service?')) {
            fetch(window.location.href, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=remove_service&cs_id=' + this.dataset.csId
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                }
            });
        }
    });
});
</script>
