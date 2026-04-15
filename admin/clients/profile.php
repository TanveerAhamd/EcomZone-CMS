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

include __DIR__ . '/../../includes/header.php';
?>

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
        background: rgba(255,255,255,0.2);
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
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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
        auto-flow: row;
        gap: 15px;
    }

    .stat-box {
        background: linear-gradient(135deg, rgba(100,24,195,0.1), rgba(30,170,231,0.1));
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
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
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
        color: #6418C3;
        border-bottom-color: #6418C3;
        background: #f8f5ff;
    }

    .tab-content {
        padding: 25px;
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.9rem;
    }

    .table thead {
        background: #f8f9ff;
        color: #6418C3;
        text-transform: uppercase;
        font-size: 0.78rem;
        font-weight: 600;
    }

    .table th, .table td {
        padding: 12px;
        text-align: left;
        border-bottom: 1px solid #f0f0f0;
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
    }

    .empty-state-icon {
        font-size: 3rem;
        margin-bottom: 15px;
        opacity: 0.5;
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
                    <p class="stat-box-number"><?php echo $projectCount; ?></p>
                    <p class="stat-box-label">Projects</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo $invoiceCount; ?></p>
                    <p class="stat-box-label">Invoices</p>
                </div>
                <div class="stat-box">
                    <p class="stat-box-number"><?php echo formatCurrency($balance); ?></p>
                    <p class="stat-box-label">Balance</p>
                </div>
            </div>
        </div>

        <!-- Assigned Services -->
        <div class="sidebar-card">
            <h5 class="sidebar-card-title"><i class="fas fa-cogs"></i> Active Services</h5>
            <?php
            $stmt = $db->prepare("
                SELECT cs.*, s.service_name, s.category
                FROM client_services cs
                JOIN services s ON cs.service_id = s.id
                WHERE cs.client_id = :id AND cs.status = 'active'
                ORDER BY cs.expiry_date ASC
            ");
            $stmt->execute([':id' => $clientId]);
            $activeServices = $stmt->fetchAll();

            if (count($activeServices) > 0):
                foreach ($activeServices as $svc):
                    $daysLeft = (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60);
                    $badgeClass = $daysLeft < 7 ? 'danger' : ($daysLeft < 30 ? 'warning' : 'success');
                ?>
                <div style="margin-bottom: 10px; padding: 10px; background: #f9f9f9; border-radius: 6px; font-size: 0.85rem;">
                    <p style="margin: 0 0 5px 0; font-weight: 600; color: #1D1D1D;">
                        <?php echo clean($svc['service_name']); ?>
                        <span class="badge bg-<?php echo $badgeClass; ?>" style="font-size: 0.7rem;">
                            <?php echo round($daysLeft); ?> days
                        </span>
                    </p>
                    <p style="margin: 0; color: #999; font-size: 0.8rem;">
                        Expires: <?php echo formatDate($svc['expiry_date']); ?>
                    </p>
                </div>
                <?php endforeach;
            else: ?>
                <p style="color: #999; text-align: center; margin: 20px 0;">No active services</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- RIGHT TABS -->
    <div class="tabs">
        <!-- TAB NAVIGATION -->
        <ul class="nav-tabs" role="tablist">
            <li role="presentation">
                <button class="nav-link active" data-tab="overview"><i class="fas fa-chart-line"></i> Overview</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="projects"><i class="fas fa-tasks"></i> Projects</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="services"><i class="fas fa-cogs"></i> Services</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="invoices"><i class="fas fa-receipt"></i> Invoices</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="payments"><i class="fas fa-credit-card"></i> Payments</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="quotations"><i class="fas fa-quote-left"></i> Quotations</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="meetings"><i class="fas fa-calendar"></i> Meetings</button>
            </li>
            <li role="presentation">
                <button class="nav-link" data-tab="documents"><i class="fas fa-file"></i> Documents</button>
            </li>
        </ul>

        <!-- TAB: OVERVIEW -->
        <div id="overview" class="tab-content active">
            <h5 style="margin-bottom: 20px;"><i class="fas fa-chart-pie"></i> Revenue Chart (Last 6 months)</h5>
            <div style="height: 300px; background: #f9f9f9; border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                <canvas id="clientRevenueChart"></canvas>
            </div>

            <h5 style="margin-top: 30px; margin-bottom: 20px;"><i class="fas fa-history"></i> Recent Activity</h5>
            <div class="timeline">
                <?php
                $stmt = $db->prepare("
                    SELECT * FROM activity_logs
                    WHERE module = 'clients' OR record_id IN (
                        SELECT id FROM projects WHERE client_id = :id
                    ) OR record_id IN (
                        SELECT id FROM invoices WHERE client_id = :id
                    )
                    ORDER BY created_at DESC
                    LIMIT 8
                ");
                $stmt->execute([':id' => $clientId]);
                $activities = $stmt->fetchAll();

                if (count($activities) > 0):
                    foreach ($activities as $activity):
                ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <p class="timeline-title"><?php echo ucfirst($activity['action']); ?></p>
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
        <div id="projects" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT p.*, s.service_name 
                FROM projects p
                LEFT JOIN services s ON p.service_id = s.id
                WHERE p.client_id = :id
                ORDER BY p.created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $projects = $stmt->fetchAll();

            if (count($projects) > 0):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Project Name</th>
                        <th>Service</th>
                        <th>Deadline</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $project): ?>
                    <tr>
                        <td><strong><?php echo clean($project['project_name']); ?></strong></td>
                        <td><?php echo clean($project['service_name'] ?? '-'); ?></td>
                        <td><?php echo formatDate($project['deadline']) ?: '-'; ?></td>
                        <td>
                            <div class="progress" style="height: 6px;">
                                <div class="progress-bar" style="width: <?php echo $project['progress']; ?>%; background: #6418C3;"></div>
                            </div>
                        </td>
                        <td><?php echo statusBadge($project['status']); ?></td>
                        <td>
                            <a href="<?php echo APP_URL; ?>/admin/projects/view.php?id=<?php echo $project['id']; ?>" class="btn btn-sm btn-primary">
                                <i class="fas fa-arrow-right"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No projects yet</p>
                <a href="<?php echo APP_URL; ?>/admin/projects/add.php?client_id=<?php echo $clientId; ?>" class="btn btn-primary" style="margin-top: 15px;">
                    <i class="fas fa-plus"></i> Create Project
                </a>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: SERVICES -->
        <div id="services" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT cs.*, s.service_name, s.category, s.price as service_price
                FROM client_services cs
                JOIN services s ON cs.service_id = s.id
                WHERE cs.client_id = :id
                ORDER BY cs.expiry_date ASC
            ");
            $stmt->execute([':id' => $clientId]);
            $services = $stmt->fetchAll();

            if (count($services) > 0):
                foreach ($services as $svc):
                    $daysLeft = (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60);
                    $badgeClass = $daysLeft < 7 ? 'danger' : ($daysLeft < 30 ? 'warning' : 'success');
                ?>
                <div class="service-card">
                    <div class="service-header">
                        <div>
                            <p class="service-name"><i class="fas fa-cog"></i> <?php echo clean($svc['service_name']); ?></p>
                            <span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $svc['category'])); ?></span>
                        </div>
                        <span class="badge bg-<?php echo $badgeClass; ?>">
                            <?php echo $daysLeft > 0 ? round($daysLeft) . ' days left' : 'EXPIRED'; ?>
                        </span>
                    </div>
                    <div class="service-dates">
                        <strong>Started:</strong> <?php echo formatDate($svc['start_date']); ?> &nbsp;&nbsp;
                        <strong>Expires:</strong> <?php echo formatDate($svc['expiry_date']); ?>
                    </div>
                    <div class="service-price">
                        <?php echo formatCurrency($svc['price'] ?: $svc['service_price']); ?>
                    </div>
                    <div style="margin-top: 10px; display: flex; gap: 5px;">
                        <button class="btn btn-sm btn-warning" style="cursor: pointer;">
                            <i class="fas fa-sync-alt"></i> Renew
                        </button>
                        <button class="btn btn-sm btn-success" style="cursor: pointer;">
                            <i class="fab fa-whatsapp"></i> Alert
                        </button>
                    </div>
                </div>
                <?php endforeach;
            else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No services assigned</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: INVOICES -->
        <div id="invoices" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM invoices
                WHERE client_id = :id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $invoices = $stmt->fetchAll();

            if (count($invoices) > 0):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Invoice #</th>
                        <th>Date</th>
                        <th>Due Date</th>
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
                        <td><?php echo formatDate($inv['issue_date']); ?></td>
                        <td><?php echo formatDate($inv['due_date']) ?: '-'; ?></td>
                        <td><?php echo formatCurrency($inv['total']); ?></td>
                        <td><?php echo formatCurrency($inv['paid_amount']); ?></td>
                        <td><strong><?php echo formatCurrency($inv['balance']); ?></strong></td>
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
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: PAYMENTS -->
        <div id="payments" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM payments
                WHERE client_id = :id
                ORDER BY payment_date DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $payments = $stmt->fetchAll();

            if (count($payments) > 0):
            ?>
            <div class="stat-box" style="margin-bottom: 20px; text-align: left; border-left: 3px solid #2BC155; background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); padding: 20px;">
                <strong>Total Paid: <?php echo formatCurrency(array_sum(array_column($payments, 'amount'))); ?></strong>
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
                        <th>Transaction ID</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($payments as $pay): ?>
                    <tr>
                        <td><?php echo clean($pay['payment_number']); ?></td>
                        <td>
                            <?php
                            $stmt2 = $db->prepare("SELECT invoice_number FROM invoices WHERE id = :id");
                            $stmt2->execute([':id' => $pay['invoice_id']]);
                            $inv = $stmt2->fetch();
                            echo clean($inv['invoice_number'] ?? '-');
                            ?>
                        </td>
                        <td><?php echo formatDate($pay['payment_date']); ?></td>
                        <td><?php echo formatCurrency($pay['amount']); ?></td>
                        <td><small><?php echo ucfirst($pay['payment_method']); ?></small></td>
                        <td><small><?php echo clean($pay['transaction_id'] ?? '-'); ?></small></td>
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
        <div id="quotations" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM quotations
                WHERE client_id = :id
                ORDER BY created_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $quotations = $stmt->fetchAll();

            if (count($quotations) > 0):
            ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Quotation #</th>
                        <th>Date</th>
                        <th>Valid Until</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($quotations as $quo): ?>
                    <tr>
                        <td><?php echo clean($quo['quotation_number']); ?></td>
                        <td><?php echo formatDate($quo['issue_date']); ?></td>
                        <td><?php echo formatDate($quo['valid_until']) ?: '-'; ?></td>
                        <td><?php echo formatCurrency($quo['total']); ?></td>
                        <td><?php echo statusBadge($quo['status']); ?></td>
                        <td><button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No quotations</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: MEETINGS -->
        <div id="meetings" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM meetings
                WHERE client_id = :id
                ORDER BY meeting_date DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $meetings = $stmt->fetchAll();

            if (count($meetings) > 0):
            ?>
            <div class="timeline">
                <?php foreach ($meetings as $mtg): ?>
                <div class="timeline-item">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <p class="timeline-title"><?php echo clean($mtg['title']); ?></p>
                        <p class="timeline-description">
                            <i class="fas fa-calendar"></i> <?php echo formatDate($mtg['meeting_date']); ?>
                            <?php if ($mtg['meeting_time']): ?>
                            @ <?php echo $mtg['meeting_time']; ?>
                            <?php endif; ?>
                        </p>
                        <button class="btn btn-sm btn-primary" style="margin-top: 8px;">View Notes</button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No meetings</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- TAB: DOCUMENTS -->
        <div id="documents" class="tab-content">
            <?php
            $stmt = $db->prepare("
                SELECT * FROM client_documents
                WHERE client_id = :id
                ORDER BY uploaded_at DESC
            ");
            $stmt->execute([':id' => $clientId]);
            $documents = $stmt->fetchAll();
            
            if (count($documents) > 0):
            ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(150px, 1fr)); gap: 15px;">
                <?php foreach ($documents as $doc): ?>
                <div style="border: 1px solid #e0e0e0; border-radius: 8px; padding: 20px; text-align: center;">
                    <i class="fas fa-file" style="font-size: 2.5rem; color: #6418C3; margin-bottom: 10px; display: block;"></i>
                    <p style="margin: 0 0 8px 0; font-size: 0.85rem; color: #1D1D1D;">
                        <strong><?php echo ucfirst(str_replace('_', ' ', $doc['doc_type'])); ?></strong>
                    </p>
                    <small style="color: #999; display: block; margin-bottom: 10px;">
                        <?php echo timeAgo($doc['uploaded_at']); ?>
                    </small>
                    <button class="btn btn-sm btn-primary" style="width: 100%;">
                        <i class="fas fa-download"></i> Download
                    </button>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-inbox empty-state-icon"></i>
                <p>No documents uploaded</p>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
    // Tab switching
    document.querySelectorAll('.nav-link').forEach(btn => {
        btn.addEventListener('click', function() {
            const tabName = this.dataset.tab;
            
            document.querySelectorAll('.nav-link').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(t => t.classList.remove('active'));
            
            this.classList.add('active');
            document.getElementById(tabName).classList.add('active');
        });
    });

    // Revenue chart for this client
    const clientRevenueChart = new ApexCharts(document.querySelector("#clientRevenueChart"), {
        series: [{
            name: 'Revenue',
            data: [0, 15000, 25000, 18000, 35000, 42000]
        }],
        chart: {
            type: 'area',
            stacked: false,
            height: 300,
            sparkline: { enabled: false }
        },
        colors: ['#6418C3'],
        dataLabels: { enabled: false },
        stroke: { curve: 'smooth', width: 2 },
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
            categories: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun']
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return CURRENCY_SYMBOL + val.toLocaleString();
                }
            }
        }
    });