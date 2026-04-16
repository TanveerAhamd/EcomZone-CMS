<?php
/**
 * MEETINGS - VIEW PAGE (ENHANCED)
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

$stmt = $db->prepare("SELECT m.*, c.client_name, p.project_name FROM meetings m
    LEFT JOIN clients c ON m.client_id = c.id
    LEFT JOIN projects p ON m.project_id = p.id
    WHERE m.id = ?");
$stmt->execute([$id]);
$meeting = $stmt->fetch();

if (!$meeting) {
    setFlash('danger', 'Meeting not found');
    redirect('admin/meetings/index.php');
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="background: white; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); padding: 30px; max-width: 700px;">
    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 25px;">
        <div>
            <h2 style="margin: 0; font-weight: 700; color: #1a202c;"><?php echo clean($meeting['title']); ?></h2>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="/EcomZone-CMS/admin/meetings/add.php?id=<?php echo $meeting['id']; ?>" class="btn" style="background: #fff3cd; color: #856404; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem; text-decoration: none;">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button type="button" onclick="deleteMeeting()" class="btn" style="background: #f8d7da; color: #721c24; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>

    <?php echo flashAlert(); ?>

    <!-- Meeting Details -->
    <div style="display: grid; gap: 20px;">
        <!-- Client & Project -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; padding: 15px; background: #f8f9ff; border-radius: 8px;">
            <?php if ($meeting['client_name']): ?>
            <div>
                <small style="color: #999; font-weight: 600;">Client</small>
                <p style="margin: 6px 0 0 0; font-weight: 500;"><?php echo clean($meeting['client_name']); ?></p>
            </div>
            <?php endif; ?>
            <?php if ($meeting['project_name']): ?>
            <div>
                <small style="color: #999; font-weight: 600;">Project</small>
                <p style="margin: 6px 0 0 0; font-weight: 500;"><?php echo clean($meeting['project_name']); ?></p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Agenda -->
        <?php if ($meeting['agenda']): ?>
        <div>
            <label style="font-weight: 600; color: #1a202c; display: block; margin-bottom: 8px;">
                <i class="fas fa-list" style="color: #6418C3; margin-right: 8px;"></i>Agenda
            </label>
            <div style="background: #f8f9ff; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; color: #666;">
                <?php echo clean($meeting['agenda']); ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Meeting Notes -->
        <?php if ($meeting['notes']): ?>
        <div>
            <label style="font-weight: 600; color: #1a202c; display: block; margin-bottom: 8px;">
                <i class="fas fa-sticky-note" style="color: #6418C3; margin-right: 8px;"></i>Meeting Notes
            </label>
            <div style="background: #f8f9ff; padding: 15px; border-radius: 8px; white-space: pre-wrap; line-height: 1.6; color: #666;">
                <?php echo clean($meeting['notes']); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Back Button -->
    <div style="margin-top: 25px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
        <a href="/EcomZone-CMS/admin/meetings/index.php" class="btn" style="background: #f0f0f0; color: #1a202c; padding: 10px 20px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; text-decoration: none;">
            <i class="fas fa-arrow-left"></i> Back to Meetings
        </a>
    </div>
</div>

<script>
function deleteMeeting() {
    if (confirm('Delete this meeting? This cannot be undone.')) {
        fetch('/EcomZone-CMS/admin/meetings/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: <?php echo $meeting['id']; ?> })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.href = '/EcomZone-CMS/admin/meetings/index.php';
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => alert('Error: ' + error.message));
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
