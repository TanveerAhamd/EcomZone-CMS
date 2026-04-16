<?php
/**
 * Meeting Notes Management
 * Add, view, and manage individual meeting notes for client meetings
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$action = isset($_POST['action']) ? htmlspecialchars($_POST['action']) : (isset($_GET['action']) ? htmlspecialchars($_GET['action']) : '');
$meetingId = isset($_POST['meeting_id']) ? (int)$_POST['meeting_id'] : 0;
$clientId = isset($_POST['client_id']) ? (int)$_POST['client_id'] : 0;
$noteIndex = isset($_POST['note_index']) ? (int)$_POST['note_index'] : 0;
$note = isset($_POST['note']) ? trim($_POST['note']) : '';

global $db;

// Get meeting to verify client access
$stmt = $db->prepare("SELECT * FROM meetings WHERE id = ?");
$stmt->execute([$meetingId]);
$meeting = $stmt->fetch();

if (!$meeting) {
    echo json_encode(['success' => false, 'error' => 'Meeting not found']);
    exit;
}

// Helper function to parse notes from string
function parseNotes($notesString) {
    if (empty($notesString)) return [];
    return array_filter(array_map('trim', explode("\n", $notesString)));
}

// Helper function to save notes to string
function saveNotes($notesArray) {
    return implode("\n", array_filter($notesArray));
}

switch ($action) {
    case 'add_note':
        // Add a new note
        if (empty($note)) {
            echo json_encode(['success' => false, 'error' => 'Note cannot be empty']);
            exit;
        }

        $currentNotes = parseNotes($meeting['notes']);
        $currentNotes[] = $note;
        $updatedNotes = saveNotes($currentNotes);

        $stmt = $db->prepare("UPDATE meetings SET notes = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$updatedNotes, $meetingId]);

        echo json_encode($result ? 
            ['success' => true, 'message' => 'Note added successfully'] :
            ['success' => false, 'error' => 'Failed to add note']
        );
        break;

    case 'edit_note':
        // Edit an existing note
        if (empty($note)) {
            echo json_encode(['success' => false, 'error' => 'Note cannot be empty']);
            exit;
        }

        $currentNotes = parseNotes($meeting['notes']);
        if (!isset($currentNotes[$noteIndex])) {
            echo json_encode(['success' => false, 'error' => 'Note not found']);
            exit;
        }

        $currentNotes[$noteIndex] = $note;
        $updatedNotes = saveNotes($currentNotes);

        $stmt = $db->prepare("UPDATE meetings SET notes = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$updatedNotes, $meetingId]);

        echo json_encode($result ? 
            ['success' => true, 'message' => 'Note updated successfully'] :
            ['success' => false, 'error' => 'Failed to update note']
        );
        break;

    case 'delete_note':
        // Delete a specific note
        $currentNotes = parseNotes($meeting['notes']);
        if (!isset($currentNotes[$noteIndex])) {
            echo json_encode(['success' => false, 'error' => 'Note not found']);
            exit;
        }

        unset($currentNotes[$noteIndex]);
        $currentNotes = array_values($currentNotes); // Re-index array
        $updatedNotes = saveNotes($currentNotes);

        $stmt = $db->prepare("UPDATE meetings SET notes = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$updatedNotes, $meetingId]);

        echo json_encode($result ? 
            ['success' => true, 'message' => 'Note deleted successfully'] :
            ['success' => false, 'error' => 'Failed to delete note']
        );
        break;

    case 'delete_meeting':
        // Delete entire meeting
        $stmt = $db->prepare("DELETE FROM meetings WHERE id = ?");
        $result = $stmt->execute([$meetingId]);

        echo json_encode($result ? 
            ['success' => true, 'message' => 'Meeting deleted successfully'] :
            ['success' => false, 'error' => 'Failed to delete meeting']
        );
        break;

    case 'save_notes':
        // Legacy: Save entire notes string
        if (empty($note) && empty($_POST['notes'])) {
            echo json_encode(['success' => false, 'error' => 'Notes cannot be empty']);
            exit;
        }

        $notesText = !empty($note) ? $note : $_POST['notes'];
        $stmt = $db->prepare("UPDATE meetings SET notes = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$notesText, $meetingId]);

        echo json_encode($result ? 
            ['success' => true, 'message' => 'Meeting notes saved successfully'] :
            ['success' => false, 'error' => 'Failed to save notes']
        );
        break;

    case 'get_notes':
        // Get meeting notes
        echo json_encode([
            'success' => true,
            'notes' => $meeting['notes'] ?? '',
            'meeting' => $meeting
        ]);
        break;

    default:
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
}
?>
