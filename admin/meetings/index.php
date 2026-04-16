<?php
/**
 * MEETINGS - LIST PAGE (MODERN REDESIGN)
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Meetings';

global $db;

// Get current user's meetings
$user_id = currentUser()['id'];
$stmt = $db->prepare("
    SELECT m.*, c.client_name, p.project_name
    FROM meetings m
    LEFT JOIN clients c ON m.client_id = c.id
    LEFT JOIN projects p ON m.project_id = p.id
    WHERE m.user_id = ?
    ORDER BY m.meeting_date DESC
    LIMIT 100
");
$stmt->execute([$user_id]);
$meetings = $stmt->fetchAll();

// Calculate meeting status
function getMeetingStatus($meeting_date) {
    $date = new DateTime($meeting_date);
    $today = new DateTime();
    $today->setTime(0, 0, 0);
    
    if ($date >= $today) {
        return ['status' => 'Upcoming', 'color' => '#2BC155', 'bg' => '#d4edda'];
    } else {
        return ['status' => 'Past', 'color' => '#999', 'bg' => '#f5f5f5'];
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 30px;">
    <h1 style="margin: 0; font-weight: 700; font-size: 2rem;">
        <i class="fas fa-calendar-alt" style="color: #6418C3; margin-right: 10px;"></i>Meetings
    </h1>
    <a href="/EcomZone-CMS/admin/meetings/add.php" class="btn" style="background: #6418C3; border: none; color: white; padding: 12px 25px; border-radius: 8px; font-weight: 600;">
        <i class="fas fa-plus"></i> Create Meeting
    </a>
</div>

<?php echo flashAlert(); ?>

<!-- MEETINGS LIST -->
<?php if (count($meetings) > 0): ?>
<div style="display: grid; gap: 15px;">
    <?php foreach ($meetings as $meeting): ?>
    <div style="background: white; border-radius: 10px; padding: 20px; box-shadow: 0 4px 12px rgba(0,0,0,0.08); border-left: 4px solid #6418C3; display: flex; justify-content: space-between; align-items: center;">
        <div style="flex: 1;">
            <h4 style="margin: 0 0 10px 0; font-weight: 600; font-size: 1.1rem; color: #1a202c;">
                <?php echo clean($meeting['title']); ?>
            </h4>
            <div style="display: flex; gap: 20px; font-size: 0.9rem; color: #666;">
                <?php if ($meeting['client_name']): ?>
                <span><i class="fas fa-building"></i> <?php echo clean($meeting['client_name']); ?></span>
                <?php endif; ?>
                <?php if ($meeting['project_name']): ?>
                <span><i class="fas fa-briefcase"></i> <?php echo clean($meeting['project_name']); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <a href="/EcomZone-CMS/admin/meetings/view.php?id=<?php echo $meeting['id']; ?>" class="btn" style="background: #f0f4ff; color: #6418C3; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-eye"></i> View
            </a>
            <a href="/EcomZone-CMS/admin/meetings/add.php?id=<?php echo $meeting['id']; ?>" class="btn" style="background: #fff3cd; color: #856404; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-edit"></i> Edit
            </a>
            <button type="button" onclick="deleteMeeting(<?php echo $meeting['id']; ?>, '<?php echo clean($meeting['title']); ?>')" class="btn" style="background: #f8d7da; color: #721c24; padding: 8px 15px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; font-size: 0.9rem;">
                <i class="fas fa-trash"></i> Delete
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php else: ?>
<div style="background: white; border-radius: 10px; padding: 60px 25px; text-align: center; box-shadow: 0 4px 12px rgba(0,0,0,0.08);">
    <i class="fas fa-calendar-times" style="font-size: 3rem; color: #ddd; margin-bottom: 15px; display: block;"></i>
    <h3 style="color: #999; margin: 0 0 10px 0;">No Meetings Yet</h3>
    <p style="color: #ccc; margin: 0 0 20px 0;">Create your first meeting to get started</p>
    <a href="/EcomZone-CMS/admin/meetings/add.php" class="btn" style="background: #6418C3; border: none; color: white; padding: 10px 20px; border-radius: 8px; font-weight: 600;">
        <i class="fas fa-plus"></i> Create Meeting
    </a>
</div>
<?php endif; ?>

<script>
function deleteMeeting(id, title) {
    if (confirm(`Delete meeting "${title}"? This cannot be undone.`)) {
        fetch('/EcomZone-CMS/admin/meetings/delete.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                window.location.reload();
            } else {
                alert('Error: ' + (data.error || 'Unknown error'));
            }
        })
        .catch(error => alert('Error: ' + error.message));
    }
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
