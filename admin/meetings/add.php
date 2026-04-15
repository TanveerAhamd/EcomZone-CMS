<?php
/**
 * MEETINGS - ADD/EDIT PAGE
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

$pageTitle = 'Schedule Meeting';

global $db;

$meeting = null;
$id = $_GET['id'] ?? null;

if ($id) {
    $stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
    $stmt->execute([$id]);
    $meeting = $stmt->fetch();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        try {
            $title = $_POST['title'] ?? '';
            $client_id = $_POST['client_id'] ?? null;
            $project_id = $_POST['project_id'] ?? null;
            $meeting_date = $_POST['meeting_date'] ?? '';
            $meeting_time = $_POST['meeting_time'] ?? '';
            $attendees = $_POST['attendees'] ?? '';
            $agenda = $_POST['agenda'] ?? '';
            $notes = $_POST['notes'] ?? '';
            $action_items = $_POST['action_items'] ?? '';
            $follow_up_date = $_POST['follow_up_date'] ?? null;
            
            if (!$title) {
                setFlash('danger', 'Meeting title is required');
            } elseif (!$meeting_date) {
                setFlash('danger', 'Meeting date is required');
            } else {
                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE meetings SET 
                            title = ?, client_id = ?, project_id = ?, meeting_date = ?,
                            meeting_time = ?, attendees = ?, agenda = ?, notes = ?,
                            action_items = ?, follow_up_date = ?
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $client_id, $project_id, $meeting_date, $meeting_time, $attendees, $agenda, $notes, $action_items, $follow_up_date, $id]);
                    logActivity('UPDATE', 'meetings', $id, "Meeting updated");
                    setFlash('success', 'Meeting updated successfully!');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO meetings (user_id, title, client_id, project_id, meeting_date, meeting_time, attendees, agenda, notes, action_items, follow_up_date, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([currentUser()['id'], $title, $client_id, $project_id, $meeting_date, $meeting_time, $attendees, $agenda, $notes, $action_items, $follow_up_date]);
                    logActivity('CREATE', 'meetings', $db->lastInsertId(), "Meeting created");
                    setFlash('success', 'Meeting scheduled successfully!');
                }
                redirect('admin/meetings/index.php');
            }
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <?php echo $meeting ? 'Edit Meeting' : 'Schedule New Meeting'; ?>
</h1>

<?php echo flashAlert(); ?>

<form method="POST" style="background: white; border-radius: 12px; padding: 25px; box-shadow: 0 4px 15px rgba(0,0,0,0.08);">
    <?php echo csrfField(); ?>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-group">
            <label>Meeting Title *</label>
            <input type="text" name="title" class="form-control" value="<?php echo clean($meeting['title'] ?? ''); ?>" required>
        </div>

        <div class="form-group">
            <label>Client (Optional)</label>
            <select name="client_id" class="form-control">
                <option value="">-- Select Client --</option>
                <?php
                $stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name");
                $stmt->execute();
                $clients = $stmt->fetchAll();
                foreach ($clients as $c):
                ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($meeting['client_id'] ?? null) == $c['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($c['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label>Project (Optional)</label>
            <select name="project_id" class="form-control">
                <option value="">-- Select Project --</option>
                <?php
                $stmt = $db->prepare("SELECT id, project_name FROM projects ORDER BY project_name");
                $stmt->execute();
                $projects = $stmt->fetchAll();
                foreach ($projects as $p):
                ?>
                <option value="<?php echo $p['id']; ?>" <?php echo ($meeting['project_id'] ?? null) == $p['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($p['project_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin-bottom: 20px;">
        <div class="form-group">
            <label>Meeting Date *</label>
            <input type="date" name="meeting_date" class="form-control" value="<?php echo $meeting['meeting_date'] ?? ''; ?>" required>
        </div>

        <div class="form-group">
            <label>Meeting Time</label>
            <input type="time" name="meeting_time" class="form-control" value="<?php echo $meeting['meeting_time'] ?? ''; ?>">
        </div>

        <div class="form-group">
            <label>Follow-up Date</label>
            <input type="date" name="follow_up_date" class="form-control" value="<?php echo $meeting['follow_up_date'] ?? ''; ?>">
        </div>
    </div>

    <div class="form-group">
        <label>Attendees</label>
        <input type="text" name="attendees" class="form-control" placeholder="e.g., John, Sarah, Team Lead" value="<?php echo clean($meeting['attendees'] ?? ''); ?>">
    </div>

    <div class="form-group">
        <label>Agenda</label>
        <textarea name="agenda" class="form-control" rows="4"><?php echo clean($meeting['agenda'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label>Meeting Notes</label>
        <textarea name="notes" class="form-control" rows="4"><?php echo clean($meeting['notes'] ?? ''); ?></textarea>
    </div>

    <div class="form-group">
        <label>Action Items</label>
        <textarea name="action_items" class="form-control" rows="4" placeholder="List items to follow up on"><?php echo clean($meeting['action_items'] ?? ''); ?></textarea>
    </div>

    <div style="display: flex; gap: 10px;">
        <button type="submit" class="btn btn-primary" style="background: #6418C3; border: none;">
            <i class="fas fa-save"></i> <?php echo $meeting ? 'Update Meeting' : 'Schedule Meeting'; ?>
        </button>
        <a href="/EcomZone-CMS/admin/meetings/index.php" class="btn btn-secondary">
            <i class="fas fa-times"></i> Cancel
        </a>
    </div>
</form>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
