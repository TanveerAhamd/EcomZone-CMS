<?php

/**
 * SERVICE DETAILS PAGE
 * Display full service information and allow status updates
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Service Details';

global $db;

$serviceId = $_GET['id'] ?? null;

if (!$serviceId) {
    setFlash('danger', 'Service not found');
    redirect('admin/alerts/index.php');
}

// Get service details
$stmt = $db->prepare("
    SELECT 
        ps.id,
        ps.service_name,
        ps.price,
        ps.start_date,
        ps.expiry_date,
        ps.status,
        ps.created_by,
        ps.created_at,
        ps.last_alert_sent,
        ps.alert_count,
        ps.renewed_at,
        ps.updated_at,
        p.id as project_id,
        p.project_name,
        p.description,
        p.client_id,
        c.client_name,
        c.email,
        c.primary_phone,
        c.secondary_phone
    FROM project_services ps
    LEFT JOIN projects p ON ps.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE ps.id = ?
");
$stmt->execute([$serviceId]);
$service = $stmt->fetch();

if (!$service) {
    setFlash('danger', 'Service not found');
    redirect('admin/alerts/index.php');
}

// Calculate days left
$today = date('Y-m-d');
$expiryDate = date('Y-m-d', strtotime($service['expiry_date']));
$daysLeft = (strtotime($expiryDate) - strtotime($today)) / (24 * 60 * 60);

// Handle status update via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');

    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        echo json_encode(['success' => false, 'message' => 'Security token expired']);
        exit;
    }

    $action = $_POST['action'] ?? '';

    if ($action === 'renew') {
        $newExpiryDate = $_POST['new_expiry_date'] ?? null;

        if (!$newExpiryDate) {
            echo json_encode(['success' => false, 'message' => 'Expiry date is required']);
            exit;
        }

        try {
            $stmt = $db->prepare("
                UPDATE project_services 
                SET expiry_date = ?, status = 'active', renewed_at = NOW(), updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$newExpiryDate, $serviceId]);

            // Update alert record
            $stmt = $db->prepare("
                UPDATE service_alerts 
                SET status = 'renewed', alert_visibility = 'hidden'
                WHERE service_id = ?
            ");
            $stmt->execute([$serviceId]);

            echo json_encode(['success' => true, 'message' => 'Service renewed successfully!']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    } elseif ($action === 'cancel') {
        try {
            $stmt = $db->prepare("
                UPDATE project_services 
                SET status = 'cancelled', updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$serviceId]);

            echo json_encode(['success' => true, 'message' => 'Service cancelled successfully']);
            exit;
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
            exit;
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .service-details-container {
        width: 100%;
        padding: 30px;
        background: #F4F4F4;
        min-height: calc(100vh - 60px);
    }

    .details-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 30px;
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .details-header h1 {
        margin: 0;
        font-size: 2rem;
        color: #1D1D1D;
        font-weight: 700;
    }

    .details-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
        margin-bottom: 20px;
    }

    .detail-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .detail-card h3 {
        margin: 0 0 15px 0;
        font-size: 1rem;
        color: #6418C3;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .detail-item {
        display: flex;
        justify-content: space-between;
        margin-bottom: 12px;
        padding-bottom: 12px;
        border-bottom: 1px solid #EEE;
    }

    .detail-item:last-child {
        margin-bottom: 0;
        padding-bottom: 0;
        border-bottom: none;
    }

    .detail-label {
        font-weight: 600;
        color: #666;
        min-width: 150px;
    }

    .detail-value {
        color: #1D1D1D;
        text-align: right;
        flex: 1;
    }

    .status-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.85rem;
        font-weight: 600;
    }

    .status-active {
        background: rgba(43, 193, 85, 0.2);
        color: #2BC155;
    }

    .status-expired {
        background: rgba(255, 94, 94, 0.2);
        color: #FF5E5E;
    }

    .status-cancelled {
        background: rgba(153, 153, 153, 0.2);
        color: #999;
    }

    .days-badge {
        display: inline-block;
        padding: 8px 16px;
        border-radius: 20px;
        font-size: 1rem;
        font-weight: 700;
    }

    .days-expired {
        background: rgba(255, 94, 94, 0.2);
        color: #FF5E5E;
    }

    .days-soon {
        background: rgba(255, 155, 82, 0.2);
        color: #FF9B52;
    }

    .days-warning {
        background: rgba(255, 193, 7, 0.2);
        color: #FFC107;
    }

    .days-normal {
        background: rgba(43, 193, 85, 0.2);
        color: #2BC155;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        margin-top: 20px;
        flex-wrap: wrap;
    }

    .btn-primary {
        background: #6418C3;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-primary:hover {
        background: #5310a3;
    }

    .btn-secondary {
        background: #ddd;
        color: #333;
        border: none;
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-secondary:hover {
        background: #ccc;
    }

    .btn-danger {
        background: #FF5E5E;
        color: white;
        border: none;
        padding: 12px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
    }

    .btn-danger:hover {
        background: #e74c3c;
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        color: #333;
    }

    .form-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 6px;
        font-size: 0.95rem;
    }

    .modal {
        display: none;
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.5);
        z-index: 1000;
        justify-content: center;
        align-items: center;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: white;
        padding: 30px;
        border-radius: 12px;
        max-width: 500px;
        width: 90%;
        max-height: 80vh;
        overflow-y: auto;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
    }

    .modal-header h2 {
        margin: 0;
    }

    .close-btn {
        background: none;
        border: none;
        font-size: 1.5rem;
        cursor: pointer;
        color: #666;
    }

    @media (max-width: 768px) {
        .service-details-container {
            padding: 15px;
        }

        .details-header {
            flex-direction: column;
            text-align: center;
        }

        .details-grid {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .btn-primary,
        .btn-secondary,
        .btn-danger {
            width: 100%;
        }
    }
</style>

<div class="service-details-container">
    <div class="details-header">
        <div>
            <h1><?php echo htmlspecialchars($service['service_name']); ?></h1>
            <p style="margin: 5px 0 0 0; color: #666;"><?php echo htmlspecialchars($service['project_name'] ?? 'N/A'); ?></p>
        </div>
        <a href="<?php echo APP_URL; ?>/admin/alerts/index.php" style="background: #ddd; color: #333; padding: 10px 20px; border-radius: 6px; text-decoration: none; font-weight: 600;">
            <i class="fas fa-arrow-left"></i> Back to Alerts
        </a>
    </div>

    <div class="details-grid">
        <!-- Service Information Card -->
        <div class="detail-card">
            <h3><i class="fas fa-info-circle"></i> Service Information</h3>

            <div class="detail-item">
                <span class="detail-label">Service Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($service['service_name']); ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Status</span>
                <span class="detail-value">
                    <span class="status-badge status-<?php echo $service['status']; ?>">
                        <?php echo ucfirst($service['status']); ?>
                    </span>
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Price</span>
                <span class="detail-value"><?php echo $service['price'] ? formatCurrency($service['price']) : 'N/A'; ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Start Date</span>
                <span class="detail-value"><?php echo formatDate($service['start_date']); ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Expiry Date</span>
                <span class="detail-value"><strong><?php echo formatDate($service['expiry_date']); ?></strong></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Days Left</span>
                <span class="detail-value">
                    <span class="days-badge <?php
                                            if ($daysLeft < 0) echo 'days-expired';
                                            elseif ($daysLeft <= 1) echo 'days-expired';
                                            elseif ($daysLeft <= 7) echo 'days-soon';
                                            elseif ($daysLeft <= 30) echo 'days-warning';
                                            else echo 'days-normal';
                                            ?>">
                        <?php echo ceil($daysLeft) . ' days'; ?>
                    </span>
                </span>
            </div>
        </div>

        <!-- Client Information Card -->
        <div class="detail-card">
            <h3><i class="fas fa-user-circle"></i> Client Information</h3>

            <div class="detail-item">
                <span class="detail-label">Client Name</span>
                <span class="detail-value"><?php echo htmlspecialchars($service['client_name'] ?? 'N/A'); ?></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Email</span>
                <span class="detail-value">
                    <a href="mailto:<?php echo $service['email']; ?>" style="color: #6418C3;">
                        <?php echo htmlspecialchars($service['email'] ?? 'N/A'); ?>
                    </a>
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Primary Phone</span>
                <span class="detail-value">
                    <a href="tel:<?php echo $service['primary_phone']; ?>" style="color: #6418C3;">
                        <?php echo htmlspecialchars($service['primary_phone'] ?? 'N/A'); ?>
                    </a>
                </span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Secondary Phone</span>
                <span class="detail-value">
                    <?php if ($service['secondary_phone']) { ?>
                        <a href="tel:<?php echo $service['secondary_phone']; ?>" style="color: #6418C3;">
                            <?php echo htmlspecialchars($service['secondary_phone']); ?>
                        </a>
                    <?php } else { ?>
                        N/A
                    <?php } ?>
                </span>
            </div>
        </div>

        <!-- Alert Tracking Card -->
        <div class="detail-card">
            <h3><i class="fas fa-bell"></i> Alert Tracking</h3>

            <div class="detail-item">
                <span class="detail-label">Alerts Sent</span>
                <span class="detail-value"><strong><?php echo $service['alert_count'] ?? 0; ?></strong></span>
            </div>

            <div class="detail-item">
                <span class="detail-label">Last Alert Sent</span>
                <span class="detail-value">
                    <?php echo $service['last_alert_sent'] ? formatDate($service['last_alert_sent']) : 'Never'; ?>
                </span>
            </div>


        </div>
    </div>


</div>



<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<script>
    function openRenewModal() {
        document.getElementById('renewModal').classList.add('active');
    }

    function closeRenewModal() {
        document.getElementById('renewModal').classList.remove('active');
    }





    // Close modal when clicking outside
    document.getElementById('renewModal').addEventListener('click', function(e) {
        if (e.target === this) closeRenewModal();
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>