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
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY created_at ASC
");
$stmt->execute();
$revenueData = $stmt->fetchAll();

// Project status data
$stmt = $db->prepare("
    SELECT status, COUNT(*) as count
    FROM projects
    GROUP BY status
");
$stmt->execute();
$projectStatus = $stmt->fetchAll();

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
        height: 300px;
        margin-bottom: 20px;
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

<!-- STAT CARDS ROW -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-1">
            <div class="stat-label">Total Clients</div>
            <div class="stat-number"><?php echo $stats['total_clients']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-arrow-up"></i> +0.5%
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-2">
            <div class="stat-label">Active Projects</div>
            <div class="stat-number"><?php echo $stats['active_projects']; ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-arrow-up"></i> +2.1%
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-3">
            <div class="stat-label">Unpaid Invoices</div>
            <div class="stat-number"><?php echo $stats['unpaid_invoices']; ?></div>
            <span class="stat-trend negative">
                <i class="fas fa-arrow-down"></i> -1.2%
            </span>
        </div>
    </div>

    <div class="col-lg-3 col-md-6 mb-3">
        <div class="stat-card stat-card-gradient-4">
            <div class="stat-label">Total Revenue</div>
            <div class="stat-number"><?php echo formatCurrency($stats['total_revenue']); ?></div>
            <span class="stat-trend positive">
                <i class="fas fa-arrow-up"></i> +3.4%
            </span>
        </div>
    </div>
</div>

<!-- CHARTS ROW -->
<div class="row mb-4">
    <!-- Revenue Chart -->
    <div class="col-lg-8">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-chart-area"></i> Revenue Overview (Last 6 months)
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="revenueChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Project Status Chart -->
    <div class="col-lg-4">
        <div class="card">
            <div class="card-header">
                <i class="fas fa-pie-chart"></i> Project Status
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="projectStatusChart"></canvas>
                </div>
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
            </div>
            <div class="card-body">
                <?php if (count($expiringServices) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-hover" style="margin-bottom: 0;">
                        <thead>
                            <tr>
                                <th>Client</th>
                                <th>Service</th>
                                <th>Expiry Date</th>
                                <th>Days Left</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($expiringServices as $service): 
                                $daysLeft = (strtotime($service['expiry_date']) - time()) / (24 * 60 * 60);
                                $badgeClass = $daysLeft < 7 ? 'critical' : ($daysLeft < 30 ? 'warning' : 'safe');
                            ?>
                            <tr>
                                <td>
                                    <strong><?php echo clean($service['client_name']); ?></strong>
                                </td>
                                <td><?php echo clean($service['service_name']); ?></td>
                                <td><?php echo formatDate($service['expiry_date']); ?></td>
                                <td>
                                    <span class="expiry-badge <?php echo $badgeClass; ?>">
                                        <?php echo round($daysLeft); ?> days
                                    </span>
                                </td>
                                <td><?php echo statusBadge($service['status']); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-success btn-action" title="Send WhatsApp Alert">
                                        <i class="fab fa-whatsapp"></i>
                                    </button>
                                    <button class="btn btn-sm btn-primary btn-action" title="Send Email">
                                        <i class="fas fa-envelope"></i>
                                    </button>
                                </td>
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

<!-- ApexCharts Script -->
<script>
    // Revenue Chart
    <?php
    $months = [];
    $revenues = [];
    foreach ($revenueData as $data) {
        $months[] = $data['month'];
        $revenues[] = round($data['revenue'], 2);
    }
    ?>
    const revenueChart = new ApexCharts(document.querySelector("#revenueChart"), {
        series: [{
            name: 'Revenue',
            data: <?php echo json_encode($revenues); ?>
        }],
        chart: {
            type: 'area',
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
            categories: <?php echo json_encode($months); ?>
        },
        yaxis: {
            title: { text: 'Revenue' }
        },
        tooltip: {
            y: {
                formatter: function(val) {
                    return CURRENCY_SYMBOL + val.toLocaleString('en-PK', {minimumFractionDigits: 2});
                }
            }
        }
    });
    revenueChart.render();

    // Project Status Chart
    <?php
    $statuses = [];
    $counts = [];
    foreach ($projectStatus as $data) {
        $statuses[] = ucfirst(str_replace('_', ' ', $data['status']));
        $counts[] = $data['count'];
    }
    ?>
    const projectStatusChart = new ApexCharts(document.querySelector("#projectStatusChart"), {
        series: <?php echo json_encode($counts); ?>,
        labels: <?php echo json_encode($statuses); ?>,
        chart: {
            type: 'donut',
            height: 300
        },
        colors: ['#6418C3', '#1EAAE7', '#FF9B52', '#2BC155'],
        dataLabels: {
            enabled: true,
            formatter: function(val) {
                return val.toFixed(0) + '%';
            }
        },
        legend: {
            position: 'bottom',
            fontSize: '12px'
        }
    });
    projectStatusChart.render();
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>
