<?php

/**
 * CLIENT PROFILE PAGE - Complete CRM Profile
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

// Get client statistics
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

// Get additional statistics
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

<!-- Bootstrap CSS & JS (for tabs) -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- ApexCharts Library (for revenue chart) -->
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

    /* Bootstrap Tab Pane Styles */
    .tab-pane {
        display: none;
        padding: 25px;
    }

    .tab-pane.show {
        display: block;
    }

    .tab-pane.fade {
        opacity: 0;
        transition: opacity 0.15s linear;
    }

    .tab-pane.fade.show {
        opacity: 1;
    }

    .tab-content {
        padding: 0;
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
        display: table-header-group;
    }

    .table th,
    .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
        display: table-cell;
    }

    .table tbody {
        display: table-row-group;
    }

    .table tbody tr {
        display: table-row;
    }

    .table tbody tr:hover {
        background: #f8f9ff;
    }

    .service-card {
        background: #f9f9f9;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
    }

    .service-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 10px;
    }

    .service-name {
        font-weight: 600;
        color: #1D1D1D;
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

    /* Tab Loader Style */
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

    .empty-state p {
        margin: 10px 0;
        font-size: 1rem;
    }

    /* CRITICAL: Tab Pane Visibility Control */
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

    @media (max-width: 1024px) {
        .profile-container {
            grid-template-columns: 1fr;
        }
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
            <h5 class="sidebar-card-title"><i class="fas fa-chart-bar"></i> Quick Stats</h5>
            <div class="stat-grid">
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo formatCurrency($totalPaid); ?></p>
                    <p class="stat-box-label">Total Paid</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo formatCurrency($balance); ?></p>
                    <p class="stat-box-label">Balance Due</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $projectCount; ?></p>
                    <p class="stat-box-label">Active Projects</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $invoiceCount; ?></p>
                    <p class="stat-box-label">Invoices</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $paymentCount; ?></p>
                    <p class="stat-box-label">Payments</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $quotationCount; ?></p>
                    <p class="stat-box-label">Quotations</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $meetingCount; ?></p>
                    <p class="stat-box-label">Meetings</p>
                </div>
                <div class="stat-box" style="<?php echo $expiringServicesCount > 0 ? 'border-left-color: #FF9B52; background: linear-gradient(135deg, rgba(255,155,82,0.1), rgba(255,107,53,0.1));' : ''; ?>">
                    <p class="stat-box-number" style="<?php echo $expiringServicesCount > 0 ? 'color: #FF9B52;' : ''; ?>"><?php echo $expiringServicesCount; ?></p>
                    <p class="stat-box-label">Expiring Soon</p>
                </div>
            </div>
        </div>

        <!-- Assigned Services & Expiry List -->
        <div class="sidebar-card">
            <h5 class="sidebar-card-title"><i class="fas fa-cogs"></i> Services (Active & Expiring)</h5>
            <?php
            // Get all services from projects assigned to this client
            $stmt = $db->prepare("
                SELECT ps.*, p.project_name, p.project_code
                FROM project_services ps
                JOIN projects p ON ps.project_id = p.id
                WHERE p.client_id = :id AND ps.status IN ('active', 'expired')
                ORDER BY ps.expiry_date ASC
                LIMIT 10
            ");
            $stmt->execute([':id' => $clientId]);
            $allServices = $stmt->fetchAll();

            if (count($allServices) > 0):
                foreach ($allServices as $svc):
                    $daysLeft = $svc['expiry_date'] ? (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60) : 999;
                    if ($daysLeft < 0) {
                        $badgeColor = '#FF5E5E';
                        $badgeText = '⚠️ EXPIRED';
                    } elseif ($daysLeft < 7) {
                        $badgeColor = '#FF9B52';
                        $badgeText = '⏰ ' . round($daysLeft) . 'd';
                    } elseif ($daysLeft < 30) {
                        $badgeColor = '#FFD700';
                        $badgeText = '📅 ' . round($daysLeft) . 'd';
                    } else {
                        $badgeColor = '#2BC155';
                        $badgeText = '✓ ' . round($daysLeft) . 'd';
                    }
            ?>
                    <div style="margin-bottom: 12px; padding: 10px; background: #f9f9f9; border-radius: 6px; font-size: 0.82rem; border-left: 3px solid <?php echo $badgeColor; ?>;">
                        <p style="margin: 0 0 5px 0; font-weight: 700; color: #1D1D1D;">
                            <?php echo clean($svc['service_name']); ?>
                            <span style="display: inline-block; background: <?php echo $badgeColor; ?>; color: white; padding: 2px 6px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; margin-left: 5px;">
                                <?php echo $badgeText; ?>
                            </span>
                        </p>
                        <p style="margin: 0; color: #666; font-size: 0.75rem;">
                            📦 <?php echo clean($svc['project_name']); ?>
                        </p>
                        <p style="margin: 3px 0 0 0; color: #999; font-size: 0.75rem;">
                            Expires: <?php echo formatDate($svc['expiry_date']); ?>
                        </p>
                    </div>
                <?php endforeach;
            else: ?>
                <p style="color: #999; text-align: center; margin: 20px 0; font-size: 0.85rem;">No services assigned</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT TABS -->
    <div class="tabs">
        <!-- TAB NAVIGATION -->
        <ul class="nav-tabs" role="tablist">
            <li role="presentation">
                <a class="nav-link active" data-bs-toggle="tab" href="#overview" role="tab"><i class="fas fa-chart-line"></i> Overview</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#projects" role="tab"><i class="fas fa-tasks"></i> Projects</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#services" role="tab"><i class="fas fa-cogs"></i> Services</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#invoices" role="tab"><i class="fas fa-receipt"></i> Invoices</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#payments" role="tab"><i class="fas fa-credit-card"></i> Payments</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#quotations" role="tab"><i class="fas fa-quote-left"></i> Quotations</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#meetings" role="tab"><i class="fas fa-calendar"></i> Meetings</a>
            </li>
            <li role="presentation">
                <a class="nav-link" data-bs-toggle="tab" href="#documents" role="tab"><i class="fas fa-file"></i> Documents</a>
            </li>
        </ul>

        <div class="tab-content">
        <!-- TAB: OVERVIEW (First tab - ACTIVE) -->
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

            <h5 style="margin: 30px 0 20px 0; font-weight: 600;"><i class="fas fa-history"></i> Recent Activity</h5>
            <div class="timeline">
                <?php
                $stmt = $db->prepare("
                    SELECT * FROM activity_logs
                    WHERE 
                        (table_name IN ('projects', 'invoices', 'payments', 'meetings') AND record_id IN (
                            SELECT id FROM projects WHERE client_id = ?
                            UNION SELECT id FROM invoices WHERE client_id = ?
                            UNION SELECT id FROM payments WHERE client_id = ?
                            UNION SELECT id FROM meetings WHERE client_id = ?
                        ))
                    ORDER BY created_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$clientId, $clientId, $clientId, $clientId]);
                $activities = $stmt->fetchAll();

                if (count($activities) > 0):
                    foreach ($activities as $activity):
                        $action_icons = [
                            'CREATE' => '✨',
                            'UPDATE' => '📝',
                            'DELETE' => '🗑️',
                            'VIEW' => '👁️'
                        ];
                ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-content">
                                <p class="timeline-title">
                                    <?php echo $action_icons[$activity['action']] ?? '📌'; ?>
                                    <?php echo ucfirst(strtolower($activity['action'])); ?> -
                                    <?php echo ucfirst(str_replace('_', ' ', $activity['table_name'])); ?>
                                </p>
                                <p class="timeline-description"><?php echo clean($activity['description'] ?? ''); ?></p>
                                <small style="color: #bbb;"><?php echo timeAgo($activity['created_at']); ?></small>
                            </div>
                        </div>
                    <?php endforeach;
                else:
                    ?>
                    <div class="empty-state">
                        <i class="fas fa-inbox empty-state-icon"></i>
                        <p>No recent activity</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- TAB: PROJECTS -->
        <div id="projects" class="tab-pane fade" role="tabpanel" data-tab="projects">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading projects...</div>
        </div>

        <!-- TAB: SERVICES -->
        <div id="services" class="tab-pane fade" role="tabpanel" data-tab="services">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading services...</div>
        </div>

        <!-- TAB: INVOICES -->
        <div id="invoices" class="tab-pane fade" role="tabpanel" data-tab="invoices">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading invoices...</div>
        </div>

        <!-- TAB: PAYMENTS -->
        <div id="payments" class="tab-pane fade" role="tabpanel" data-tab="payments">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading payments...</div>
        </div>

        <!-- TAB: QUOTATIONS -->
        <div id="quotations" class="tab-pane fade" role="tabpanel" data-tab="quotations">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading quotations...</div>
        </div>

        <!-- TAB: MEETINGS -->
        <div id="meetings" class="tab-pane fade" role="tabpanel" data-tab="meetings">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading meetings...</div>
        </div>

        <!-- TAB: DOCUMENTS -->
        <div id="documents" class="tab-pane fade" role="tabpanel" data-tab="documents">
            <div class="tab-loader"><i class="fas fa-spinner fa-spin"></i> Loading documents...</div>
        </div>

        <!-- TAB: SERVICES -->
        <div id="services" class="tab-pane fade" role="tabpanel">
            <?php
            // Get all services from all projects of this client
            $stmt = $db->prepare("
                SELECT ps.*, p.project_name, p.project_code
                FROM project_services ps
                JOIN projects p ON ps.project_id = p.id
                WHERE p.client_id = :id
                ORDER BY 
                    CASE WHEN ps.status = 'active' AND ps.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY) THEN 1
                         WHEN ps.status = 'active' AND ps.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 2
                         WHEN ps.status = 'active' THEN 3
                         WHEN ps.status = 'expired' THEN 4
                         ELSE 5
                    END ASC,
                    ps.expiry_date ASC
            ");
            $stmt->execute([':id' => $clientId]);
            $services = $stmt->fetchAll();

            if (count($services) > 0):
                $groupedByProject = [];
                foreach ($services as $svc) {
                    if (!isset($groupedByProject[$svc['project_id']])) {
                        $groupedByProject[$svc['project_id']] = [
                            'project_name' => $svc['project_name'],
                            'project_code' => $svc['project_code'],
                            'items' => []
                        ];
                    }
                    $groupedByProject[$svc['project_id']]['items'][] = $svc;
                }

                foreach ($groupedByProject as $proj):
            ?>
                    <h6 style="margin: 20px 0 15px 0; padding: 10px; background: #f8f9ff; border-radius: 6px; border-left: 3px solid #6418C3;">
                        📦 <?php echo clean($proj['project_name']); ?> <code style="font-size: 0.75rem; opacity: 0.7;">[<?php echo clean($proj['project_code']); ?>]</code>
                    </h6>
                    <?php
                    foreach ($proj['items'] as $svc):
                        $daysLeft = $svc['expiry_date'] ? (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60) : 999;
                        if ($daysLeft < 0) {
                            $statusColor = '#FF5E5E';
                            $statusText = '⚠️ EXPIRED';
                        } elseif ($daysLeft < 7) {
                            $statusColor = '#FF9B52';
                            $statusText = '⏰ EXPIRING SOON (' . round($daysLeft) . 'd)';
                        } elseif ($daysLeft < 30) {
                            $statusColor = '#FFD700';
                            $statusText = '📅 EXPIRING (' . round($daysLeft) . 'd)';
                        } else {
                            $statusColor = '#2BC155';
                            $statusText = '✓ ACTIVE (' . round($daysLeft) . 'd)';
                        }
                    ?>
                        <div class="service-card" style="border-left-color: <?php echo $statusColor; ?>;">
                            <div class="service-header">
                                <div style="flex: 1;">
                                    <p class="service-name"><i class="fas fa-cog"></i> <?php echo clean($svc['service_name']); ?></p>
                                </div>
                                <span style="display: inline-block; background: <?php echo $statusColor; ?>; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">
                                    <?php echo $statusText; ?>
                                </span>
                            </div>
                            <div class="service-dates">
                                <strong>Started:</strong> <?php echo formatDate($svc['start_date'] ?? 'N/A'); ?> &nbsp;&nbsp;
                                <strong>Expires:</strong> <?php echo formatDate($svc['expiry_date'] ?? 'N/A'); ?>
                            </div>
                            <div class="service-price">
                                💰 <?php echo formatCurrency($svc['price']); ?>
                            </div>
                            <div style="margin-top: 10px; display: flex; gap: 5px; flex-wrap: wrap;">
                                <?php if ($daysLeft < 30 && $daysLeft >= 0): ?>
                                    <button class="btn btn-sm btn-warning" style="cursor: pointer; font-weight: 600;">
                                        <i class="fas fa-sync-alt"></i> Renew
                                    </button>
                                <?php endif; ?>
                                <a href="<?php echo APP_URL; ?>/admin/projects/services.php?id=<?php echo $svc['project_id']; ?>" class="btn btn-sm btn-info" style="text-decoration: none; cursor: pointer;">
                                    <i class="fas fa-edit"></i> Manage
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php
                endforeach;
            else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No services assigned to projects</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: INVOICES -->
        <div id="invoices" class="tab-pane fade" role="tabpanel">
            <?php
            $stmt = $db->prepare("
                SELECT i.*, p.project_name, p.project_code
                FROM invoices i
                LEFT JOIN projects p ON i.project_id = p.id
                WHERE i.client_id = :id
                ORDER BY i.created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $invoices = $stmt->fetchAll();

            if (count($invoices) > 0):
                // Calculate summary
                $inv_total = array_sum(array_column($invoices, 'total'));
                $inv_paid = array_sum(array_column($invoices, 'paid_amount'));
                $inv_balance = $inv_total - $inv_paid;
            ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: linear-gradient(135deg, rgba(30,170,231,0.1), rgba(26,156,231,0.1)); border-left: 3px solid #1EAAE7; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Total Invoiced</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #1EAAE7;"><?php echo formatCurrency($inv_total); ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Total Paid</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #2BC155;"><?php echo formatCurrency($inv_paid); ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, rgba(255,155,82,0.1), rgba(255,107,53,0.1)); border-left: 3px solid #FF9B52; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Outstanding</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #FF9B52;"><?php echo formatCurrency($inv_balance); ?></div>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Invoice #</th>
                            <th>Project</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Paid</th>
                            <th>Balance</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invoices as $inv): ?>
                            <tr>
                                <td><strong><?php echo clean($inv['invoice_number']); ?></strong></td>
                                <td><small><?php echo $inv['project_name'] ? clean($inv['project_name']) . ' [' . clean($inv['project_code']) . ']' : '-'; ?></small></td>
                                <td><?php echo formatDate($inv['issue_date']); ?></td>
                                <td><?php echo formatCurrency($inv['total']); ?></td>
                                <td style="color: #2BC155; font-weight: 600;"><?php echo formatCurrency($inv['paid_amount']); ?></td>
                                <td style="color: <?php echo $inv['balance'] > 0 ? '#FF9B52' : '#2BC155'; ?>; font-weight: 600;">
                                    <?php echo formatCurrency($inv['balance']); ?>
                                </td>
                                <td><?php echo statusBadge($inv['status']); ?></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/admin/invoices/view.php?id=<?php echo $inv['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No invoices</p>
                    <a href="<?php echo APP_URL; ?>/admin/invoices/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Create Invoice
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: PAYMENTS -->
        <div id="payments" class="tab-pane fade" role="tabpanel">
            <?php
            $stmt = $db->prepare("
                SELECT p.*, i.invoice_number 
                FROM payments p
                LEFT JOIN invoices i ON p.invoice_id = i.id
                WHERE p.client_id = :id
                ORDER BY p.payment_date DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $payments = $stmt->fetchAll();

            if (count($payments) > 0):
                $total_payments = array_sum(array_column($payments, 'amount'));
            ?>
                <div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
                    <strong>Total Payments Received: <?php echo formatCurrency($total_payments); ?></strong>
                    <br><small style="color: #999;">Last Payment: <?php echo formatDate($payments[0]['payment_date']); ?></small>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Payment #</th>
                            <th>Invoice</th>
                            <th>Date</th>
                            <th>Amount</th>
                            <th>Method</th>
                            <th>Reference ID</th>
                            <th>Notes</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $pay): ?>
                            <tr>
                                <td><strong><?php echo clean($pay['payment_number']); ?></strong></td>
                                <td>
                                    <a href="<?php echo APP_URL; ?>/admin/invoices/view.php?id=<?php echo $pay['invoice_id']; ?>" style="color: #6418C3; text-decoration: none;">
                                        <?php echo clean($pay['invoice_number']); ?>
                                    </a>
                                </td>
                                <td><?php echo formatDate($pay['payment_date']); ?></td>
                                <td style="font-weight: 600; color: #2BC155;"><?php echo formatCurrency($pay['amount']); ?></td>
                                <td>
                                    <span style="display: inline-block; background: #f0f0f0; padding: 3px 8px; border-radius: 3px; font-size: 0.8rem;">
                                        <?php
                                        $methods = [
                                            'cash' => '💵 Cash',
                                            'check' => '🏧 Check',
                                            'bank_transfer' => '🏦 Bank Transfer',
                                            'credit_card' => '💳 Card',
                                            'online' => '🌐 Online'
                                        ];
                                        echo $methods[$pay['payment_method']] ?? ucfirst(str_replace('_', ' ', $pay['payment_method']));
                                        ?>
                                    </span>
                                </td>
                                <td><small><?php echo clean($pay['transaction_id'] ?? '-'); ?></small></td>
                                <td><small><?php echo clean($pay['notes'] ?? '-'); ?></small></td>
                                <td>
                                    <?php if ($pay['receipt_file']): ?>
                                        <a href="#" class="btn btn-sm btn-outline-primary"><i class="fas fa-download"></i></a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No payments received</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: QUOTATIONS -->
        <div id="quotations" class="tab-pane fade" role="tabpanel">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM quotations
                WHERE client_id = :id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $quotations = $stmt->fetchAll();

            if (count($quotations) > 0):
                // Calculate statistics
                $quo_total = array_sum(array_column($quotations, 'total'));
                $quo_accepted = count(array_filter($quotations, fn($q) => $q['status'] === 'accepted'));
                $quo_pending = count(array_filter($quotations, fn($q) => $q['status'] === 'pending'));
            ?>
                <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
                    <div style="background: linear-gradient(135deg, rgba(100,24,195,0.1), rgba(155,89,182,0.1)); border-left: 3px solid #6418C3; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Total Value</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #6418C3;"><?php echo formatCurrency($quo_total); ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Accepted</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #2BC155;"><?php echo $quo_accepted; ?></div>
                    </div>
                    <div style="background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,152,0,0.1)); border-left: 3px solid #FFD700; padding: 15px; border-radius: 8px;">
                        <small style="color: #666; font-weight: 600;">Pending</small>
                        <div style="font-size: 1.5rem; font-weight: 700; color: #FFD700;"><?php echo $quo_pending; ?></div>
                    </div>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Quotation #</th>
                            <th>Date</th>
                            <th>Valid Until</th>
                            <th>Amount</th>
                            <th>Status</th>
                            <th>Days Valid</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($quotations as $quo):
                            $daysValid = ($quo['valid_until'] ? (strtotime($quo['valid_until']) - time()) / (24 * 60 * 60) : 0);
                        ?>
                            <tr>
                                <td><strong><?php echo clean($quo['quotation_number']); ?></strong></td>
                                <td><?php echo formatDate($quo['issue_date']); ?></td>
                                <td><?php echo formatDate($quo['valid_until']) ?: '-'; ?></td>
                                <td><?php echo formatCurrency($quo['total']); ?></td>
                                <td><?php echo statusBadge($quo['status']); ?></td>
                                <td>
                                    <?php if ($daysValid > 0): ?>
                                        <span style="color: <?php echo $daysValid < 7 ? '#FF9B52' : '#2BC155'; ?>; font-weight: 600;">
                                            <?php echo round($daysValid); ?> days
                                        </span>
                                    <?php else: ?>
                                        <span style="color: #999;"><em>Expired</em></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-primary" style="cursor: pointer;"><i class="fas fa-eye"></i></button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No quotations</p>
                    <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo $clientId; ?>" class="btn btn-primary" style="margin-top: 15px;">
                        <i class="fas fa-plus"></i> Create Quotation
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: MEETINGS -->
        <div id="meetings" class="tab-pane fade" role="tabpanel">
            <button class="btn btn-primary" style="margin-bottom: 20px; background: #6418C3; border: none;">
                <i class="fas fa-plus"></i> Schedule Meeting
            </button>

            <?php
            $stmt = $db->prepare("
                SELECT m.*, u.name as scheduled_by_name
                FROM meetings m
                LEFT JOIN users u ON m.created_by = u.id
                WHERE m.client_id = :id
                ORDER BY m.meeting_date DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $meetings = $stmt->fetchAll();

            if (count($meetings) > 0):
            ?>
                <div class="timeline">
                    <?php foreach ($meetings as $mtg):
                        $isFuture = strtotime($mtg['meeting_date']) > time();
                        $daysDiff = (strtotime($mtg['meeting_date']) - time()) / (24 * 60 * 60);
                    ?>
                        <div class="timeline-item">
                            <div class="timeline-dot" style="background: <?php echo $isFuture ? '#6418C3' : '#999'; ?>;"></div>
                            <div class="timeline-content">
                                <p class="timeline-title" style="color: <?php echo $isFuture ? '#1D1D1D' : '#999'; ?>;">
                                    <?php echo clean($mtg['title']); ?>
                                    <?php if ($isFuture): ?>
                                        <span style="display: inline-block; background: #6418C3; color: white; padding: 2px 8px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; margin-left: 10px;">
                                            UPCOMING (<?php echo round($daysDiff); ?> days)
                                        </span>
                                    <?php endif; ?>
                                </p>
                                <p class="timeline-description">
                                    <i class="fas fa-calendar"></i> <?php echo formatDate($mtg['meeting_date']); ?>
                                    <?php if ($mtg['meeting_time']): ?>
                                        @ <?php echo date('H:i', strtotime($mtg['meeting_time'])); ?>
                                    <?php endif; ?>
                                    <?php if ($mtg['location']): ?>
                                        <br><i class="fas fa-map-marker-alt"></i> <?php echo clean($mtg['location']); ?>
                                    <?php endif; ?>
                                </p>
                                <?php if ($mtg['description']): ?>
                                    <p style="font-size: 0.85rem; color: #666; margin: 8px 0; padding: 8px; background: #f9f9f9; border-radius: 4px;">
                                        <?php echo clean($mtg['description']); ?>
                                    </p>
                                <?php endif; ?>
                                <small style="color: #bbb;">
                                    Scheduled by <?php echo clean($mtg['scheduled_by_name'] ?? 'Unknown'); ?> on <?php echo formatDate($mtg['created_at']); ?>
                                </small>
                                <div style="margin-top: 8px; display: flex; gap: 5px;">
                                    <button class="btn btn-sm btn-info" style="cursor: pointer; font-size: 0.75rem;">
                                        <i class="fas fa-edit"></i> Edit
                                    </button>
                                    <button class="btn btn-sm btn-warning" style="cursor: pointer; font-size: 0.75rem;">
                                        <i class="fas fa-comments"></i> Add Notes
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No meetings scheduled</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- TAB: DOCUMENTS -->
        <div id="documents" class="tab-pane fade" role="tabpanel">
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal" style="margin-bottom: 20px; background: #6418C3; border: none; font-weight: 600;">
                <i class="fas fa-upload"></i> Upload Document
            </button>

            <?php
            $stmt = $db->prepare("
                SELECT d.*, u.name as uploaded_by_name 
                FROM client_documents d
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.client_id = :id
                ORDER BY d.created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $documents = $stmt->fetchAll();

            if (count($documents) > 0):
                // Group by type
                $docsByType = [];
                foreach ($documents as $doc) {
                    $type = $doc['document_type'] ?? 'other';
                    if (!isset($docsByType[$type])) {
                        $docsByType[$type] = [];
                    }
                    $docsByType[$type][] = $doc;
                }
            ?>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 25px;">
                    <?php
                    $typeIcons = [
                        'contract' => '📋',
                        'agreement' => '📝',
                        'certificate' => '🏆',
                        'invoice' => '📄',
                        'other' => '📎'
                    ];
                    foreach ($docsByType as $type => $docs):
                    ?>
                        <div style="background: white; padding: 15px; border-radius: 8px; border: 1px solid #e0e0e0; text-align: center;">
                            <div style="font-size: 2rem; margin-bottom: 10px;">
                                <?php echo $typeIcons[$type] ?? '📎'; ?>
                            </div>
                            <p style="margin: 0 0 5px 0; font-weight: 600; color: #1D1D1D;">
                                <?php echo ucfirst(str_replace('_', ' ', $type)); ?>
                            </p>
                            <p style="margin: 0; font-size: 1.5rem; font-weight: 700; color: #6418C3;">
                                <?php echo count($docs); ?>
                            </p>
                        </div>
                    <?php endforeach; ?>
                </div>

                <table class="table">
                    <thead>
                        <tr>
                            <th>Document Name</th>
                            <th>Type</th>
                            <th>Size</th>
                            <th>Uploaded</th>
                            <th>By</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($documents as $doc):
                            $fileSize = file_exists($doc['file_path']) ? filesize($doc['file_path']) : 0;
                            $fileSizeFormatted = $fileSize > 1048576 ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';
                        ?>
                            <tr>
                                <td><strong><?php echo clean($doc['document_name']); ?></strong></td>
                                <td>
                                    <span style="display: inline-block; background: #f0f0f0; padding: 4px 10px; border-radius: 3px; font-size: 0.8rem;">
                                        <?php echo ucfirst(str_replace('_', ' ', $doc['document_type'])); ?>
                                    </span>
                                </td>
                                <td><small><?php echo $fileSizeFormatted; ?></small></td>
                                <td><?php echo formatDate($doc['created_at']); ?></td>
                                <td><small><?php echo clean($doc['uploaded_by_name'] ?? 'Unknown'); ?></small></td>
                                <td>
                                    <?php if (!empty($doc['file_path']) && file_exists($doc['file_path'])): ?>
                                        <a href="<?php echo APP_URL . '/' . clean($doc['file_path']); ?>" class="btn btn-sm btn-info" download title="Download">
                                            <i class="fas fa-download"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button class="btn btn-sm btn-danger delete-doc" data-doc-id="<?php echo $doc['id']; ?>" title="Delete">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No documents uploaded yet</p>
                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#uploadDocModal" style="margin-top: 15px; background: #6418C3; border: none;">
                        <i class="fas fa-upload"></i> Upload First Document
                    </button>
                </div>
            <?php endif; ?>
        </div>
        </div>
    </div>
</div>

<!-- UPLOAD DOCUMENT MODAL -->
<div class="modal fade" id="uploadDocModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content" style="border-radius: 12px;">
            <div class="modal-header" style="background: #6418C3; color: white; border: none; border-radius: 12px 12px 0 0;">
                <h5 class="modal-title">Upload Document</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form id="uploadDocForm" enctype="multipart/form-data" style="padding: 20px;">
                <input type="hidden" name="client_id" value="<?php echo $clientId; ?>">
                <input type="hidden" name="csrf_token" value="<?php echo generateCsrf(); ?>">

                <div class="mb-3">
                    <label class="form-label">Document Name</label>
                    <input type="text" name="doc_name" class="form-control" required>
                </div>
                <div class="mb-3">
                    <label class="form-label">Document Type</label>
                    <select name="doc_type" class="form-control" required>
                        <option value="">-- Select Type --</option>
                        <option value="contract">Contract</option>
                        <option value="agreement">Agreement</option>
                        <option value="certificate">Certificate</option>
                        <option value="invoice">Invoice</option>
                        <option value="other">Other</option>
                    </select>
                </div>
                <div class="mb-3">
                    <label class="form-label">File (PDF, DOC, DOCX max 10MB)</label>
                    <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx" required>
                </div>
                <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none; width: 100%;">
                    <i class="fas fa-upload"></i> Upload Document
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    // ========================================
    // BOOTSTRAP TAB INITIALIZATION - FIXED VERSION
    // ========================================
    
    document.addEventListener('DOMContentLoaded', function() {
        console.log('🚀 TAB SYSTEM INITIALIZING...');

        // STEP 1: Hide all tab panes initially
        document.querySelectorAll('.tab-pane').forEach(pane => {
            pane.style.display = 'none';
            pane.classList.remove('show', 'active');
        });

        // STEP 2: Show first tab
        const firstPane = document.querySelector('.tab-pane');
        if (firstPane) {
            firstPane.style.display = 'block';
            firstPane.classList.add('show', 'active');
            console.log('✅ First tab pane shown');
        }

        // STEP 3: Get all tab links
        const tabLinks = document.querySelectorAll('[data-bs-toggle="tab"]');
        console.log(`📌 Found ${tabLinks.length} tabs`);

        // STEP 4: Attach click listeners to tab links
        tabLinks.forEach((link, index) => {
            // Mark first link as active
            if (index === 0) {
                link.classList.add('active');
            }

            // Add click handler
            link.addEventListener('click', function(e) {
                e.preventDefault();

                const targetId = this.getAttribute('href');
                console.log(`\n👆 TAB CLICKED: ${targetId}`);

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
                console.log(`✔️ Link activated: ${this.textContent.trim()}`);

                // Show the target pane
                const targetPane = document.querySelector(targetId);
                if (targetPane) {
                    targetPane.style.display = 'block';
                    targetPane.classList.add('show', 'active');
                    console.log(`✅ Content shown for: ${targetId}`);

                    // Re-render chart if overview tab
                    if (targetId === '#overview') {
                        setTimeout(() => {
                            if (window.renderChart) {
                                console.log('📊 Rendering chart...');
                                window.renderChart();
                            }
                        }, 100);
                    }
                } else {
                    console.error(`❌ Tab pane not found: ${targetId}`);
                }
            });
        });

        // STEP 5: Render chart on first load
        setTimeout(() => {
            if (window.renderChart) {
                console.log('📊 Initial chart render...');
                window.renderChart();
            }
        }, 300);

        console.log('✅ TAB SYSTEM READY');
    });

</script>

<script>
    // Upload document handler
    document.getElementById('uploadDocForm').addEventListener('submit', function(e) {
        e.preventDefault();

        const formData = new FormData(this);

        fetch('/EcomZone-CMS/admin/clients/handle-documents.php', {
                method: 'POST',
                body: formData
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    bootstrap.Modal.getInstance(document.getElementById('uploadDocModal')).hide();
                    alert('Document uploaded successfully');
                    location.reload();
                } else {
                    alert('Error: ' + (data.error || 'Upload failed'));
                }
            })
            .catch(err => {
                console.error('Upload error:', err);
                alert('Upload failed');
            });
    });

    // Delete document
    document.querySelectorAll('.delete-doc').forEach(btn => {
        btn.addEventListener('click', function() {
            if (confirm('Delete this document?')) {
                const docId = this.dataset.docId;
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('doc_id', docId);

                fetch('/EcomZone-CMS/admin/clients/handle-documents.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(r => r.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Delete failed');
                        }
                    });
            }
        });
    });
</script>

<script>


    // ========== CHART RENDERING ==========
    window.chartRendered = false;
    
    window.renderChart = function() {
        if (window.chartRendered) {
            console.log('📊 Chart already rendered, skipping...');
            return;
        }

        if (typeof ApexCharts === 'undefined') {
            console.warn('⏳ ApexCharts not ready yet, retrying in 500ms...');
            setTimeout(window.renderChart, 500);
            return;
        }

        const container = document.getElementById('clientRevenueChart');
        if (!container) {
            console.error('❌ Chart container not found');
            return;
        }

        // Check if already has chart
        if (container.querySelectorAll('svg').length > 0) {
            console.log('📊 Chart SVG already exists');
            window.chartRendered = true;
            return;
        }

        try {
            const revenue = <?php echo json_encode($chartRevenues); ?>;
            const months = <?php echo json_encode($chartMonths); ?>;

            if (!revenue || revenue.length === 0) {
                console.warn('⚠️ No revenue data available');
                container.innerHTML = '<p style="text-align:center; color:#999; padding:40px;">No revenue data available</p>';
                return;
            }

            console.log('📈 Rendering chart with data:', { months, revenue });

            const options = {
                series: [{
                    name: 'Revenue',
                    data: revenue
                }],
                chart: {
                    type: 'area',
                    height: 300,
                    toolbar: {
                        show: true
                    },
                    animations: {
                        enabled: true,
                        speed: 800,
                        animateGradually: {
                            enabled: true,
                            delay: 150
                        },
                        dynamicAnimation: {
                            enabled: true,
                            speed: 150
                        }
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
                    type: 'category',
                    labels: {
                        show: true
                    }
                },
                yaxis: {
                    labels: {
                        formatter: function(val) {
                            return '₨' + Math.round(val);
                        },
                        show: true
                    }
                },
                tooltip: {
                    enabled: true,
                    theme: 'light',
                    y: {
                        formatter: function(val) {
                            return '₨' + (val ? val.toFixed(0) : '0');
                        }
                    }
                },
                grid: {
                    borderColor: '#f0f0f0',
                    show: true,
                    padding: {
                        top: 10,
                        right: 30,
                        bottom: 10,
                        left: 60
                    }
                },
                responsive: [{
                    breakpoint: 1024,
                    options: {
                        chart: {
                            height: 300
                        }
                    }
                }]
            };

            const chart = new ApexCharts(container, options);
            chart.render();
            window.chartRendered = true;
            console.log('✅ Chart rendered successfully!');
        } catch (err) {
            console.error('❌ Chart error:', err);
            window.chartRendered = false;
        }
    };

    // Try to render chart when ApexCharts loads
    const chartCheckInterval = setInterval(() => {
        if (typeof ApexCharts !== 'undefined' && !window.chartRendered) {
            clearInterval(chartCheckInterval);
            setTimeout(window.renderChart, 100);
        }
    }, 100);

    // Also try on page load
    window.addEventListener('load', () => {
        setTimeout(window.renderChart, 200);
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>