<?php
/**
 * SERVICES - DEPRECATED
 * This module has been replaced by Manage Alerts
 * Redirecting to new module...
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

// Redirect to the new Manage Alerts module
redirect('admin/alerts/index.php');
    }
}

global $db;

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .alert-banner {
        background: linear-gradient(135deg, #FF9B52, #E74C3C);
        color: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }

    .filter-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
    }

    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e0e0e0;
        background: white;
        border-radius: 8px;
        cursor: pointer;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .filter-btn.active {
        background: #6418C3;
        color: white;
        border-color: #6418C3;
    }

    .filter-btn:hover {
        border-color: #6418C3;
    }

    .btn-bulk {
        background: #25D366;
        color: white;
        padding: 12px 24px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;"><i class="fas fa-exclamation-triangle" style="color: #FF9B52;"></i> Service Expiry Alerts</h1>
    <button class="btn-bulk"><i class="fab fa-whatsapp"></i> Send All Alerts</button>
</div>

<?php if (count($filtered) > 0): ?>
<div class="alert-banner">
    <div>
        <strong style="font-size: 1.1rem;">⚠️ <?php echo count($filtered); ?> services expiring</strong>
        <p style="margin: 5px 0 0 0; opacity: 0.95;">Action required to prevent service interruption</p>
    </div>
</div>
<?php endif; ?>

<!-- FILTER TABS -->
<div class="filter-tabs">
    <a href="?days=" class="filter-btn <?php echo !$daysFilter ? 'active' : ''; ?>">All Services</a>
    <a href="?days=7" class="filter-btn <?php echo $daysFilter === 7 ? 'active' : ''; ?>"><i class="fas fa-fire"></i> 7 Days</a>
    <a href="?days=15" class="filter-btn <?php echo $daysFilter === 15 ? 'active' : ''; ?>"><i class="fas fa-clock"></i> 15 Days</a>
    <a href="?days=30" class="filter-btn <?php echo $daysFilter === 30 ? 'active' : ''; ?>"><i class="fas fa-calendar"></i> 30 Days</a>
</div>

<!-- TABLE -->
<div class="card">
    <table data-datatable-export class="table table-hover">
        <thead>
            <tr>
                <th>Client</th>
                <th>Service</th>
                <th>Category</th>
                <th>Expiry Date</th>
                <th>Days Left</th>
                <th>Price</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if (count($filtered) > 0): ?>
                <?php foreach ($filtered as $service): 
                    $badgeClass = $service['daysLeft'] < 7 ? 'danger' : ($service['daysLeft'] < 15 ? 'warning' : 'warning');
                ?>
                <tr>
                    <td>
                        <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo $service['client_id']; ?>">
                            <?php echo clean($service['client_name']); ?>
                        </a>
                    </td>
                    <td><?php echo clean($service['service_name']); ?></td>
                    <td><span class="badge bg-secondary"><?php echo ucfirst(str_replace('_', ' ', $service['category'])); ?></span></td>
                    <td><?php echo formatDate($service['expiry_date']); ?></td>
                    <td>
                        <span class="badge bg-<?php echo $badgeClass; ?>">
                            <?php echo round($service['daysLeft']); ?> days
                        </span>
                    </td>
                    <td><?php echo formatCurrency($service['price']); ?></td>
                    <td>
                        <button class="btn btn-sm btn-success" title="Send WhatsApp Alert">
                            <i class="fab fa-whatsapp"></i>
                        </button>
                        <button class="btn btn-sm btn-primary" title="Send Email">
                            <i class="fas fa-envelope"></i>
                        </button>
                        <button class="btn btn-sm btn-warning" title="Mark Renewed">
                            <i class="fas fa-sync-alt"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="7" style="text-align: center; padding: 40px; color: #999;">
                        <i class="fas fa-check-circle" style="font-size: 3rem; margin-bottom: 15px; display: block;"></i>
                        No services in this range
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
