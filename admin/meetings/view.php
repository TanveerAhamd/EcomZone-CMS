<?php
/**
 * MEETINGS - VIEW PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Meeting Details';

global $db;

$id = $_GET['id'] ?? null;
if (!$id) {
    setFlash('danger', 'Meeting not found');
    redirect('admin/meetings/index.php');
}

$stmt = $db->prepare("SELECT m.*, c.client_name, p.project_name, u.name as created_by FROM meetings m
    LEFT JOIN clients c ON m.client_id = c.id
    LEFT JOIN projects p ON m.project_id = p.id
    LEFT JOIN users u ON m.user_id = u.id
    WHERE m.id = ?");
$stmt->execute([$id]);
$meeting = $stmt->fetch();

if (!$meeting) {
    setFlash('danger', 'Meeting not found');
    redirect('admin/meetings/index.php');
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="margin-bottom: 20px;">
    <a href="/EcomZone-CMS/admin/meetings/index.php" class="btn btn-secondary">
        <i class="fas fa-arrow-left"></i> Back to Meetings
    </a>
</div>

<div style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <h2 style="margin-top: 0; font-weight: 700;"><?php echo clean($meeting['title']); ?></h2>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 25px 0; padding: 20px; background: #f8f9ff; border-radius: 8px;">
        <div>
            <small style="color: #999; font-weight: 600;">Date</small>
            <p style="margin: 8px 0 0 0; font-size: 1.1rem;">
                <i class="fas fa-calendar"></i> <?php echo formatDate($meeting['meeting_date']); ?>
                <?php if ($meeting['meeting_time']): ?>
                <i class="fas fa-clock" style="margin-left: 10px;"></i> <?php echo $meeting['meeting_time']; ?>
                <?php endif; ?>
            </p>
        </div>

        <?php if ($meeting['client_name']): ?>
        <div>
            <small style="color: #999; font-weight: 600;">Client</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;"><?php echo clean($meeting['client_name']); ?></p>
        </div>
        <?php endif; ?>

        <?php if ($meeting['project_name']): ?>
        <div>
            <small style="color: #999; font-weight: 600;">Project</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;"><?php echo clean($meeting['project_name']); ?></p>
        </div>
        <?php endif; ?>

        <div>
            <small style="color: #999; font-weight: 600;">Created By</small>
            <p style="margin: 8px 0 0 0; font-size: 1rem;"><?php echo clean($meeting['created_by']); ?></p>
        </div>
    </div>

    <?php if ($meeting['attendees']): ?>
    <div style="margin: 20px 0;">
        <h5 style="font-weight: 600;">Attendees</h5>
        <p><?php echo clean($meeting['attendees']); ?></p>
    </div>
    <?php endif; ?>

    <?php if ($meeting['agenda']): ?>
    <div style="margin: 20px 0;">
        <h5 style="font-weight: 600;">Agenda</h5>
        <div style="background: #f8f9ff; padding: 15px; border-radius: 6px; white-space: pre-wrap;">
            <?php echo clean($meeting['agenda']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($meeting['notes']): ?>
    <div style="margin: 20px 0;">
        <h5 style="font-weight: 600;">Notes</h5>
        <div style="background: #f8f9ff; padding: 15px; border-radius: 6px; white-space: pre-wrap;">
            <?php echo clean($meeting['notes']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($meeting['action_items']): ?>
    <div style="margin: 20px 0;">
        <h5 style="font-weight: 600;">Action Items</h5>
        <div style="background: #fff3cd; padding: 15px; border-radius: 6px; border-left: 4px solid #FF9B52; white-space: pre-wrap;">
            <?php echo clean($meeting['action_items']); ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if ($meeting['follow_up_date']): ?>
    <div style="margin: 20px 0;">
        <h5 style="font-weight: 600;">Follow-up Date</h5>
        <p>
            <i class="fas fa-calendar"></i> <?php echo formatDate($meeting['follow_up_date']); ?>
        </p>
    </div>
    <?php endif; ?>

    <div style="margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0;">
        <a href="/EcomZone-CMS/admin/meetings/add.php?id=<?php echo $meeting['id']; ?>" class="btn btn-warning">
            <i class="fas fa-edit"></i> Edit Meeting
        </a>
        <a href="/EcomZone-CMS/admin/meetings/index.php" class="btn btn-secondary">
            <i class="fas fa-arrow-left"></i> Back
        </a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
