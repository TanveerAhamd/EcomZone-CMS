<?php
/**
 * MEETINGS - LIST PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Meetings';

global $db;

// Get current user's meetings
$user_id = currentUser()['id'];
$stmt = $db->prepare("
    SELECT m.*, c.client_name, p.project_name, u.name as created_by_user
    FROM meetings m
    LEFT JOIN clients c ON m.client_id = c.id
    LEFT JOIN projects p ON m.project_id = p.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.user_id = ? OR m.is_private = 0
    ORDER BY m.meeting_date DESC
    LIMIT 100
");
$stmt->execute([$user_id]);
$meetings = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fas fa-calendar-alt"></i> Meetings
</h1>

<?php echo flashAlert(); ?>

<!-- ADD BUTTON -->
<div style="margin-bottom: 20px;">
    <a href="/EcomZone-CMS/admin/meetings/add.php" class="btn btn-primary" style="background: #6418C3; border: none;">
        <i class="fas fa-plus"></i> Schedule Meeting
    </a>
</div>

<!-- MEETINGS LIST -->
<div style="display: grid; gap: 15px;">
    <?php foreach ($meetings as $meeting): ?>
    <div style="background: white; border-radius: 12px; padding: 20px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); border-left: 4px solid #6418C3;">
        <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 12px;">
            <div>
                <h4 style="margin: 0; font-weight: 600; font-size: 1.1rem;"><?php echo clean($meeting['title']); ?></h4>
                <p style="margin: 8px 0 0 0; color: #666; font-size: 0.9rem;">
                    <i class="fas fa-calendar"></i> <?php echo formatDate($meeting['meeting_date']); ?>
                    <?php if ($meeting['meeting_time']): ?>
                    <i class="fas fa-clock" style="margin-left: 15px;"></i> <?php echo $meeting['meeting_time']; ?>
                    <?php endif; ?>
                </p>
            </div>
            <a href="/EcomZone-CMS/admin/meetings/view.php?id=<?php echo $meeting['id']; ?>" class="btn btn-sm btn-info">
                <i class="fas fa-eye"></i> View Details
            </a>
        </div>

        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 12px;">
            <?php if ($meeting['client_name']): ?>
            <div>
                <small style="color: #999;">Client</small>
                <p style="margin: 0; font-weight: 500;"><?php echo clean($meeting['client_name']); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($meeting['project_name']): ?>
            <div>
                <small style="color: #999;">Project</small>
                <p style="margin: 0; font-weight: 500;"><?php echo clean($meeting['project_name']); ?></p>
            </div>
            <?php endif; ?>
            <div>
                <small style="color: #999;">Attendees</small>
                <p style="margin: 0; font-weight: 500;"><?php echo clean($meeting['attendees'] ?? 'N/A'); ?></p>
            </div>
        </div>

        <?php if ($meeting['notes']): ?>
        <div style="background: #f8f9ff; padding: 12px; border-radius: 6px; margin-top: 12px;">
            <small style="color: #999;">Notes</small>
            <p style="margin: 0; color: #666;"><?php echo substr(clean($meeting['notes']), 0, 150); ?><?php echo strlen($meeting['notes']) > 150 ? '...' : ''; ?></p>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
