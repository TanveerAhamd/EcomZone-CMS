<?php
/**
 * ACTIVITY LOG PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'Activity Log';

global $db;

$stmt = $db->prepare("
    SELECT al.*, u.name as username
    FROM activity_logs al
    LEFT JOIN users u ON al.user_id = u.id
    ORDER BY al.created_at DESC
    LIMIT 1000
");
$stmt->execute();
$activities = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .search-box {
        margin-bottom: 20px;
    }

    .search-box input {
        width: 300px;
        padding: 10px 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
    }

    .timeline {
        padding: 0;
    }

    .timeline-item {
        display: grid;
        grid-template-columns: 150px 1fr;
        gap: 20px;
        padding: 15px;
        border-bottom: 1px solid #f0f0f0;
        align-items: start;
    }

    .timeline-item:last-child {
        border-bottom: none;
    }

    .timeline-time {
        font-weight: 600;
        color: #6418C3;
        font-size: 0.9rem;
    }

    .timeline-action {
        background: white;
        padding: 12px;
        border-radius: 8px;
        border-left: 3px solid #6418C3;
    }

    .action-header {
        display: flex;
        justify-content: space-between;
        align-items: start;
        margin-bottom: 8px;
    }

    .action-user {
        font-weight: 600;
        color: #1D1D1D;
    }

    .action-badge {
        background: rgba(100,24,195,0.1);
        color: #6418C3;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
    }

    .action-description {
        color: #666;
        font-size: 0.9rem;
    }

    .action-meta {
        margin-top: 8px;
        font-size: 0.8rem;
        color: #999;
    }
</style>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 25px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">Activity Log</h1>
    <div class="search-box">
        <input type="text" id="activitySearch" placeholder="Search activities...">
    </div>
</div>

<?php echo flashAlert(); ?>

<div class="card">
    <div class="timeline">
        <?php foreach ($activities as $activity): ?>
        <div class="timeline-item">
            <div class="timeline-time">
                <?php echo formatDateTime($activity['created_at']); ?>
            </div>
            <div class="timeline-action">
                <div class="action-header">
                    <span class="action-user"><?php echo clean($activity['username'] ?? 'System'); ?></span>
                    <span class="action-badge"><?php echo clean($activity['action']); ?></span>
                </div>
                <p class="action-description">
                    <strong><?php echo ucfirst($activity['module']); ?></strong>
                    <?php if ($activity['description']): ?>
                    — <?php echo clean($activity['description']); ?>
                    <?php endif; ?>
                </p>
                <div class="action-meta">
                    <i class="fas fa-globe"></i> <?php echo clean($activity['ip_address']); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
    document.getElementById('activitySearch').addEventListener('keyup', function(e) {
        const search = e.target.value.toLowerCase();
        document.querySelectorAll('.timeline-item').forEach(item => {
            const text = item.textContent.toLowerCase();
            item.style.display = text.includes(search) ? '' : 'none';
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
