<?php
/**
 * MANAGE ALERTS - SERVICE EXPIRATION TRACKING
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Manage Alerts';

global $db;

// Get filter from query
$filter = $_GET['filter'] ?? 'all';

// Get all project services with their status
$stmt = $db->prepare("
    SELECT 
        ps.*, 
        p.project_name, p.id as project_id,
        c.client_name, c.id as client_id,
        c.client_email, c.client_phone
    FROM project_services ps
    LEFT JOIN projects p ON ps.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE ps.status IN ('active', 'expired')
    ORDER BY ps.expiry_date ASC, ps.created_at DESC
");
$stmt->execute();
$services = $stmt->fetchAll();

// Calculate statistics
$stats = [
    'total' => 0,
    'active' => 0,
    'expiring_7d' => 0,
    'expiring_30d' => 0,
    'expired' => 0
];

$today = date('Y-m-d');
$today_7d = date('Y-m-d', strtotime('+7 days'));
$today_30d = date('Y-m-d', strtotime('+30 days'));

$filtered_services = [];

foreach ($services as $s) {
    $stats['total']++;
    
    if ($s['status'] === 'expired' || ($s['expiry_date'] && $s['expiry_date'] < $today)) {
        $stats['expired']++;
        $s['alert_type'] = 'expired';
        if ($filter === 'all' || $filter === 'expired') {
            $filtered_services[] = $s;
        }
    } elseif ($s['expiry_date']) {
        $stats['active']++;
        
        if ($s['expiry_date'] <= $today_7d) {
            $stats['expiring_7d']++;
            $s['alert_type'] = '7days';
            if ($filter === 'all' || $filter === '7days') {
                $filtered_services[] = $s;
            }
        } elseif ($s['expiry_date'] <= $today_30d) {
            $stats['expiring_30d']++;
            $s['alert_type'] = '30days';
            if ($filter === 'all' || $filter === '30days') {
                $filtered_services[] = $s;
            }
        } else {
            $s['alert_type'] = 'active';
            if ($filter === 'all' || $filter === 'active') {
                $filtered_services[] = $s;
            }
        }
    }
}

// Handle send reminder
if ($_POST['action'] === 'send_reminder' && $_POST['service_id']) {
    $service_id = sanitizeInt($_POST['service_id']);
    $message_type = $_POST['message_type'] ?? 'whatsapp';
    
    $stmt = $db->prepare("SELECT ps.*, p.project_name, c.client_name, c.client_email, c.client_phone FROM project_services ps LEFT JOIN projects p ON ps.project_id = p.id LEFT JOIN clients c ON p.client_id = c.id WHERE ps.id = ?");
    $stmt->execute([$service_id]);
    $svc = $stmt->fetch();
    
    if ($svc) {
        $message = "Hi {$svc['client_name']}, your service '{$svc['service_name']}' in project '{$svc['project_name']}' will expire on " . formatDate($svc['expiry_date']) . ".";
        
        // Log the reminder sent
        logActivity('SEND_REMINDER', 'project_services', $service_id, "Reminder sent via {$message_type} to {$svc['client_name']}");
        setFlash('success', "Reminder sent via " . ucfirst($message_type) . "!");
        
        // TODO: Integrate actual WhatsApp/Email sending here
        // For now, just log it
    }
    
    redirect('admin/alerts/index.php?filter=' . $filter);
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fas fa-bell"></i> Manage Alerts & Reminders
</h1>

<!-- Statistics -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-bottom: 25px;">
    <a href="?filter=all" style="text-decoration: none; cursor: pointer;">
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #6418C3; transition: transform 0.2s;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Total Services</div>
            <div style="font-size: 2rem; font-weight: 700; color: #6418C3;"><?php echo $stats['total']; ?></div>
        </div>
    </a>

    <a href="?filter=7days" style="text-decoration: none; cursor: pointer;">
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #ff6b6b;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Expiring in 7 Days</div>
            <div style="font-size: 2rem; font-weight: 700; color: #ff6b6b;"><?php echo $stats['expiring_7d']; ?></div>
        </div>
    </a>

    <a href="?filter=30days" style="text-decoration: none; cursor: pointer;">
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #ffc107;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Expiring in 30 Days</div>
            <div style="font-size: 2rem; font-weight: 700; color: #ffc107;"><?php echo $stats['expiring_30d']; ?></div>
        </div>
    </a>

    <a href="?filter=expired" style="text-decoration: none; cursor: pointer;">
        <div style="background: white; border-radius: 8px; padding: 20px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); border-left: 4px solid #dc3545;">
            <div style="font-size: 0.85rem; color: #666; margin-bottom: 5px;">Expired</div>
            <div style="font-size: 2rem; font-weight: 700; color: #dc3545;"><?php echo $stats['expired']; ?></div>
        </div>
    </a>
</div>

<!-- Filter Tabs -->
<div style="display: flex; gap: 10px; margin-bottom: 25px; flex-wrap: wrap;">
    <a href="?filter=all" class="btn <?php echo $filter === 'all' || !$filter ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 6px;">
        All (<?php echo $stats['total']; ?>)
    </a>
    <a href="?filter=7days" class="btn <?php echo $filter === '7days' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 6px;">
        <i class="fas fa-exclamation-circle"></i> 7 Days (<?php echo $stats['expiring_7d']; ?>)
    </a>
    <a href="?filter=30days" class="btn <?php echo $filter === '30days' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 6px;">
        <i class="fas fa-clock"></i> 30 Days (<?php echo $stats['expiring_30d']; ?>)
    </a>
    <a href="?filter=expired" class="btn <?php echo $filter === 'expired' ? 'btn-primary' : 'btn-secondary'; ?>" style="border-radius: 6px;">
        <i class="fas fa-times-circle"></i> Expired (<?php echo $stats['expired']; ?>)
    </a>
</div>

<?php echo flashAlert(); ?>

<!-- Services Table -->
<div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); overflow: hidden;">
    <?php if (empty($filtered_services)): ?>
        <div style="text-align: center; padding: 60px 20px; color: #999;">
            <i class="fas fa-inbox" style="font-size: 3rem; margin-bottom: 10px; display: block;"></i>
            <p style="margin: 0; font-size: 1.1rem;">No alerts for this filter</p>
        </div>
    <?php else: ?>
        <table class="table table-hover" style="margin: 0;">
            <thead style="background: #f8f9ff; border-bottom: 2px solid #e0e0e0;">
                <tr>
                    <th style="padding: 15px; font-weight: 600;">Service</th>
                    <th style="padding: 15px; font-weight: 600;">Client</th>
                    <th style="padding: 15px; font-weight: 600;">Project</th>
                    <th style="padding: 15px; font-weight: 600;">Expiry Date</th>
                    <th style="padding: 15px; font-weight: 600;">Status</th>
                    <th style="padding: 15px; font-weight: 600;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($filtered_services as $s): 
                    $days_until = $s['expiry_date'] ? floor((strtotime($s['expiry_date']) - strtotime('today')) / 86400) : null;
                    $bg_color = match($s['alert_type']) {
                        'expired' => '#fff5f5',
                        '7days' => '#fff3cd',
                        '30days' => '#f0f9ff',
                        default => 'white'
                    };
                ?>
                <tr style="background: <?php echo $bg_color; ?>; border-bottom: 1px solid #e0e0e0;">
                    <td style="padding: 15px;">
                        <div style="font-weight: 600; margin-bottom: 3px;"><?php echo clean($s['service_name']); ?></div>
                        <small style="color: #999;">Price: <?php echo formatCurrency($s['price']); ?></small>
                    </td>
                    <td style="padding: 15px;">
                        <a href="/EcomZone-CMS/admin/clients/profile.php?id=<?php echo $s['client_id']; ?>" style="color: #6418C3; text-decoration: none;">
                            <?php echo clean($s['client_name']); ?>
                        </a>
                        <div style="font-size: 0.85rem; color: #999; margin-top: 3px;">
                            <i class="fas fa-phone"></i> <?php echo $s['client_phone'] ?? 'N/A'; ?>
                        </div>
                    </td>
                    <td style="padding: 15px;">
                        <a href="/EcomZone-CMS/admin/projects/view.php?id=<?php echo $s['project_id']; ?>" style="color: #6418C3; text-decoration: none;">
                            <?php echo clean($s['project_name']); ?>
                        </a>
                    </td>
                    <td style="padding: 15px;">
                        <div style="font-weight: 600; margin-bottom: 3px;"><?php echo $s['expiry_date'] ? formatDate($s['expiry_date']) : 'N/A'; ?></div>
                        <?php if ($days_until !== null): ?>
                            <small style="color: <?php echo $days_until < 0 ? '#dc3545' : ($days_until <= 7 ? '#ff6b6b' : '#666'); ?>;">
                                <?php 
                                if ($days_until < 0) echo '⚠️ ' . abs($days_until) . ' days ago';
                                elseif ($days_until === 0) echo '📌 Today';
                                else echo '📅 ' . $days_until . ' days';
                                ?>
                            </small>
                        <?php endif; ?>
                    </td>
                    <td style="padding: 15px;">
                        <?php 
                        $badge_color = match($s['alert_type']) {
                            'expired' => ['bg' => '#f8d7da', 'text' => '#721c24'],
                            '7days' => ['bg' => '#fff3cd', 'text' => '#856404'],
                            '30days' => ['bg' => '#d1ecf1', 'text' => '#0c5460'],
                            default => ['bg' => '#d4edda', 'text' => '#155724']
                        };
                        ?>
                        <span style="padding: 6px 10px; border-radius: 4px; font-size: 0.85rem; font-weight: 600; background: <?php echo $badge_color['bg']; ?>; color: <?php echo $badge_color['text']; ?>;">
                            <?php 
                            echo match($s['alert_type']) {
                                'expired' => '🔴 Expired',
                                '7days' => '🟠 Urgent (7d)',
                                '30days' => '🟡 Warning (30d)',
                                default => '🟢 Active'
                            };
                            ?>
                        </span>
                    </td>
                    <td style="padding: 15px;">
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="send_reminder">
                            <input type="hidden" name="service_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" name="message_type" value="whatsapp" class="btn btn-sm" style="background: #25D366; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; margin-right: 5px; font-size: 0.85rem;">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </button>
                        </form>
                        <form method="POST" style="display: inline;">
                            <input type="hidden" name="action" value="send_reminder">
                            <input type="hidden" name="service_id" value="<?php echo $s['id']; ?>">
                            <button type="submit" name="message_type" value="email" class="btn btn-sm" style="background: #EA4335; color: white; border: none; padding: 6px 10px; border-radius: 4px; cursor: pointer; font-size: 0.85rem;">
                                <i class="fas fa-envelope"></i> Email
                            </button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<div style="margin-top: 25px; background: #f8f9ff; border-radius: 8px; padding: 20px; border-left: 4px solid #6418C3;">
    <p style="margin: 0; color: #666;">
        <i class="fas fa-info-circle"></i> <strong>Workflow:</strong> Review services by expiration status. Send reminders via WhatsApp or Email to clients before services expire. Mark as expired or renew as needed.
    </p>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
