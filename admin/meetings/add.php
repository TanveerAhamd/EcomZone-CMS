<?php
/**
 * MEETINGS - ADD/EDIT PAGE (MODERN REDESIGN)
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
            $agenda = $_POST['agenda'] ?? '';
            $notes = $_POST['notes'] ?? '';
            
            // Convert empty strings to NULL for foreign keys
            $client_id = !empty($client_id) ? $client_id : null;
            $project_id = !empty($project_id) ? $project_id : null;
            
            if (!$title) {
                setFlash('danger', 'Meeting title is required');
            } else {
                if ($id) {
                    $stmt = $db->prepare("
                        UPDATE meetings SET 
                            title = ?, client_id = ?, project_id = ?, agenda = ?, notes = ?, updated_at = NOW()
                        WHERE id = ?
                    ");
                    $stmt->execute([$title, $client_id, $project_id, $agenda, $notes, $id]);
                    logActivity('UPDATE', 'meetings', $id, "Meeting updated");
                    setFlash('success', 'Meeting updated successfully!');
                } else {
                    $stmt = $db->prepare("
                        INSERT INTO meetings (user_id, title, client_id, project_id, agenda, notes, created_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ");
                    $stmt->execute([currentUser()['id'], $title, $client_id, $project_id, $agenda, $notes]);
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

// Get clients for dropdown
$stmt = $db->prepare("SELECT id, client_name FROM clients ORDER BY client_name");
$stmt->execute();
$clients = $stmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div style="background: white; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.08); padding: 30px; max-width: 600px;">
    <h2 style="margin: 0 0 25px 0; font-weight: 700; color: #1a202c;">
        <i class="fas fa-calendar-plus" style="color: #6418C3; margin-right: 10px;"></i>
        <?php echo $id ? 'Edit Meeting' : 'Create Meeting'; ?>
    </h2>

    <?php echo flashAlert(); ?>

    <form method="POST">
        <?php echo csrfField(); ?>

        <!-- Meeting Title -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Meeting Title *</label>
            <input type="text" name="title" class="form-control" 
                   value="<?php echo clean($meeting['title'] ?? ''); ?>" 
                   placeholder="Enter meeting title" required>
        </div>

        <!-- Client Dropdown -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Client</label>
            <select name="client_id" id="clientSelect" class="form-control" onchange="loadProjects()">
                <option value="">-- Select Client --</option>
                <?php foreach ($clients as $c): ?>
                <option value="<?php echo $c['id']; ?>" <?php echo ($meeting['client_id'] ?? null) == $c['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($c['client_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>

        <!-- Project Dropdown (AJAX) -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Project</label>
            <select name="project_id" id="projectSelect" class="form-control">
                <option value="">-- Select Project --</option>
                <?php
                if ($meeting && $meeting['client_id']) {
                    $stmt = $db->prepare("SELECT id, project_name FROM projects WHERE client_id = ? ORDER BY project_name");
                    $stmt->execute([$meeting['client_id']]);
                    $projects = $stmt->fetchAll();
                    foreach ($projects as $p):
                ?>
                <option value="<?php echo $p['id']; ?>" <?php echo ($meeting['project_id'] ?? null) == $p['id'] ? 'selected' : ''; ?>>
                    <?php echo clean($p['project_name']); ?>
                </option>
                <?php
                    endforeach;
                }
                ?>
            </select>
        </div>

        <!-- Agenda -->
        <div class="form-group" style="margin-bottom: 20px;">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Agenda</label>
            <textarea name="agenda" class="form-control" rows="4" 
                      placeholder="What will be discussed in this meeting?"><?php echo clean($meeting['agenda'] ?? ''); ?></textarea>
        </div>

        <!-- Meeting Notes -->
        <div class="form-group" style="margin-bottom: 25px;">
            <label style="font-weight: 600; margin-bottom: 8px; display: block;">Meeting Notes</label>
            <textarea name="notes" class="form-control" rows="4" 
                      placeholder="Key discussion points and outcomes..."><?php echo clean($meeting['notes'] ?? ''); ?></textarea>
        </div>

        <!-- Buttons -->
        <div style="display: flex; gap: 10px;">
            <button type="submit" class="btn" style="background: #6418C3; color: white; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer;">
                <i class="fas fa-save"></i> <?php echo $id ? 'Update' : 'Create'; ?>
            </button>
            <a href="/EcomZone-CMS/admin/meetings/index.php" class="btn" style="background: #f0f0f0; color: #1a202c; padding: 10px 25px; border-radius: 6px; border: none; font-weight: 600; cursor: pointer; text-decoration: none;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
function loadProjects() {
    const clientId = document.getElementById('clientSelect').value;
    const projectSelect = document.getElementById('projectSelect');
    
    if (!clientId) {
        projectSelect.innerHTML = '<option value="">-- Select Project --</option>';
        return;
    }
    
    fetch('/EcomZone-CMS/admin/meetings/get-projects.php?client_id=' + clientId)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.projects) {
                let options = '<option value="">-- Select Project --</option>';
                data.projects.forEach(project => {
                    options += `<option value="${project.id}">${project.project_name}</option>`;
                });
                projectSelect.innerHTML = options;
            }
        })
        .catch(error => console.error('Error loading projects:', error));
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
