<?php
/**
 * DASHBOARD PAGE
 * Main overview with stat cards, charts, alerts and activities
 */

require_once __DIR__ . '/../includes/init.php';

requireLogin();

$pageTitle = 'Dashboard';

// Get dashboard statistics
$stats = getDashboardStats();
$user = currentUser();

// Get financial data for charts
global $db;

// Revenue data for last 6 months
$stmt = $db->prepare("
    SELECT 
        DATE_FORMAT(created_at, '%b %Y') as month,
        SUM(paid_amount) as revenue
    FROM invoices
    WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
    AND paid_amount > 0
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
");
$stmt->execute();
$revenueData = $stmt->fetchAll();

// Project status data - Fixed
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM projects
    GROUP BY status
    ORDER BY FIELD(status, 'in_progress', 'pending', 'completed', 'on_hold')
");
$stmt->execute();
$projectStatus = $stmt->fetchAll();

// Client status data
$stmt = $db->prepare("
    SELECT client_status, COUNT(*) as count
    FROM clients
    GROUP BY client_status
    ORDER BY FIELD(client_status, 'active', 'inactive', 'suspended')
");
$stmt->execute();
$clientStatus = $stmt->fetchAll();

// Service status data
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM project_services
    GROUP BY status
    ORDER BY FIELD(status, 'active', 'expired', 'cancelled')
");
$stmt->execute();
$serviceStatus = $stmt->fetchAll();

// Expiring services
$expiringServices = checkExpiringServices();

// Recent activity
$stmt = $db->prepare("
    SELECT * FROM activity_logs
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute();
$recentActivities = $stmt->fetchAll();

include __DIR__ . '/../includes/header.php';
?>

<style>
    .stat-card {
        border-radius: 12px;
        padding: 24px;
        color: white;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
        min-height: 140px;
        border: none;
    }

    .stat-card::before {
        content: '';
        position: absolute;
        right: -20px;
        top: -20px;
        font-size: 3rem;
        opacity: 0.15;
    }

    .stat-card-gradient-1 {
        background: linear-gradient(135deg, #6418C3, #9B59B6);
    }

    .stat-card-gradient-2 {
        background: linear-gradient(135deg, #1EAAE7, #3498DB);
    }

    .stat-card-gradient-3 {
        background: linear-gradient(135deg, #FF9B52, #E74C3C);
    }

    .stat-card-gradient-4 {
        background: linear-gradient(135deg, #2BC155, #27AE60);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        margin-bottom: 8px;
    }

    .stat-label {
        font-size: 0.9rem;
        opacity: 0.95;
        margin-bottom: 10px;
    }

    .stat-trend {
        display: inline-block;
        padding: 4px 10px;
        background: rgba(255,255,255,0.2);
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .stat-trend.positive {
        background: rgba(43, 193, 85, 0.3);
        color: #fff;
    }

    .stat-trend.negative {
        background: rgba(255, 94, 94, 0.3);
        color: #fff;
    }

    .card {
        border: none;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.06);
        margin-bottom: 25px;
    }

    .card-header {
        background: white;
        border-bottom: 1px solid #f4f4f4;
        border-radius: 12px 12px 0 0;
        padding: 20px 24px;
        font-weight: 600;
        color: var(--dark);
    }

    .card-body {
        padding: 24px;
    }

    .chart-container {
        position: relative;
        width: 100%;
        margin-bottom: 20px;
    }

    #revenueChart {
        width: 100% !important;
    }

    #projectStatusChart,
    #clientStatusChart,
    #serviceStatusChart {
        width: 100% !important;
    }

    .activity-timeline {
        max-height: 350px;
        overflow-y: auto;
    }

    .timeline-item {
        display: flex;
        gap: 15px;
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .timeline-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .timeline-dot {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        margin-top: 5px;
        flex-shrink: 0;
    }

    .timeline-dot.info {
        background: #1EAAE7;
    }

    .timeline-dot.success {
        background: #2BC155;
    }

    .timeline-dot.warning {
        background: #FF9B52;
    }

    .timeline-dot.danger {
        background: #FF5E5E;
    }

    .timeline-content {
        flex: 1;
    }

    .timeline-title {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1D1D1D;
        margin: 0 0 3px 0;
    }

    .timeline-description {
        font-size: 0.85rem;
        color: #888;
        margin: 0;
    }

    .timeline-time {
        font-size: 0.75rem;
        color: #bbb;
    }

    table {
        font-size: 0.9rem;
    }

    thead {
        background: #f8f9ff;
        color: #6418C3;
        text-transform: uppercase;
        font-size: 0.78rem;
        font-weight: 600;
        letter-spacing: 0.5px;
    }

    tbody tr:hover {
        background: #f8f9ff;
    }

    .expiry-badge {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .expiry-badge.critical {
        background: rgba(255, 94, 94, 0.15);
        color: #FF5E5E;
    }

    .expiry-badge.warning {
        background: rgba(255, 155, 82, 0.15);
        color: #FF9B52;
    }

    .expiry-badge.safe {
        background: rgba(43, 193, 85, 0.15);
        color: #2BC155;
    }

    .btn-action {
        padding: 6px 12px;
        font-size: 0.85rem;
        border-radius: 6px;
        margin-right: 5px;
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
</style>

<!-- STAT CARDS ROW 1 -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-1">
            <div class="stat-label">Total Clients</div>
            <div class="stat-number"><?php echo $stats['total_clients']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-users"></i> All Clients
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-2">
            <div class="stat-label">Active Clients</div>
            <div class="stat-number"><?php echo $stats['active_clients']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-check-circle"></i> Active Status
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #1EAAE7, #3498DB);">
            <div class="stat-label">Total Projects</div>
            <div class="stat-number"><?php echo $stats['total_projects']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-project-diagram"></i> All Time
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-2">
            <div class="stat-label">Active Projects</div>
            <div class="stat-number"><?php echo $stats['active_projects']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-hourglass-start"></i> In Progress
            </span>
        </div>
    </div>
</div>

<!-- STAT CARDS ROW 2 -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-3">
            <div class="stat-label">Active Services</div>
            <div class="stat-number"><?php echo $stats['active_services']; ?></div>
            <span class="stat-trend <?php echo $stats['expiring_services'] > 0 ? 'negative' : 'positive'; ?>">
                <i class="fas fa-exclamation-circle"></i> <?php echo $stats['expiring_services']; ?> Expiring
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #FF5E5E, #E74C3C);">
            <div class="stat-label">Expired Services</div>
            <div class="stat-number"><?php echo $stats['expired_services']; ?></div>
            <span class="stat-trend negative">
                <i class="fas fa-times-circle"></i> Need Renewal
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-4">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number"><?php echo formatCurrency($stats['total_revenue']); ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-check-circle"></i> Paid
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card" style="background: linear-gradient(135deg, #FF9B52, #E67E22);">
            <div class="stat-label">Pending Revenue</div>
            <div class="stat-number"><?php echo formatCurrency($stats['pending_revenue']); ?></div>
            <span class="stat-trend negative">
                <i class="fas fa-clock"></i> <?php echo $stats['unpaid_invoices']; ?> Unpaid
            </span>
        </div>
    </div>
</div>

</div>

<!-- REVENUE CHART ROW - FIRST AND FULL WIDTH -->
<div class="row mb-4">
    <div class="col-lg-12">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-line"></i> Revenue Overview (Last 6 months)
            </div>
            <div class="card-body">
                <div id="revenueChart" style="min-height: 400px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- STATUS BREAKDOWN CHARTS ROW -->
<div class="row mb-4">
    <!-- Projects by Status -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-project-diagram"></i> Projects by Status
            </div>
            <div class="card-body">
                <div id="projectStatusChart" style="min-height: 320px;"></div>
            </div>
        </div>
    </div>

    <!-- Clients by Status -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-users"></i> Clients by Status
            </div>
            <div class="card-body">
                <div id="clientStatusChart" style="min-height: 320px;"></div>
            </div>
        </div>
    </div>

    <!-- Services by Status -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-cogs"></i> Services by Status
            </div>
            <div class="card-body">
                <div id="serviceStatusChart" style="min-height: 320px;"></div>
            </div>
        </div>
    </div>
</div>

<!-- ALERTS & ACTIVITIES ROW -->
<div class="row mb-4">
    <!-- Service Expiry Alerts Table -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-exclamation-triangle" style="color: #FF9B52;"></i> 
                Service Expiry Alerts (Next 30 Days)
                <a href="<?php echo APP_URL; ?>/admin/alerts/index.php" class="float-end text-decoration-none" style="font-size: 0.85rem;">View all →</a>
            </div>
            <div class="card-body">
                <?php if (count($expiringServices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Project</th>
                                <th>Client</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringServices as $service): 
                                $daysLeft = (strtotime($service['expiry_date']) - time()) / (24 * 60 * 60);
                                $badgeClass = $daysLeft < 7 ? 'critical' : ($daysLeft < 30 ? 'warning' : 'safe');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($service['service_name']); ?></strong>
                                </td>
                                <td>
                                    <span style="background: #E8D7F1; color: #6418C3; padding: 4px 8px; border-radius: 4px; font-size: 0.85rem; font-weight: 600;">
                                        <?php echo htmlspecialchars($service['project_name']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($service['client_name']); ?></td>
                                <td><?php echo formatDate($service['expiry_date']); ?></td>
                                <td>
                                    <span class="expiry-badge <?php echo $badgeClass; ?>">
                                        <?php echo round($daysLeft); ?> days
                                    </span>
                                </td>
                                <td><?php echo statusBadge($service['status']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <div class="empty-state-icon"><i class="fas fa-check-circle"></i></div>
                    <p>No services expiring within the next 30 days</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Recent Activity -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-history"></i> Recent Activity
            </div>
            <div class="card-body p-0">
                <div class="activity-timeline p-3">
                    <?php if (count($recentActivities) > 0): ?>
                        <?php foreach ($recentActivities as $activity): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot info"></div>
                            <div class="timeline-content">
                                <p class="timeline-title"><?php echo ucfirst($activity['action']); ?></p>
                                <p class="timeline-description"><?php echo clean($activity['description'] ?? $activity['module']); ?></p>
                                <div class="timeline-time"><?php echo timeAgo($activity['created_at']); ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div style="text-align: center; padding: 30px 0; color: #999;">
                            <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                            No recent activity
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- RECENT DATA ROW -->
<div class="row">
    <!-- Recent Invoices -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-receipt"></i> Recent Invoices
                <a href="<?php echo APP_URL; ?>/admin/invoices/index.php" class="float-end text-decoration-none" style="font-size: 0.85rem;">View all →</a>
            </div>
            <div class="card-body p-0">
                <?php
                $stmt = $db->prepare("
                    SELECT i.*, c.client_name FROM invoices i
                    JOIN clients c ON i.client_id = c.id
                    ORDER BY i.created_at DESC
                    LIMIT 5
                ");
                $stmt->execute();
                $recentInvoices = $stmt->fetchAll();
                
                if (count($recentInvoices) > 0):
                ?>
                <table class="table table-hover" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Invoice</th>
                            <th>Client</th>
                            <th>Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentInvoices as $invoice): ?>
                        <tr>
                            <td>
                                <a href="<?php echo APP_URL; ?>/admin/invoices/view.php?id=<?php echo $invoice['id']; ?>" class="text-decoration-none">
                                    <?php echo clean($invoice['invoice_number']); ?>
                                </a>
                            </td>
                            <td><?php echo clean($invoice['client_name']); ?></td>
                            <td><?php echo formatCurrency($invoice['total']); ?></td>
                            <td><?php echo statusBadge($invoice['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class="fas fa-inbox empty-state-icon"></i>
                    <p>No invoices yet</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- My Tasks -->
    <div class="col-lg-6">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-tasks"></i> My Tasks
                <a href="<?php echo APP_URL; ?>/admin/todos/index.php" class="float-end text-decoration-none" style="font-size: 0.85rem;">View all →</a>
            </div>
            <div class="card-body p-0">
                <?php
                $stmt = $db->prepare("
                    SELECT * FROM todos
                    WHERE user_id = :user_id
                    AND status != 'done'
                    ORDER BY priority DESC, due_date ASC
                    LIMIT 5
                ");
                $stmt->execute([':user_id' => $user['id']]);
                $myTasks = $stmt->fetchAll();
                
                if (count($myTasks) > 0):
                ?>
                <table class="table table-hover" style="margin-bottom: 0;">
                    <thead>
                        <tr>
                            <th>Task</th>
                            <th>Priority</th>
                            <th>Due Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($myTasks as $task): ?>
                        <tr>
                            <td><?php echo clean(substr($task['title'], 0, 25)); ?></td>
                            <td><?php echo priorityBadge($task['priority']); ?></td>
                            <td><?php echo formatDate($task['due_date']) ?: '-'; ?></td>
                            <td><?php echo statusBadge($task['status']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <?php else: ?>
                <div class="empty-state" style="padding: 30px;">
                    <i class="fas fa-check-circle empty-state-icon"></i>
                    <p>No pending tasks</p>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ApexCharts CDN -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.45.0/dist/apexcharts.min.js"></script>

<script>
// Wait for ApexCharts to load
var chartsReady = false;
var maxRetries = 10;
var retryCount = 0;

function waitForApexCharts() {
    if (typeof ApexCharts !== 'undefined') {
        chartsReady = true;
        initCharts();
    } else if (retryCount < maxRetries) {
        retryCount++;
        setTimeout(waitForApexCharts, 100);
    } else {
        console.error('ApexCharts failed to load');
    }
}

// Prepare data from PHP
<?php
$months = [];
$revenues = [];
foreach ($revenueData as $data) {
    $months[] = $data['month'];
    $revenues[] = floatval($data['revenue']);
}

$projectLabels = [];
$projectData = [];
foreach ($projectStatus as $data) {
    $projectLabels[] = ucfirst($data['status']);
    $projectData[] = intval($data['count']);
}

$clientLabels = [];
$clientData = [];
foreach ($clientStatus as $data) {
    $clientLabels[] = ucfirst($data['client_status']);
    $clientData[] = intval($data['count']);
}

$serviceLabels = [];
$serviceData = [];
foreach ($serviceStatus as $data) {
    $serviceLabels[] = ucfirst($data['status']);
    $serviceData[] = intval($data['count']);
}
?>

// Chart Data
var chartData = {
    months: <?php echo json_encode($months); ?>,
    revenues: <?php echo json_encode($revenues); ?>,
    projectLabels: <?php echo json_encode($projectLabels); ?>,
    projectData: <?php echo json_encode($projectData); ?>,
    clientLabels: <?php echo json_encode($clientLabels); ?>,
    clientData: <?php echo json_encode($clientData); ?>,
    serviceLabels: <?php echo json_encode($serviceLabels); ?>,
    serviceData: <?php echo json_encode($serviceData); ?>
};

console.log('Chart Data:', chartData);

// Initialize charts
function initCharts() {
    console.log('Initializing charts...');
    
    // Check if container elements exist
    var revenueContainer = document.querySelector("#revenueChart");
    var projectContainer = document.querySelector("#projectStatusChart");
    var clientContainer = document.querySelector("#clientStatusChart");
    var serviceContainer = document.querySelector("#serviceStatusChart");
    
    if (!revenueContainer || !projectContainer || !clientContainer || !serviceContainer) {
        console.error('One or more chart containers not found');
        return;
    }
    
    console.log('All chart containers found');

    // 1. REVENUE OVERVIEW - Area Chart (MAIN CHART)
    try {
        var revenueChart = new ApexCharts(revenueContainer, {
            series: [{
                name: 'Revenue (PKR)',
                data: chartData.revenues
            }],
            chart: {
                type: 'area',
                height: 380,
                fontFamily: 'Inter, sans-serif',
                sparkline: { enabled: false },
                toolbar: {
                    show: true,
                    tools: {
                        download: true,
                        selection: true,
                        zoom: true,
                        zoomin: true,
                        zoomout: true,
                        pan: true,
                        reset: true
                    }
                },
                animations: {
                    enabled: true,
                    speed: 800
                }
            },
            colors: ['#6418C3'],
            stroke: {
                curve: 'smooth',
                width: 3,
                lineCap: 'round'
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
            dataLabels: { enabled: false },
            xaxis: {
                categories: chartData.months,
                title: { text: 'Month', style: { fontSize: '12px', fontWeight: 600 } },
                axisBorder: { show: true, color: '#f0f0f0' },
                axisTicks: { show: true, color: '#f0f0f0' }
            },
            yaxis: {
                title: { text: 'Revenue (PKR)', style: { fontSize: '12px', fontWeight: 600 } },
                labels: {
                    formatter: function(val) {
                        return '₨' + (val >= 1000 ? (val/1000).toFixed(0) + 'K' : val.toFixed(0));
                    }
                }
            },
            tooltip: {
                theme: 'light',
                style: { fontSize: '12px' },
                y: {
                    formatter: function(val) {
                        return '₨' + val.toLocaleString('en-PK', {minimumFractionDigits: 0, maximumFractionDigits: 0});
                    }
                }
            },
            grid: {
                borderColor: '#f1f1f1',
                padding: { left: 0, right: 0 }
            },
            responsive: [{
                breakpoint: 1024,
                options: { chart: { height: 300 } }
            }]
        });
        revenueChart.render();
        console.log('Revenue chart rendered');
    } catch(e) {
        console.error('Revenue chart error:', e);
    }

    // 2. PROJECTS BY STATUS - Donut Chart
    try {
        var projectChart = new ApexCharts(projectContainer, {
            series: chartData.projectData,
            labels: chartData.projectLabels,
            chart: {
                type: 'donut',
                height: 300,
                fontFamily: 'Inter, sans-serif',
                animations: { enabled: true }
            },
            colors: ['#6418C3', '#FF9B52', '#2BC155', '#1EAAE7'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '14px', fontWeight: 600 },
                            value: { show: true, fontSize: '18px', fontWeight: 700 }
                        }
                    }
                }
            },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: '600' } },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { theme: 'light' }
        });
        projectChart.render();
        console.log('Project chart rendered');
    } catch(e) {
        console.error('Project chart error:', e);
    }

    // 3. CLIENTS BY STATUS - Donut Chart
    try {
        var clientChart = new ApexCharts(clientContainer, {
            series: chartData.clientData,
            labels: chartData.clientLabels,
            chart: {
                type: 'donut',
                height: 300,
                fontFamily: 'Inter, sans-serif',
                animations: { enabled: true }
            },
            colors: ['#2BC155', '#FF5E5E', '#FFC107'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '14px', fontWeight: 600 },
                            value: { show: true, fontSize: '18px', fontWeight: 700 }
                        }
                    }
                }
            },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: '600' } },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { theme: 'light' }
        });
        clientChart.render();
        console.log('Client chart rendered');
    } catch(e) {
        console.error('Client chart error:', e);
    }

    // 4. SERVICES BY STATUS - Donut Chart
    try {
        var serviceChart = new ApexCharts(serviceContainer, {
            series: chartData.serviceData,
            labels: chartData.serviceLabels,
            chart: {
                type: 'donut',
                height: 300,
                fontFamily: 'Inter, sans-serif',
                animations: { enabled: true }
            },
            colors: ['#2BC155', '#FF5E5E', '#999'],
            plotOptions: {
                pie: {
                    donut: {
                        size: '65%',
                        labels: {
                            show: true,
                            name: { show: true, fontSize: '14px', fontWeight: 600 },
                            value: { show: true, fontSize: '18px', fontWeight: 700 }
                        }
                    }
                }
            },
            dataLabels: { enabled: true, style: { fontSize: '12px', fontWeight: '600' } },
            legend: { position: 'bottom', fontSize: '12px' },
            tooltip: { theme: 'light' }
        });
        serviceChart.render();
        console.log('Service chart rendered');
    } catch(e) {
        console.error('Service chart error:', e);
    }
}

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', waitForApexCharts);
} else {
    waitForApexCharts();
}
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
