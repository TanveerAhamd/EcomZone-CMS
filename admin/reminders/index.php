<?php
/**
 * REMINDERS - Service Expiry Notifications
 * Track and manage service renewal reminders
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Service Reminders';

global $db;

// Get filter parameter
$filter = $_GET['filter'] ?? 'all'; // all, expiring_30, expiring_7, expired

// Get reminders based on filter
$query = "
    SELECT cs.*, s.service_name, s.category, s.renewal_period, c.client_name, c.primary_phone, c.email
    FROM client_services cs
    JOIN services s ON cs.service_id = s.id
    JOIN clients c ON cs.client_id = c.id
    WHERE 1=1
";

$params = [];

if ($filter === 'expiring_30') {
    $query .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND cs.alert_30_sent = 0";
} elseif ($filter === 'expiring_7') {
    $query .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND cs.alert_7_sent = 0";
} elseif ($filter === 'expired') {
    $query .= " AND expiry_date < CURDATE() AND cs.status = 'active'";
} elseif ($filter === 'pending_renewal') {
    $query .= " AND expiry_date <= CURDATE() AND expiry_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) AND cs.status = 'active'";
}

$query .= " ORDER BY cs.expiry_date ASC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$reminders = $stmt->fetchAll();

// Handle alert actions
if (isset($_GET['action']) && isset($_GET['id'])) {
    $action = $_GET['action'];
    $cs_id = sanitizeInt($_GET['id']);
    
    if ($action === 'mark_alert_sent') {
        $days = sanitizeInt($_GET['days'] ?? 30);
        $field = "alert_" . $days . "_sent";
        
        $stmt = $db->prepare("UPDATE client_services SET $field = 1 WHERE id = ?");
        $stmt->execute([$cs_id]);
        
        logActivity('UPDATE', 'client_services', $cs_id, "Reminder $days days alert sent");
        setFlash('success', "Reminder marked as sent");
        redirect('admin/reminders/index.php?filter=' . $filter);
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .reminder-card {
        border-left: 4px solid #6418C3;
        border-radius: 8px;
        padding: 20px;
        background: white;
        margin-bottom: 15px;
        box-shadow: 0 2px 8px rgba(0,0,0,0.06);
        transition: all 0.3s ease;
    }

    .reminder-card:hover {
        box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    }

    .reminder-card.expired {
        border-left-color: #FF5E5E;
        background: #fff5f5;
    }

    .reminder-card.expiring_7 {
        border-left-color: #FF9B52;
        background: #fff9f5;
    }

    .reminder-card.expiring_30 {
        border-left-color: #FFD700;
        background: #fffbf0;
    }

    .reminder-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 12px;
    }

    .reminder-title {
        font-size: 1.1rem;
        font-weight: 600;
        color: #1D1D1D;
        margin: 0;
    }

    .reminder-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 0.8rem;
        font-weight: 600;
    }

    .reminder-meta {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        margin-bottom: 15px;
    }

    .reminder-meta-item {
        font-size: 0.9rem;
    }

    .reminder-meta-label {
        color: #999;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
    }

    .reminder-meta-value {
        color: #333;
        font-weight: 600;
        margin-top: 3px;
    }

    .reminder-actions {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        margin-top: 15px;
        padding-top: 15px;
        border-top: 1px solid rgba(0,0,0,0.05);
    }

    .stat-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        text-align: center;
        box-shadow: 0 4px 12px rgba(0,0,0,0.06);
    }

    .stat-number {
        font-size: 2rem;
        font-weight: 700;
        color: #6418C3;
        margin: 0;
    }

    .stat-label {
        font-size: 0.9rem;
        color: #999;
        margin-top: 5px;
        text-transform: uppercase;
    }
</style>

<div style="margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">
        <i class="fas fa-bell"></i> Service Reminders & Expiries
    </h1>
    <p style="margin: 8px 0 0 0; color: #666;">Track and manage service renewal reminders</p>
</div>

<?php echo flashAlert(); ?>

<!-- STATISTICS CARDS -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 30px;">
    <?php
    // Get statistics
    $stats = [
        'total' => 0,
        'expiring_30' => 0,
        'expiring_7' => 0,
        'expired' => 0
    ];
    
    // Total active
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM client_services WHERE status = 'active'");
    $stmt->execute();
    $stats['total'] = $stmt->fetch()['cnt'];
    
    // Expiring in 30 days
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM client_services WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) AND status = 'active'");
    $stmt->execute();
    $stats['expiring_30'] = $stmt->fetch()['cnt'];
    
    // Expiring in 7 days
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM client_services WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) AND status = 'active'");
    $stmt->execute();
    $stats['expiring_7'] = $stmt->fetch()['cnt'];
    
    // Expired
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM client_services WHERE expiry_date < CURDATE() AND status = 'active'");
    $stmt->execute();
    $stats['expired'] = $stmt->fetch()['cnt'];
    ?>
    
    <div class="stat-card">
        <p class="stat-number"><?php echo $stats['total']; ?></p>
        <p class="stat-label">Total Active</p>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #FFD700;">
        <p class="stat-number" style="color: #FFD700;"><?php echo $stats['expiring_30']; ?></p>
        <p class="stat-label">Expiring in 30 Days</p>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #FF9B52;">
        <p class="stat-number" style="color: #FF9B52;"><?php echo $stats['expiring_7']; ?></p>
        <p class="stat-label">Expiring in 7 Days</p>
    </div>
    
    <div class="stat-card" style="border-left: 4px solid #FF5E5E;">
        <p class="stat-number" style="color: #FF5E5E;"><?php echo $stats['expired']; ?></p>
        <p class="stat-label">Expired</p>
    </div>
</div>

<!-- FILTER TABS -->
<div style="background: white; border-radius: 12px; margin-bottom: 25px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.06);">
    <div style="display: flex; border-bottom: 1px solid #f0f0f0;">
        <a href="?filter=all" class="filter-tab <?php echo $filter === 'all' ? 'active' : ''; ?>" style="flex: 1; padding: 15px; text-align: center; text-decoration: none; color: <?php echo $filter === 'all' ? '#6418C3' : '#999'; ?>; border-bottom: <?php echo $filter === 'all' ? '3px solid #6418C3' : 'none'; ?>; font-weight: 600; transition: all 0.3s;">
            <i class="fas fa-list"></i> All (<?php echo $stats['total']; ?>)
        </a>
        <a href="?filter=expiring_30" class="filter-tab <?php echo $filter === 'expiring_30' ? 'active' : ''; ?>" style="flex: 1; padding: 15px; text-align: center; text-decoration: none; color: <?php echo $filter === 'expiring_30' ? '#FFD700' : '#999'; ?>; border-bottom: <?php echo $filter === 'expiring_30' ? '3px solid #FFD700' : 'none'; ?>; font-weight: 600; transition: all 0.3s;">
            <i class="fas fa-clock"></i> Expiring 30D (<?php echo $stats['expiring_30']; ?>)
        </a>
        <a href="?filter=expiring_7" class="filter-tab <?php echo $filter === 'expiring_7' ? 'active' : ''; ?>" style="flex: 1; padding: 15px; text-align: center; text-decoration: none; color: <?php echo $filter === 'expiring_7' ? '#FF9B52' : '#999'; ?>; border-bottom: <?php echo $filter === 'expiring_7' ? '3px solid #FF9B52' : 'none'; ?>; font-weight: 600; transition: all 0.3s;">
            <i class="fas fa-exclamation-triangle"></i> Expiring 7D (<?php echo $stats['expiring_7']; ?>)
        </a>
        <a href="?filter=expired" class="filter-tab <?php echo $filter === 'expired' ? 'active' : ''; ?>" style="flex: 1; padding: 15px; text-align: center; text-decoration: none; color: <?php echo $filter === 'expired' ? '#FF5E5E' : '#999'; ?>; border-bottom: <?php echo $filter === 'expired' ? '3px solid #FF5E5E' : 'none'; ?>; font-weight: 600; transition: all 0.3s;">
            <i class="fas fa-times-circle"></i> Expired (<?php echo $stats['expired']; ?>)
        </a>
    </div>
</div>

<!-- REMINDER LIST -->
<div>
    <?php if (count($reminders) > 0): ?>
        <?php foreach ($reminders as $reminder):
            $daysLeft = (strtotime($reminder['expiry_date']) - time()) / (24 * 60 * 60);
            
            if ($daysLeft < 0) {
                $card_class = 'expired';
                $badge_color = 'danger';
                $badge_text = 'EXPIRED ' . abs(round($daysLeft)) . ' DAYS AGO';
            } elseif ($daysLeft < 7) {
                $card_class = 'expiring_7';
                $badge_color = 'warning';
                $badge_text = 'EXPIRES IN ' . round($daysLeft) . ' DAYS';
            } elseif ($daysLeft < 30) {
                $card_class = 'expiring_30';
                $badge_color = 'info';
                $badge_text = 'EXPIRES IN ' . round($daysLeft) . ' DAYS';
            } else {
                $card_class = 'active';
                $badge_color = 'success';
                $badge_text = 'ACTIVE - ' . round($daysLeft) . ' DAYS LEFT';
            }
        ?>
        <div class="reminder-card <?php echo $card_class; ?>">
            <div class="reminder-header">
                <h3 class="reminder-title">
                    <i class="fas fa-service"></i> <?php echo clean($reminder['service_name']); ?>
                </h3>
                <span class="reminder-badge reminder-badge-<?php echo $badge_color; ?>" style="background: <?php echo ['danger' => '#FFE5E5', 'warning' => '#FFF5E5', 'info' => '#E5F2FF', 'success' => '#E5F5E5'][$badge_color] ?? '#E5F5E5'; ?>; color: <?php echo ['danger' => '#FF5E5E', 'warning' => '#FF9B52', 'info' => '#1EAAE7', 'success' => '#2BC155'][$badge_color] ?? '#2BC155'; ?>;">
                    <?php echo $badge_text; ?>
                </span>
            </div>

            <div class="reminder-meta">
                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-user"></i> Client</div>
                    <div class="reminder-meta-value">
                        <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo strtok($reminder['client_name'], '+'); ?>" style="color: #6418C3; text-decoration: none;">
                            <?php echo clean($reminder['client_name']); ?>
                        </a>
                    </div>
                </div>

                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-calendar"></i> Issue Date</div>
                    <div class="reminder-meta-value"><?php echo formatDate($reminder['start_date']); ?></div>
                </div>

                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-calendar-check"></i> Expiry Date</div>
                    <div class="reminder-meta-value" style="color: <?php echo $daysLeft < 0 ? '#FF5E5E' : '#333'; ?>;">
                        <?php echo formatDate($reminder['expiry_date']); ?>
                    </div>
                </div>

                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-tag"></i> Category</div>
                    <div class="reminder-meta-value"><?php echo ucfirst(str_replace('_', ' ', $reminder['category'])); ?></div>
                </div>

                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-redo"></i> Renewal Period</div>
                    <div class="reminder-meta-value"><?php echo ucfirst($reminder['renewal_period']); ?></div>
                </div>

                <div class="reminder-meta-item">
                    <div class="reminder-meta-label"><i class="fas fa-phone"></i> Contact</div>
                    <div class="reminder-meta-value">
                        <a href="tel:<?php echo $reminder['primary_phone']; ?>"><?php echo clean($reminder['primary_phone']); ?></a>
                    </div>
                </div>
            </div>

            <div class="reminder-actions">
                <a href="https://wa.me/<?php echo preg_replace('/[^0-9]/', '', $reminder['primary_phone']); ?>" target="_blank" class="btn btn-sm btn-success">
                    <i class="fab fa-whatsapp"></i> Send WhatsApp
                </a>
                <a href="mailto:<?php echo $reminder['email']; ?>" class="btn btn-sm btn-info">
                    <i class="fas fa-envelope"></i> Send Email
                </a>
                <a href="?action=mark_alert_sent&id=<?php echo $reminder['id']; ?>&days=30&filter=<?php echo $filter; ?>" class="btn btn-sm btn-secondary">
                    <i class="fas fa-check"></i> Mark Alert Sent
                </a>
                <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo strtok($reminder['client_name'], '+'); ?>" class="btn btn-sm btn-outline-primary">
                    <i class="fas fa-user"></i> View Client
                </a>
            </div>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 12px;">
            <i class="fas fa-check-circle" style="font-size: 3rem; color: #2BC155; margin-bottom: 15px; display: block;"></i>
            <p style="font-size: 1.2rem; color: #333; margin: 0;">No reminders in this category</p>
            <p style="color: #999; margin: 8px 0 0 0;">Great job staying on top of service renewals!</p>
        </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
