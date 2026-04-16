<?php

/**
 * CLIENT PROFILE PAGE - Complete CRM Profile with AJAX Tabs
 * 8 Tabs: Overview | Projects | Services | Invoices | Payments | Quotations | Meetings | Documents
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

$clientId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
global $db;

// Get client
$stmt = $db->prepare("SELECT c.*, u.name as assigned_user FROM clients c LEFT JOIN users u ON c.assigned_user_id = u.id WHERE c.id = :id");
$stmt->execute([':id' => $clientId]);
$client = $stmt->fetch();

if (!$client) {
    setFlash('danger', 'Client not found');
    redirect('admin/clients/index.php');
}

$pageTitle = clean($client['client_name']);

// Get client statistics (for Overview tab)
$stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$projectCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$invoiceCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$totalInvoiced = $stmt->fetch()['total'];

$stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM invoices WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$totalPaid = $stmt->fetch()['total'];

$balance = $totalInvoiced - $totalPaid;

$stmt = $db->prepare("SELECT COUNT(*) as count FROM quotations WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$quotationCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM meetings WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$meetingCount = $stmt->fetch()['count'];

$stmt = $db->prepare("SELECT COUNT(*) as count FROM payments WHERE client_id = :id");
$stmt->execute([':id' => $clientId]);
$paymentCount = $stmt->fetch()['count'];

// Get expiring services count
$stmt = $db->prepare("
    SELECT COUNT(*) as count 
    FROM project_services ps
    JOIN projects p ON ps.project_id = p.id
    WHERE p.client_id = :id AND ps.status = 'active' AND ps.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
");
$stmt->execute([':id' => $clientId]);
$expiringServicesCount = $stmt->fetch()['count'] ?? 0;

// Get revenue data for chart (Last 6 months)
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(issue_date, '%b') as month_name,
        SUM(total) as revenue
    FROM invoices 
    WHERE client_id = :id AND issue_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(issue_date, '%Y-%m')
    ORDER BY issue_date ASC
");
$stmt->execute([':id' => $clientId]);
$chartData = $stmt->fetchAll();

$chartMonths = [];
$chartRevenues = [];
foreach ($chartData as $data) {
    $chartMonths[] = $data['month_name'];
    $chartRevenues[] = (float)$data['revenue'];
}

// Fallback if no data
if (empty($chartMonths)) {
    $chartMonths = ['No Data'];
    $chartRevenues = [0];
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Bootstrap CSS & JS -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@latest/dist/apexcharts.min.js"></script>

<style>
    .profile-banner {
        background: linear-gradient(135deg, #6418C3, #9B59B6);
        border-radius: 12px;
        padding: 40px;
        color: white;
        margin-bottom: 30px;
        position: relative;
        height: 180px;
        display: flex;
        align-items: flex-end;
    }

    .profile-avatar {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 2.5rem;
        font-weight: 700;
        color: #6418C3;
        border: 4px solid white;
        position: absolute;
        top: 40px;
        left: 40px;
    }

    .profile-header-content {
        margin-left: 120px;
        flex: 1;
    }

    .profile-name {
        font-size: 1.8rem;
        font-weight: 700;
        margin: 0 0 5px 0;
    }

    .profile-subtitle {
        font-size: 0.95rem;
        opacity: 0.95;
        margin: 0;
    }

    .profile-actions {
        display: flex;
        gap: 10px;
    }

    .profile-action-btn {
        background: rgba(255, 255, 255, 0.2);
        color: white;
        border: 1px solid white;
        padding: 8px 16px;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
        text-decoration: none;
    }

    .profile-action-btn:hover {
        background: white;
        color: #6418C3;
    }

    .profile-container {
        display: grid;
        grid-template-columns: 300px 1fr;
        gap: 25px;
    }

    .profile-sidebar {
        display: flex;
        flex-direction: column;
        gap: 25px;
    }

    .sidebar-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }

    .sidebar-card-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: #1D1D1D;
        margin-bottom: 15px;
    }

    .info-item {
        margin-bottom: 15px;
    }

    .info-item:last-child {
        margin-bottom: 0;
    }

    .info-label {
        font-size: 0.75rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }

    .info-value {
        font-weight: 600;
        color: #1D1D1D;
        font-size: 0.9rem;
        word-break: break-all;
    }

    .info-value a {
        color: #6418C3;
        text-decoration: none;
        transition: all 0.3s ease;
    }

    .info-value a:hover {
        text-decoration: underline;
    }

    .stat-grid {
        display: grid;
        grid-auto-flow: row;
        gap: 15px;
    }

    .stat-box {
        background: linear-gradient(135deg, rgba(100, 24, 195, 0.1), rgba(30, 170, 231, 0.1));
        padding: 15px;
        border-radius: 8px;
        text-align: center;
        border-left: 3px solid #6418C3;
    }

    .stat-box-number {
        font-size: 1.5rem;
        font-weight: 700;
        color: #6418C3;
        margin: 0;
    }

    .stat-box-label {
        font-size: 0.8rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-top: 3px;
    }

    .btn-whatsapp {
        width: 100%;
        background: #25D366;
        color: white;
        border: none;
        padding: 10px;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        margin-top: 10px;
    }

    .btn-whatsapp:hover {
        background: #20BA5C;
    }

    .tabs {
        background: white;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        overflow: hidden;
    }

    .nav-tabs {
        border-bottom: 1px solid #f0f0f0;
        display: flex;
        vertical-align: bottom;
        gap: 0;
        padding: 0;
        margin: 0;
        list-style: none;
    }

    .nav-link {
        padding: 15px 20px;
        background: white;
        border: none;
        cursor: pointer;
        font-weight: 600;
        font-size: 0.9rem;
        color: #999;
        transition: all 0.3s ease;
        border-bottom: 3px solid transparent;
    }

    .nav-link:hover {
        color: #6418C3;
    }

    .nav-link.active {
        color: #6418C3 !important;
        border-bottom-color: #6418C3 !important;
        background: #f8f5ff !important;
    }

    /* Tab Pane Visibility */
    .tab-content {
        position: relative;
        width: 100%;
    }

    .tab-pane {
        display: none !important;
        width: 100%;
        padding: 25px;
    }

    .tab-pane.show {
        display: block !important;
        animation: fadeInTab 0.3s ease-in-out;
    }

    .tab-pane.active {
        display: block !important;
    }

    @keyframes fadeInTab {
        from {
            opacity: 0;
            transform: translateY(5px);
        }

        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    /* Tab Loader */
    .tab-loader {
        text-align: center;
        padding: 60px 40px;
        color: #999;
        font-size: 1.1rem;
    }

    .tab-loader i {
        margin-right: 10px;
        color: #6418C3;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
        margin: 0;
        padding: 0;
    }

    .table thead {
        background: #f8f9ff;
        color: #6418C3;
        text-transform: uppercase;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .table th,
    .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
    }

    .table tbody tr:hover {
        background: #f8f9ff;
    }

    .empty-state {
        text-align: center;
        padding: 40px;
        color: #999;
        display: block;
        width: 100%;
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
    }

    /* Service Card */
    .service-card {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        border-left: 3px solid;
    }

    .service-name {
        font-weight: 600;
        color: #1D1D1D;
        margin: 0;
    }

    .service-dates {
        font-size: 0.85rem;
        color: #666;
        margin: 8px 0;
    }

    .service-price {
        font-weight: 600;
        color: #6418C3;
        font-size: 1rem;
    }

    .timeline {
        position: relative;
        padding: 20px 0;
    }

    .timeline-item {
        display: flex;
        gap: 15px;
        margin-bottom: 20px;
        padding-bottom: 20px;
        border-bottom: 1px solid #f0f0f0;
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-dot {
        width: 12px;
        height: 12px;
        background: #6418C3;
        border-radius: 50%;
        margin-top: 5px;
    }

    .timeline-content {
        flex: 1;
    }

    .timeline-title {
        font-weight: 600;
        color: #1D1D1D;
        margin-bottom: 3px;
    }

    .timeline-description {
        font-size: 0.85rem;
        color: #666;
    }

    @media (max-width: 1024px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
    }

    /* Notification Buttons */
    .btn-notify {
        transition: all 0.3s ease;
    }

    .btn-notify:hover:not(:disabled) {
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    }

    .btn-notify:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    /* Document Upload Form */
    #uploadDocumentForm input,
    #uploadDocumentForm select {
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        transition: all 0.3s ease;
    }

    #uploadDocumentForm input:focus,
    #uploadDocumentForm select:focus {
        outline: none;
        box-shadow: 0 4px 15px rgba(100, 24, 195, 0.2);
        transform: translateY(-2px);
    }

</style>

<!-- PROFILE BANNER -->
<div class="profile-banner">
    <div class="profile-avatar"><?php echo getInitials($client['client_name']); ?></div>
    <div class="profile-header-content">
        <h1 class="profile-name"><?php echo clean($client['client_name']); ?></h1>
        <p class="profile-subtitle">
            <?php echo clean($client['company_name'] ?? ''); ?> • <?php echo clean($client['country'] ?? 'N/A'); ?>
        </p>
    </div>
    <div class="profile-actions">
        <a href="<?php echo APP_URL; ?>/admin/clients/add.php?id=<?php echo $client['id']; ?>" class="profile-action-btn">
            <i class="fas fa-edit"></i> Edit
        </a>
        <a href="<?php echo APP_URL; ?>/admin/invoices/add.php?client_id=<?php echo $client['id']; ?>" class="profile-action-btn">
            <i class="fas fa-receipt"></i> Invoice
        </a>
        <a href="<?php echo APP_URL; ?>/admin/projects/add.php?client_id=<?php echo $client['id']; ?>" class="profile-action-btn">
            <i class="fas fa-tasks"></i> Project
        </a>
        <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $client['primary_phone']); ?>" target="_blank" class="profile-action-btn">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
    </div>
</div>

<!-- PROFILE BODY -->
<div class="profile-container">
    <!-- LEFT SIDEBAR -->
    <div class="profile-sidebar">
        <!-- Contact Details -->
        <div class="sidebar-card">
            <h5 class="sidebar-card-title"><i class="fas fa-phone"></i> Contact Details</h5>

            <div class="info-item">
                <div class="info-label">Email</div>
                <div class="info-value">
                    <a href="mailto:<?php echo sanitizeEmail($client['email']); ?>"><?php echo clean($client['email']); ?></a>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Primary Phone</div>
                <div class="info-value">
                    <a href="tel:<?php echo $client['primary_phone']; ?>"><?php echo clean($client['primary_phone']); ?></a>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Secondary Phone</div>
                <div class="info-value">
                    <?php echo $client['secondary_phone'] ? '<a href="tel:' . $client['secondary_phone'] . '">' . clean($client['secondary_phone']) . '</a>' : 'N/A'; ?>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Website</div>
                <div class="info-value">
                    <?php echo $client['website'] ? '<a href="' . sanitizeUrl($client['website']) . '" target="_blank">View Site →</a>' : 'N/A'; ?>
                </div>
            </div>

            <div class="info-item">
                <div class="info-label">Address</div>
                <div class="info-value"><?php echo clean($client['address'] ?? 'N/A'); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">City / Country</div>
                <div class="info-value"><?php echo clean($client['city'] ?? '') . ' / ' . clean($client['country'] ?? ''); ?></div>
            </div>

            <button class="btn-whatsapp" onclick="sendWhatsAppMessage('<?php echo preg_replace('/[^0-9]/', '', $client['primary_phone']); ?>', 'Hi <?php echo $client['client_name']; ?>, ')">
                <i class="fab fa-whatsapp"></i> Send WhatsApp
            </button>
        </div>

        <!-- Quick Stats -->
        <div class="sidebar-card">
            <h5 class="sidebar-card-title"><i class="fas fa-bell"></i> Expiring Services</h5>
            <?php
            $stmt = $db->prepare("
                SELECT ps.*, p.project_name
                FROM project_services ps
                JOIN projects p ON ps.project_id = p.id
                WHERE p.client_id = :id AND ps.status IN ('active', 'expired')
                ORDER BY ps.expiry_date ASC
                LIMIT 8
            ");
            $stmt->execute([':id' => $clientId]);
            $expiringServices = $stmt->fetchAll();

            if (count($expiringServices) > 0):
                foreach ($expiringServices as $svc):
                    $daysLeft = $svc['expiry_date'] ? (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60) : 999;
                    if ($daysLeft < 0) {
                        $badgeColor = '#FF5E5E';
                        $badgeText = '⚠️ EXPIRED';
                        $showNotify = true;
                    } elseif ($daysLeft < 7) {
                        $badgeColor = '#FF9B52';
                        $badgeText = '⏰ ' . round($daysLeft) . 'd';
                        $showNotify = true;
                    } elseif ($daysLeft < 30) {
                        $badgeColor = '#FFD700';
                        $badgeText = '📅 ' . round($daysLeft) . 'd';
                        $showNotify = true;
                    } else {
                        $badgeColor = '#2BC155';
                        $badgeText = '✓ ' . round($daysLeft) . 'd';
                        $showNotify = false;
                    }
            ?>
                    <div style="margin-bottom: 15px; padding: 12px; background: #f9f9f9; border-radius: 8px; border-left: 4px solid <?php echo $badgeColor; ?>;">
                        <p style="margin: 0 0 6px 0; font-weight: 700; color: #1D1D1D; font-size: 0.85rem; display: flex; justify-content: space-between; align-items: center;">
                            <?php echo clean($svc['service_name']); ?>
                            <span style="background: <?php echo $badgeColor; ?>; color: white; padding: 3px 8px; border-radius: 3px; font-size: 0.65rem; font-weight: 600; white-space: nowrap; margin-left: 8px;">
                                <?php echo $badgeText; ?>
                            </span>
                        </p>
                        <p style="margin: 0 0 8px 0; color: #999; font-size: 0.75rem;">
                            <?php echo clean($svc['project_name']); ?>
                        </p>
                        <?php if ($showNotify): ?>
                            <div style="display: flex; gap: 6px; font-size: 0.75rem;">
                                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $client['primary_phone']) ?: preg_replace('/[^0-9]/', '', $client['secondary_phone']); ?>?text=<?php echo urlencode('Hi ' . $client['client_name'] . ',\n\n⚠️ Service Renewal Notice\n\nService: ' . $svc['service_name'] . '\nProject: ' . $svc['project_name'] . '\nExpiry: ' . formatDate($svc['expiry_date']) . '\n\nPlease contact us for renewal.\n\nThank you!'); ?>" target="_blank" class="btn-notify" style="flex: 1; background: #25D366; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.7rem; text-decoration: none; display: flex; align-items: center; justify-content: center;">
                                    💬 WhatsApp
                                </a>
                                <button class="btn-notify" onclick="notifyServiceExpiry(<?php echo $svc['id']; ?>, 'email', this)" style="flex: 1; background: #1EAAE7; color: white; border: none; padding: 6px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.7rem;">
                                    📧 Email
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach;
            else: ?>
                <p style="color: #999; text-align: center; margin: 20px 0; font-size: 0.85rem;">No expiring services</p>
            <?php endif; ?>
        </div>


    </div>

    <!-- RIGHT TABS -->
    <div class="tabs">
        <!-- TAB NAVIGATION -->
        <ul class="nav-tabs" role="tablist">
            <li role="presentation">
                <a class="nav-link active" data-bs-toggle="tab" href="#overview" role="tab" data-tab="overview"><i class="fas fa-chart-line"></i> Overview</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#projects" role="tab" data-tab="projects"><i class="fas fa-tasks"></i> Projects</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#services" role="tab" data-tab="services"><i class="fas fa-cogs"></i> Services</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab" data-tab="invoices"><i class="fas fa-receipt"></i> Invoices</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab" data-tab="payments"><i class="fas fa-credit-card"></i> Payments</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#quotations" role="tab" data-tab="quotations"><i class="fas fa-quote-left"></i> Quotations</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#meetings" role="tab" data-tab="meetings"><i class="fas fa-calendar"></i> Meetings</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#documents" role="tab" data-tab="documents"><i class="fas fa-file"></i> Documents</a>
            </li>
        </ul>

        <div class="tab-content">
            <!-- TAB: OVERVIEW -->
            <div id="overview" class="tab-pane fade show active" role="tabpanel" data-loaded="true">
                <!-- Summary Cards -->
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 30px;">
                    <div style="background: linear-gradient(135deg, #6418C3, #9B59B6); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(100,24,195,0.2);">
                        <small style="opacity: 0.9;">Revenue (Last 6 months)</small>
                        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">
                            <?php echo formatCurrency(array_sum($chartRevenues)); ?>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #FF9B52, #FF6B35); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(255,155,82,0.2);">
                        <small style="opacity: 0.9;">Outstanding Balance</small>
                        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">
                            <?php echo formatCurrency($balance); ?>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #2BC155, #27AE60); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(43,193,85,0.2);">
                        <small style="opacity: 0.9;">Total Received</small>
                        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">
                            <?php echo formatCurrency($totalPaid); ?>
                        </div>
                    </div>
                    <div style="background: linear-gradient(135deg, #1EAAE7, #3498DB); color: white; padding: 20px; border-radius: 8px; box-shadow: 0 4px 15px rgba(30,170,231,0.2);">
                        <small style="opacity: 0.9;">Active Projects</small>
                        <div style="font-size: 1.8rem; font-weight: 700; margin-top: 8px;">
                            <?php echo $projectCount; ?>
                        </div>
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 2fr 1fr; gap: 25px;">
                    <!-- Chart -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <h5 style="margin-bottom: 20px; font-weight: 600;">Revenue Chart (Last 6 Months)</h5>
                        <div id="clientRevenueChart" style="height: 300px;"></div>
                    </div>

                    <!-- Quick Info -->
                    <div style="background: white; padding: 20px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
                        <h5 style="margin-bottom: 20px; font-weight: 600;">Quick Summary</h5>
                        <div style="display: flex; flex-direction: column; gap: 15px;">
                            <div style="padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #6418C3;">
                                <small style="color: #999; font-weight: 600;">Projects</small>
                                <p style="margin: 5px 0 0 0; font-size: 1.3rem; font-weight: 700; color: #1D1D1D;"><?php echo $projectCount; ?></p>
                            </div>
                            <div style="padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #1EAAE7;">
                                <small style="color: #999; font-weight: 600;">Invoices</small>
                                <p style="margin: 5px 0 0 0; font-size: 1.3rem; font-weight: 700; color: #1D1D1D;"><?php echo $invoiceCount; ?></p>
                            </div>
                            <div style="padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #2BC155;">
                                <small style="color: #999; font-weight: 600;">Payments</small>
                                <p style="margin: 5px 0 0 0; font-size: 1.3rem; font-weight: 700; color: #1D1D1D;"><?php echo $paymentCount; ?></p>
                            </div>
                            <div style="padding: 12px; background: #f9f9f9; border-radius: 6px; border-left: 3px solid #FF9B52;">
                                <small style="color: #999; font-weight: 600;">Expiring Soon</small>
                                <p style="margin: 5px 0 0 0; font-size: 1.3rem; font-weight: 700; color: #1D1D1D;"><?php echo $expiringServicesCount; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB: PROJECTS (AJAX) -->
            <div id="projects" class="tab-pane fade" role="tabpanel" data-tab="projects">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading projects...</div>
            </div>

            <!-- TAB: SERVICES (AJAX) -->
            <div id="services" class="tab-pane fade" role="tabpanel" data-tab="services">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>
            </div>

            <!-- TAB: INVOICES (AJAX) -->
            <div id="invoices" class="tab-pane fade" role="tabpanel" data-tab="invoices">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading invoices...</div>
            </div>

            <!-- TAB: PAYMENTS (AJAX) -->
            <div id="payments" class="tab-pane fade" role="tabpanel" data-tab="payments">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading payments...</div>
            </div>

            <!-- TAB: QUOTATIONS (AJAX) -->
            <div id="quotations" class="tab-pane fade" role="tabpanel" data-tab="quotations">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading quotations...</div>
            </div>

            <!-- TAB: MEETINGS (AJAX) -->
            <div id="meetings" class="tab-pane fade" role="tabpanel" data-tab="meetings">
                <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading meetings...</div>
            </div>

            <!-- TAB: DOCUMENTS (AJAX) -->
            <div id="documents" class="tab-pane fade" role="tabpanel" data-tab="documents">


                <!-- Documents List - Loaded via AJAX -->
                <div id="documentsContent">
                    <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading documents...</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const CLIENT_ID = <?php echo $clientId; ?>;

    // ========== TAB INITIALIZATION & AJAX LOADING ==========
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 TAB SYSTEM INITIALIZING...');

        // Get all tab links
        const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
        console.log(`📌 Found ${tabLinks.length} tabs`);

        // Attach click handler to each tab
        tabLinks.forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const tabName = this.getAttribute('data-tab');
                const targetId = this.getAttribute('href');
                console.log(`\n👆 TAB CLICKED: ${tabName}`);

                // Remove active class from ALL links
                document.querySelectorAll('[data-bs-toggle="tab"]').forEach(tab => {
                    tab.classList.remove('active');
                });

                // Hide ALL panes
                document.querySelectorAll('.tab-pane').forEach(pane => {
                    pane.style.display = 'none';
                    pane.classList.remove('show', 'active');
                });

                // Add active class to THIS link
                this.classList.add('active');
                console.log(`✔️ Link activated`);

                // Show the target pane
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    targetPane.style.display = 'block';
                    targetPane.classList.add('show', 'active');
                    console.log(`✅ Pane shown: ${targetId}`);

                    // Load AJAX data if not already loaded
                    if (tabName !== 'overview') {
                        loadTabData(tabName, targetPane);
                    } else if (tabName === 'overview') {
                        setTimeout(() => {
                            if (window.renderChart) {
                                console.log('📊 Rendering chart...');
                                window.renderChart();
                            }
                        }, 100);
                    }
                } else {
                    console.error(`❌ Pane not found: ${targetId}`);
                }
            });
        });

        // Render chart on load
        setTimeout(() => {
            if (window.renderChart) {
                console.log('📊 Initial chart render...');
                window.renderChart();
            }
        }, 300);

        console.log('✅ TAB SYSTEM READY');
    });

    // Load tab data via AJAX
    function loadTabData(tabName, tabPane) {
        console.log(`📡 Fetching data for tab: ${tabName}...`);

        // For documents tab, target the content div only
        const contentTarget = tabName === 'documents' ? document.getElementById('documentsContent') : tabPane;

        fetch(`<?php echo APP_URL; ?>/admin/clients/ajax-tabs.php?client_id=${CLIENT_ID}&tab=${tabName}`)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
                console.log(`✅ Data loaded for ${tabName}`, data);
                if (data.html) {
                    contentTarget.innerHTML = data.html;
                    // Re-attach upload form if documents tab
                    if (tabName === 'documents') {
                        attachDocumentUploadHandler();
                    }
                } else if (data.error) {
                    contentTarget.innerHTML = `<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>${data.error}</p></div>`;
                }
            })
            .catch(error => {
                console.error(`❌ Error loading ${tabName}:`, error);
                contentTarget.innerHTML = `<div class="empty-state"><i class="fas fa-exclamation-triangle empty-state-icon"></i><p>Error loading data. Please try again.</p></div>`;
            });
    }

    // ========== CHART RENDERING ==========
    window.chartRendered = false;

    window.renderChart = function() {
        if (window.chartRendered) return;

        if (typeof ApexCharts === 'undefined') {
            console.warn('⏳ ApexCharts not ready');
            setTimeout(window.renderChart, 500);
            return;
        }

        const container = document.getElementById('clientRevenueChart');
        if (!container) {
            console.error('❌ Chart container not found');
            return;
        }

        if (container.querySelectorAll('svg').length > 0) {
            console.log('📊 Chart already rendered');
            window.chartRendered = true;
            return;
        }

        try {
            const revenue = <?php echo json_encode($chartRevenues); ?>;
            const months = <?php echo json_encode($chartMonths); ?>;

            const options = {
                series: [{
                    name: 'Revenue',
                    data: revenue
                }],
                chart: {
                    type: 'area',
                    height: 300,
                    animations: {
                        enabled: true
                    }
                },
                colors: ['#6418C3'],
                dataLabels: {
                    enabled: false
                },
                stroke: {
                    curve: 'smooth',
                    width: 2
                },
                fill: {
                    type: 'gradient',
                    gradient: {
                        shadeIntensity: 1,
                        opacityFrom: 0.45,
                        opacityTo: 0.05,
                        stops: [20, 100, 100, 100]
                    }
                },
                xaxis: {
                    categories: months,
                    type: 'category'
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            return '₨' + Math.round(val);
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function(val) {
                            return '₨' + (val ? val.toFixed(0) : '0');
                        }
                    }
                },
                grid: {
                    borderColor: '#f0f0f0',
                    padding: {
                        top: 0,
                        right: 30,
                        bottom: 0,
                        left: 60
                    }
                }
            };

            const chart = new ApexCharts(container, options);
            chart.render();
            window.chartRendered = true;
            console.log('✅ Chart rendered successfully!');
        } catch (err) {
            console.error('❌ Chart error:', err);
        }
    };


    window.addEventListener('load', () => {
        setTimeout(window.renderChart, 200);
    });

    // ========== DOCUMENT UPLOAD HANDLER ==========
    function attachDocumentUploadHandler() {
        const form = document.getElementById('uploadDocumentForm');
        if (!form) return;

        // Remove previous listeners
        const newForm = form.cloneNode(true);
        form.parentNode.replaceChild(newForm, form);

        document.getElementById('uploadDocumentForm')?.addEventListener('submit', function(e) {
            e.preventDefault();

            const formData = new FormData();
            formData.append('client_id', CLIENT_ID);
            formData.append('document_name', document.getElementById('docName').value);
            formData.append('document_type', document.getElementById('docType').value);
            formData.append('file', document.getElementById('docFile').files[0]);

            const btn = this.querySelector('button[type="submit"]');
            const originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
            btn.disabled = true;

            fetch('<?php echo APP_URL; ?>/admin/clients/upload-document.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire('✅ Success!', 'Document uploaded successfully!', 'success');
                    document.getElementById('uploadDocumentForm').reset();
                    loadTabData('documents', document.getElementById('documents'));
                } else {
                    Swal.fire('❌ Error!', data.error || 'Upload failed', 'error');
                }
            })
            .catch(err => Swal.fire('❌ Error!', 'Upload failed', 'error'))
            .finally(() => {
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        });
    }

    // Attach upload handler on page load
    document.addEventListener('DOMContentLoaded', () => {
        attachDocumentUploadHandler();
    });

    // ========== VIEW DOCUMENT ==========
    window.viewDoc = function(docId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        fetch(`${APP_URL}/admin/clients/document-view.php?id=${docId}&action=view`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const doc = data.document;
                    Swal.fire({
                        title: doc.original_name,
                        html: `<div style="text-align: left;">
                            <p><strong>Type:</strong> ${doc.doc_type.toUpperCase()}</p>
                            <p><strong>Size:</strong> ${doc.file_size}</p>
                            <p><strong>Uploaded:</strong> ${doc.uploaded_at}</p>
                        </div>`,
                        icon: 'info',
                        confirmButtonText: 'Close'
                    });
                }
            })
            .catch(err => Swal.fire('Error', 'Failed to load document', 'error'));
    };

    // ========== EDIT DOCUMENT ==========
    window.editDoc = function(docId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        fetch(`${APP_URL}/admin/clients/document-view.php?id=${docId}&action=view`)
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    const doc = data.document;
                    Swal.fire({
                        title: 'Edit Document',
                        html: `<input type="text" id="editName" value="${doc.original_name}" placeholder="Document Name" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; margin-bottom: 10px; box-sizing: border-box;">
                            <select id="editType" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; box-sizing: border-box;">
                                <option value="contract" ${doc.doc_type === 'contract' ? 'selected' : ''}>Contract</option>
                                <option value="agreement" ${doc.doc_type === 'agreement' ? 'selected' : ''}>Agreement</option>
                                <option value="invoice" ${doc.doc_type === 'invoice' ? 'selected' : ''}>Invoice</option>
                                <option value="certificate" ${doc.doc_type === 'certificate' ? 'selected' : ''}>Certificate</option>
                                <option value="other" ${doc.doc_type === 'other' ? 'selected' : ''}>Other</option>
                            </select>`,
                        showCancelButton: true,
                        confirmButtonText: 'Save',
                        preConfirm: () => {
                            const name = document.getElementById('editName').value;
                            const type = document.getElementById('editType').value;
                            if (!name) {
                                Swal.showValidationMessage('Name is required');
                                return false;
                            }
                            return {name, type};
                        }
                    }).then(result => {
                        if (result.isConfirmed) {
                            const formData = new FormData();
                            formData.append('original_name', result.value.name);
                            formData.append('doc_type', result.value.type);
                            
                            fetch(`${APP_URL}/admin/clients/document-view.php?id=${docId}&action=edit`, {
                                method: 'POST',
                                body: formData
                            })
                            .then(r => r.json())
                            .then(data => {
                                if (data.success) {
                                    Swal.fire('✅ Updated!', 'Document updated', 'success');
                                    loadTabData('documents', document.getElementById('documents'));
                                } else {
                                    Swal.fire('Error', data.error || 'Update failed', 'error');
                                }
                            });
                        }
                    });
                }
            });
    };

    // ========== DELETE DOCUMENT ==========
    window.deleteDoc = function(docId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: 'Delete Document?',
            text: 'This cannot be undone',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5E5E',
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', docId);
                
                fetch(`${APP_URL}/admin/clients/document-delete.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Deleted!', 'Document deleted', 'success');
                        loadTabData('documents', document.getElementById('documents'));
                    } else {
                        Swal.fire('Error', data.error || 'Delete failed', 'error');
                    }
                });
            }
        });
    };

    // ========== DOCUMENT UPLOAD HANDLER (LEGACY) ==========
    // (Removed - using attachDocumentUploadHandler instead)

    // ========== SERVICE EXPIRY NOTIFICATION ==========
    window.notifyServiceExpiry = function(serviceId, type, button) {
        const btn = button;
        const originalText = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

        fetch('<?php echo APP_URL; ?>/admin/api/send-service-alert.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `service_id=${serviceId}&client_id=${CLIENT_ID}&alert_method=${type}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                const typeIcon = type === 'whatsapp' ? '💬' : '📧';
                alert(`✅ ${typeIcon} ${data.message}`);
                btn.style.opacity = '0.6';
                btn.disabled = true;
            } else {
                alert('❌ Error: ' + (data.error || 'Notification failed'));
            }
        })
        .catch(err => {
            console.error('Notification error:', err);
            alert('❌ Error: ' + err.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            if (!btn.disabled || btn.style.opacity !== '0.6') {
                btn.disabled = false;
            }
        });
    };

    // ========== MEETING NOTES MANAGEMENT ==========
    window.toggleMeetingNotes = function(meetingId) {
        const notesSection = document.getElementById(`notes-section-${meetingId}`);
        if (notesSection) {
            notesSection.style.display = notesSection.style.display === 'none' ? 'block' : 'none';
        }
    };

    window.saveMeetingNotes = function(meetingId) {
        const textarea = document.getElementById(`notes-content-${meetingId}`);
        if (!textarea) return;

        const notes = textarea.value;
        const btn = event.target.closest('button');
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
        btn.disabled = true;

        fetch('<?php echo APP_URL; ?>/admin/clients/meeting-notes.php?action=save_notes', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: `meeting_id=${meetingId}&client_id=${CLIENT_ID}&notes=${encodeURIComponent(notes)}`
        })
        .then(r => r.json())
        .then(data => {
            if (data.success) {
                alert('✅ Meeting notes saved successfully!');
                textarea.style.borderColor = '#2BC155';
                setTimeout(() => {
                    textarea.style.borderColor = '#ddd';
                }, 2000);
            } else {
                alert('❌ Error: ' + (data.error || 'Failed to save notes'));
            }
        })
        .catch(err => {
            console.error('Save error:', err);
            alert('❌ Error: ' + err.message);
        })
        .finally(() => {
            btn.innerHTML = originalText;
            btn.disabled = false;
        });
    };

    // ========== DOCUMENT BANK VIEW ==========
    window.viewDocBank = function(docId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        
        fetch(`${APP_URL}/admin/api/get-documents.php?id=${docId}`)
            .then(r => r.json())
            .then(data => {
                if (data.success && data.documents && data.documents.length > 0) {
                    const doc = data.documents[0];
                    const fileExt = doc.file_path.split('.').pop().toLowerCase();
                    let content = '';
                    
                    // Check file type and display appropriate viewer
                    if (['jpg', 'jpeg', 'png', 'webp'].includes(fileExt)) {
                        content = `<img src="${APP_URL}/admin/api/download-document.php?id=${docId}" style="max-width: 100%; max-height: 500px; border-radius: 6px;">`;
                    } else if (fileExt === 'pdf') {
                        content = `<iframe src="${APP_URL}/admin/api/download-document.php?id=${docId}" style="width: 100%; height: 500px; border: 1px solid #ddd; border-radius: 6px;"></iframe>`;
                    } else {
                        content = `<p style="text-align: center; color: #666; padding: 20px;">Preview not available for this file type</p>`;
                    }
                    
                    Swal.fire({
                        title: doc.document_title,
                        html: `
                            <div style="text-align: center;">
                                ${content}
                                <div style="margin-top: 20px; padding: 15px; background: #f5f5f5; border-radius: 6px; text-align: left; font-size: 0.9rem;">
                                    <p><strong>File:</strong> ${doc.original_name || doc.file_name}</p>
                                    <p><strong>Size:</strong> ${formatFileSizeJS(doc.file_size)}</p>
                                    <p><strong>Uploaded:</strong> ${new Date(doc.uploaded_at).toLocaleDateString()}</p>
                                </div>
                            </div>
                        `,
                        width: '800px',
                        showConfirmButton: true,
                        confirmButtonText: 'Close'
                    });
                }
            })
            .catch(err => {
                console.error('Error:', err);
                Swal.fire('Error', 'Failed to load document', 'error');
            });
    };

    // ========== DOCUMENT BANK DELETE ==========
    window.deleteDocBank = function(docId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: 'Delete Document?',
            text: 'This action cannot be undone.',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5E5E',
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('id', docId);
                
                fetch(`${APP_URL}/admin/api/delete-document.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Deleted!', 'Document has been deleted', 'success');
                        loadTabData('documents', document.getElementById('documents'));
                    } else {
                        Swal.fire('Error', data.error || 'Failed to delete document', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('Error', 'Failed to delete document', 'error');
                });
            }
        });
    };

    // ========== HELPER FUNCTION: FORMAT FILE SIZE ==========
    function formatFileSizeJS(bytes) {
        if (bytes === 0) return '0 B';
        const k = 1024;
        const sizes = ['B', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return Math.round((bytes / Math.pow(k, i)) * 100) / 100 + ' ' + sizes[i];
    }

    // ========== MEETING NOTES MANAGEMENT - NEW CARD FUNCTIONS ==========
    window.openAddNoteModal = function(meetingId, meetingTitle) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: `Add Note to "${meetingTitle}"`,
            html: '<textarea id="noteInput" placeholder="Enter meeting note..." style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; resize: vertical; min-height: 100px;"></textarea>',
            showCancelButton: true,
            confirmButtonText: 'Add Note',
            confirmButtonColor: '#6418C3',
            preConfirm: () => {
                const note = document.getElementById('noteInput').value.trim();
                if (!note) {
                    Swal.showValidationMessage('Please enter a note');
                    return false;
                }
                return note;
            }
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('meeting_id', meetingId);
                formData.append('action', 'add_note');
                formData.append('note', result.value);

                fetch(`${APP_URL}/admin/clients/meeting-notes.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Added!', 'Note added successfully', 'success');
                        loadTabData('meetings', document.getElementById('meetings'));
                    } else {
                        Swal.fire('Error', data.error || 'Failed to add note', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('Error', 'Failed to add note', 'error');
                });
            }
        });
    };

    window.editMeetingNote = function(meetingId, noteIndex, noteText) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: 'Edit Note',
            html: '<textarea id="noteEditInput" placeholder="Edit meeting note..." style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-size: 0.9rem; resize: vertical; min-height: 100px;">' + noteText + '</textarea>',
            showCancelButton: true,
            confirmButtonText: 'Update Note',
            confirmButtonColor: '#1EAAE7',
            preConfirm: () => {
                const note = document.getElementById('noteEditInput').value.trim();
                if (!note) {
                    Swal.showValidationMessage('Please enter a note');
                    return false;
                }
                return note;
            }
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('meeting_id', meetingId);
                formData.append('action', 'edit_note');
                formData.append('note_index', noteIndex);
                formData.append('note', result.value);

                fetch(`${APP_URL}/admin/clients/meeting-notes.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Updated!', 'Note updated successfully', 'success');
                        loadTabData('meetings', document.getElementById('meetings'));
                    } else {
                        Swal.fire('Error', data.error || 'Failed to update note', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('Error', 'Failed to update note', 'error');
                });
            }
        });
    };

    window.deleteMeetingNote = function(meetingId, noteIndex) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: 'Delete Note?',
            text: 'This note will be removed permanently',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5E5E',
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('meeting_id', meetingId);
                formData.append('action', 'delete_note');
                formData.append('note_index', noteIndex);

                fetch(`${APP_URL}/admin/clients/meeting-notes.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Deleted!', 'Note deleted successfully', 'success');
                        loadTabData('meetings', document.getElementById('meetings'));
                    } else {
                        Swal.fire('Error', data.error || 'Failed to delete note', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('Error', 'Failed to delete note', 'error');
                });
            }
        });
    };

    window.editMeeting = function(meetingId) {
        const APP_URL = '<?php echo APP_URL; ?>';
        alert('Edit meeting feature coming soon!');
    };

    window.deleteMeeting = function(meetingId, meetingTitle) {
        const APP_URL = '<?php echo APP_URL; ?>';
        Swal.fire({
            title: 'Delete Meeting?',
            text: `"${meetingTitle}" will be permanently removed`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#FF5E5E',
            confirmButtonText: 'Yes, Delete'
        }).then(result => {
            if (result.isConfirmed) {
                const formData = new FormData();
                formData.append('meeting_id', meetingId);
                formData.append('action', 'delete_meeting');

                fetch(`${APP_URL}/admin/clients/meeting-notes.php`, {
                    method: 'POST',
                    body: formData
                })
                .then(r => r.json())
                .then(data => {
                    if (data.success) {
                        Swal.fire('✅ Deleted!', 'Meeting deleted successfully', 'success');
                        loadTabData('meetings', document.getElementById('meetings'));
                    } else {
                        Swal.fire('Error', data.error || 'Failed to delete meeting', 'error');
                    }
                })
                .catch(err => {
                    console.error('Error:', err);
                    Swal.fire('Error', 'Failed to delete meeting', 'error');
                });
            }
        });
    };</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>