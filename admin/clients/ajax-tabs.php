<?php

/**
 * AJAX Endpoints for Client Profile Tabs
 * Returns JSON data for dynamic tab loading
 */

require_once __DIR__ . '/../../includes/init.php';
requireLogin();

header('Content-Type: application/json');

$clientId = isset($_GET['client_id']) ? (int)$_GET['client_id'] : 0;
$tab = isset($_GET['tab']) ? htmlspecialchars($_GET['tab'], ENT_QUOTES, 'UTF-8') : '';

if (!$clientId || !$tab) {
    echo json_encode(['error' => 'Invalid request']);
    exit;
}

global $db;

// Verify client exists and user has access
$stmt = $db->prepare("SELECT id FROM clients WHERE id = ?");
$stmt->execute([$clientId]);
if (!$stmt->fetch()) {
    echo json_encode(['error' => 'Client not found']);
    exit;
}

switch ($tab) {
    case 'projects':
        handleProjects($db, $clientId);
        break;
    case 'services':
        handleServices($db, $clientId);
        break;
    case 'invoices':
        handleInvoices($db, $clientId);
        break;
    case 'payments':
        handlePayments($db, $clientId);
        break;
    case 'quotations':
        handleQuotations($db, $clientId);
        break;
    case 'meetings':
        handleMeetings($db, $clientId);
        break;
    case 'documents':
        handleDocuments($db, $clientId);
        break;
    default:
        echo json_encode(['error' => 'Unknown tab']);
}

// ============ PROJECTS ============
function handleProjects($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT p.*, sc.category_name, u.name as assigned_user
        FROM projects p
        LEFT JOIN service_categories sc ON p.service_category_id = sc.id
        LEFT JOIN users u ON p.assigned_to = u.id
        WHERE p.client_id = ?
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([$clientId]);
    $projects = $stmt->fetchAll();

    if (count($projects) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No projects</p></div>']);
        return;
    }

    $html = '<table class="table"><thead><tr><th>Project</th><th>Code</th><th>Category</th><th>Assigned</th><th>Deadline</th><th>Progress</th><th>Status</th><th>Action</th></tr></thead><tbody>';

    foreach ($projects as $p) {
        $stmt2 = $db->prepare("SELECT COUNT(*) as total, SUM(status='done') as done FROM tasks WHERE project_id = ?");
        $stmt2->execute([$p['id']]);
        $pd = $stmt2->fetch();
        $progress = $pd['total'] > 0 ? round(($pd['done'] / $pd['total']) * 100) : 0;

        $html .= '<tr>
            <td><strong>' . clean($p['project_name']) . '</strong></td>
            <td><code style="background: #f0f0f0; padding: 3px 6px; border-radius: 3px; font-size: 0.8rem;">' . clean($p['project_code']) . '</code></td>
            <td><span style="display: inline-block; background: #E8D5FF; color: #6418C3; padding: 3px 8px; border-radius: 3px; font-size: 0.8rem;">' . clean($p['category_name'] ?? 'N/A') . '</span></td>
            <td><small>' . clean($p['assigned_user'] ?? 'Unassigned') . '</small></td>
            <td>' . formatDate($p['deadline']) . '</td>
            <td>
                <div style="display: flex; align-items: center; gap: 8px;">
                    <div style="flex: 1; background: #f0f0f0; height: 6px; border-radius: 3px; overflow: hidden;">
                        <div style="width: ' . $progress . '%; height: 100%; background: ' . getProgressColor($progress) . '; transition: width 0.3s ease;"></div>
                    </div>
                    <small style="min-width: 30px; text-align: right;">' . $progress . '%</small>
                </div>
            </td>
            <td>' . statusBadge($p['status']) . '</td>
            <td><a href="' . APP_URL . '/admin/projects/view.php?id=' . $p['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-arrow-right"></i></a></td>
        </tr>';
    }

    $html .= '</tbody></table>';
    echo json_encode(['html' => $html]);
}

// ============ SERVICES ============
function handleServices($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT ps.*, p.project_name, p.project_code
        FROM project_services ps
        JOIN projects p ON ps.project_id = p.id
        WHERE p.client_id = ?
        ORDER BY ps.expiry_date ASC
    ");
    $stmt->execute([$clientId]);
    $services = $stmt->fetchAll();

    if (count($services) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No services</p></div>']);
        return;
    }

    $html = '';
    $groupedByProject = [];
    foreach ($services as $svc) {
        if (!isset($groupedByProject[$svc['project_id']])) {
            $groupedByProject[$svc['project_id']] = [
                'project_name' => $svc['project_name'],
                'project_code' => $svc['project_code'],
                'items' => []
            ];
        }
        $groupedByProject[$svc['project_id']]['items'][] = $svc;
    }

    foreach ($groupedByProject as $proj) {
        $html .= '<h6 style="margin: 20px 0 15px 0; padding: 10px; background: #f8f9ff; border-radius: 6px; border-left: 3px solid #6418C3;">
            📦 ' . clean($proj['project_name']) . ' <code style="font-size: 0.75rem; opacity: 0.7;">[' . clean($proj['project_code']) . ']</code>
        </h6>';

        foreach ($proj['items'] as $svc) {
            $daysLeft = $svc['expiry_date'] ? (strtotime($svc['expiry_date']) - time()) / (24 * 60 * 60) : 999;
            if ($daysLeft < 0) {
                $statusColor = '#FF5E5E';
                $statusText = '⚠️ EXPIRED';
            } elseif ($daysLeft < 7) {
                $statusColor = '#FF9B52';
                $statusText = '⏰ EXPIRING SOON (' . round($daysLeft) . 'd)';
            } elseif ($daysLeft < 30) {
                $statusColor = '#FFD700';
                $statusText = '📅 EXPIRING (' . round($daysLeft) . 'd)';
            } else {
                $statusColor = '#2BC155';
                $statusText = '✓ ACTIVE (' . round($daysLeft) . 'd)';
            }

            $html .= '<div class="service-card" style="border-left-color: ' . $statusColor . ';">
                <div class="service-header">
                    <div style="flex: 1;">
                        <p class="service-name"><i class="fas fa-cog"></i> ' . clean($svc['service_name']) . '</p>
                    </div>
                    <span style="display: inline-block; background: ' . $statusColor . '; color: white; padding: 6px 12px; border-radius: 4px; font-size: 0.8rem; font-weight: 600; white-space: nowrap;">
                        ' . $statusText . '
                    </span>
                </div>
                <div class="service-dates">
                    <strong>Started:</strong> ' . formatDate($svc['start_date']) . ' &nbsp;&nbsp;
                    <strong>Expires:</strong> ' . formatDate($svc['expiry_date']) . '
                </div>
                <div class="service-price">💰 ' . formatCurrency($svc['price']) . '</div>
            </div>';
        }
    }

    echo json_encode(['html' => $html]);
}

// ============ INVOICES ============
function handleInvoices($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT i.*, p.project_name, p.project_code
        FROM invoices i
        LEFT JOIN projects p ON i.project_id = p.id
        WHERE i.client_id = ?
        ORDER BY i.created_at DESC
    ");
    $stmt->execute([$clientId]);
    $invoices = $stmt->fetchAll();

    if (count($invoices) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No invoices</p></div>']);
        return;
    }

    $inv_total = array_sum(array_column($invoices, 'total'));
    $inv_paid = array_sum(array_column($invoices, 'paid_amount'));
    $inv_balance = $inv_total - $inv_paid;

    $html = '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
        <div style="background: linear-gradient(135deg, rgba(30,170,231,0.1), rgba(26,156,231,0.1)); border-left: 3px solid #1EAAE7; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Total Invoiced</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #1EAAE7;">' . formatCurrency($inv_total) . '</div>
        </div>
        <div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Total Paid</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #2BC155;">' . formatCurrency($inv_paid) . '</div>
        </div>
        <div style="background: linear-gradient(135deg, rgba(255,155,82,0.1), rgba(255,107,53,0.1)); border-left: 3px solid #FF9B52; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Outstanding</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #FF9B52;">' . formatCurrency($inv_balance) . '</div>
        </div>
    </div>';

    $html .= '<table class="table"><thead><tr><th>Invoice #</th><th>Project</th><th>Date</th><th>Amount</th><th>Paid</th><th>Balance</th><th>Status</th><th>Action</th></tr></thead><tbody>';

    foreach ($invoices as $inv) {
        $html .= '<tr>
            <td><strong>' . clean($inv['invoice_number']) . '</strong></td>
            <td><small>' . ($inv['project_name'] ? clean($inv['project_name']) . ' [' . clean($inv['project_code']) . ']' : '-') . '</small></td>
            <td>' . formatDate($inv['issue_date']) . '</td>
            <td>' . formatCurrency($inv['total']) . '</td>
            <td style="color: #2BC155; font-weight: 600;">' . formatCurrency($inv['paid_amount']) . '</td>
            <td style="color: ' . ($inv['total'] - $inv['paid_amount'] > 0 ? '#FF9B52' : '#2BC155') . '; font-weight: 600;">' . formatCurrency($inv['total'] - $inv['paid_amount']) . '</td>
            <td>' . statusBadge($inv['status']) . '</td>
            <td><a href="' . APP_URL . '/admin/invoices/view.php?id=' . $inv['id'] . '" class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></a></td>
        </tr>';
    }

    $html .= '</tbody></table>';
    echo json_encode(['html' => $html]);
}

// ============ PAYMENTS ============
function handlePayments($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT p.*, i.invoice_number 
        FROM payments p
        LEFT JOIN invoices i ON p.invoice_id = i.id
        WHERE p.client_id = ?
        ORDER BY p.payment_date DESC
    ");
    $stmt->execute([$clientId]);
    $payments = $stmt->fetchAll();

    if (count($payments) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No payments</p></div>']);
        return;
    }

    $total_payments = array_sum(array_column($payments, 'amount'));

    $html = '<div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
        <strong>Total Payments Received: ' . formatCurrency($total_payments) . '</strong>
        <br><small style="color: #999;">Last Payment: ' . formatDate($payments[0]['payment_date']) . '</small>
    </div>';

    $html .= '<table class="table"><thead><tr><th>Payment #</th><th>Invoice</th><th>Date</th><th>Amount</th><th>Method</th><th>Reference</th></tr></thead><tbody>';

    $methods = [
        'cash' => '💵 Cash',
        'check' => '🏧 Check',
        'bank_transfer' => '🏦 Bank Transfer',
        'credit_card' => '💳 Card',
        'online' => '🌐 Online'
    ];

    foreach ($payments as $pay) {
        $html .= '<tr>
            <td><strong>' . clean($pay['payment_number']) . '</strong></td>
            <td>' . clean($pay['invoice_number']) . '</td>
            <td>' . formatDate($pay['payment_date']) . '</td>
            <td style="font-weight: 600; color: #2BC155;">' . formatCurrency($pay['amount']) . '</td>
            <td><span style="display: inline-block; background: #f0f0f0; padding: 3px 8px; border-radius: 3px; font-size: 0.8rem;">' . ($methods[$pay['payment_method']] ?? ucfirst(str_replace('_', ' ', $pay['payment_method']))) . '</span></td>
            <td><small>' . clean($pay['transaction_id'] ?? '-') . '</small></td>
        </tr>';
    }

    $html .= '</tbody></table>';
    echo json_encode(['html' => $html]);
}

// ============ QUOTATIONS ============
function handleQuotations($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT * FROM quotations
        WHERE client_id = ?
        ORDER BY created_at DESC
    ");
    $stmt->execute([$clientId]);
    $quotations = $stmt->fetchAll();

    if (count($quotations) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No quotations</p></div>']);
        return;
    }

    $quo_total = array_sum(array_column($quotations, 'total'));
    $quo_accepted = count(array_filter($quotations, fn($q) => $q['status'] === 'accepted'));
    $quo_pending = count(array_filter($quotations, fn($q) => $q['status'] === 'pending'));

    $html = '<div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; margin-bottom: 20px;">
        <div style="background: linear-gradient(135deg, rgba(100,24,195,0.1), rgba(155,89,182,0.1)); border-left: 3px solid #6418C3; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Total Value</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #6418C3;">' . formatCurrency($quo_total) . '</div>
        </div>
        <div style="background: linear-gradient(135deg, rgba(43,193,85,0.1), rgba(39,174,96,0.1)); border-left: 3px solid #2BC155; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Accepted</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #2BC155;">' . $quo_accepted . '</div>
        </div>
        <div style="background: linear-gradient(135deg, rgba(255,193,7,0.1), rgba(255,152,0,0.1)); border-left: 3px solid #FFD700; padding: 15px; border-radius: 8px;">
            <small style="color: #666; font-weight: 600;">Pending</small>
            <div style="font-size: 1.5rem; font-weight: 700; color: #FFD700;">' . $quo_pending . '</div>
        </div>
    </div>';

    $html .= '<table class="table"><thead><tr><th>Quotation #</th><th>Date</th><th>Valid Until</th><th>Amount</th><th>Status</th><th>Days Valid</th></tr></thead><tbody>';

    foreach ($quotations as $quo) {
        $daysValid = ($quo['valid_until'] ? (strtotime($quo['valid_until']) - time()) / (24 * 60 * 60) : 0);

        $html .= '<tr>
            <td><strong>' . clean($quo['quotation_number']) . '</strong></td>
            <td>' . formatDate($quo['issue_date']) . '</td>
            <td>' . formatDate($quo['valid_until']) . '</td>
            <td>' . formatCurrency($quo['total']) . '</td>
            <td>' . statusBadge($quo['status']) . '</td>
            <td>';

        if ($daysValid > 0) {
            $html .= '<span style="color: ' . ($daysValid < 7 ? '#FF9B52' : '#2BC155') . '; font-weight: 600;">' . round($daysValid) . ' days</span>';
        } else {
            $html .= '<span style="color: #999;"><em>Expired</em></span>';
        }

        $html .= '</td></tr>';
    }

    $html .= '</tbody></table>';
    echo json_encode(['html' => $html]);
}

// ============ MEETINGS ============
function handleMeetings($db, $clientId)
{
    $stmt = $db->prepare("
        SELECT m.*
        FROM meetings m
        WHERE m.client_id = ?
        ORDER BY m.meeting_date DESC
    ");
    $stmt->execute([$clientId]);
    $meetings = $stmt->fetchAll();

    if (count($meetings) === 0) {
        echo json_encode(['html' => '<div class="empty-state"><i class="fas fa-inbox empty-state-icon"></i><p>No meetings scheduled</p></div>']);
        return;
    }

    $html = '<div class="timeline">';

    foreach ($meetings as $mtg) {
        $isFuture = strtotime($mtg['meeting_date']) > time();
        $daysDiff = (strtotime($mtg['meeting_date']) - time()) / (24 * 60 * 60);

        $hasNotes = !empty($mtg['notes']);

        $html .= '<div class="timeline-item" style="position: relative; padding: 15px; background: #f9f9f9; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid ' . ($isFuture ? '#6418C3' : '#999') . ';">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 12px;">
                <div style="flex: 1;">
                    <p class="timeline-title" style="color: ' . ($isFuture ? '#1D1D1D' : '#999') . '; margin: 0 0 5px 0;">
                        ' . clean($mtg['title']) . '
                        ' . ($isFuture ? '<span style="display: inline-block; background: #6418C3; color: white; padding: 3px 10px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; margin-left: 10px;">UPCOMING (' . round($daysDiff) . 'd)</span>' : '<span style="display: inline-block; background: #999; color: white; padding: 3px 10px; border-radius: 3px; font-size: 0.7rem; font-weight: 600; margin-left: 10px;">PAST</span>') . '
                    </p>
                    <p class="timeline-description" style="margin: 0 0 8px 0;">
                        <i class="fas fa-calendar"></i> ' . formatDate($mtg['meeting_date']) . '
                        ' . ($mtg['meeting_time'] ? '@ <strong>' . date('H:i', strtotime($mtg['meeting_time'])) . '</strong>' : '') . '
                        ' . ($mtg['location'] ? '<br><i class="fas fa-map-marker-alt"></i> ' . clean($mtg['location']) : '') . '
                    </p>
                </div>
                <button onclick="toggleMeetingNotes(' . $mtg['id'] . ')" style="background: #1EAAE7; color: white; border: none; padding: 5px 12px; border-radius: 4px; cursor: pointer; font-weight: 600; font-size: 0.75rem; white-space: nowrap;">
                    <i class="fas fa-sticky-note"></i> ' . ($hasNotes ? 'View' : 'Add') . '
                </button>
            </div>
            
            <div id="notes-section-' . $mtg['id'] . '" style="display: none; margin-top: 15px; padding-top: 15px; border-top: 1px solid #e0e0e0;">
                <textarea id="notes-content-' . $mtg['id'] . '" placeholder="Add meeting notes..." style="width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 6px; font-family: Arial; font-size: 0.9rem; resize: vertical; min-height: 120px;" data-meeting-id="' . $mtg['id'] . '">' . clean($mtg['notes'] ?? '') . '</textarea>
                <div style="display: flex; gap: 10px; margin-top: 12px;">
                    <button onclick="saveMeetingNotes(' . $mtg['id'] . ')" style="flex: 1; background: #2BC155; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-save"></i> Save Notes
                    </button>
                    <button onclick="toggleMeetingNotes(' . $mtg['id'] . ')" style="flex: 1; background: #999; color: white; border: none; padding: 10px; border-radius: 6px; cursor: pointer; font-weight: 600;">
                        <i class="fas fa-times"></i> Close
                    </button>
                </div>
            </div>
        </div>';
    }

    $html .= '</div>';
    echo json_encode(['html' => $html]);
}

// Helper function to get progress color
function getProgressColor($progress) {
    if ($progress >= 75) return '#2BC155';
    if ($progress >= 50) return '#FFD700';
    if ($progress >= 25) return '#FF9B52';
    return '#FF5E5E';
}

// ============ DOCUMENTS ============
function handleDocuments($db, $clientId)
{
    $stmt = $db->prepare("SELECT * FROM client_documents WHERE client_id = ? ORDER BY uploaded_at DESC");
    $stmt->execute([$clientId]);
    $documents = $stmt->fetchAll();

    if (count($documents) === 0) {
        echo json_encode(['html' => '<p style="text-align: center; color: #888; padding: 20px;">No documents uploaded</p>']);
        return;
    }

    $html = '<table class="table table-striped table-hover">
        <thead class="table-dark">
            <tr>
                <th>📄 Title</th>
                <th>📅 Date</th>
                <th>💾 Size</th>
                <th style="text-align: center;">⚙️ Actions</th>
            </tr>
        </thead>
        <tbody>';

    foreach ($documents as $doc) {
        $filePath = '/uploads/client-documents/' . $doc['file_name'];
        $fullPath = $_SERVER['DOCUMENT_ROOT'] . $filePath;
        $fileExists = file_exists($fullPath);
        $fileSize = $fileExists ? filesize($fullPath) : 0;
        $sizeText = $fileSize > 1048576 ? round($fileSize / 1048576, 2) . ' MB' : round($fileSize / 1024, 2) . ' KB';
        $date = date('d M Y', strtotime($doc['uploaded_at']));
        
        $html .= '<tr>
            <td><strong>' . htmlspecialchars($doc['original_name']) . '</strong></td>
            <td>' . $date . '</td>
            <td><strong>' . $sizeText . '</strong></td>
            <td style="text-align: center;">';
        
        // Download button
        if ($fileExists) {
            $html .= '<a href="' . APP_URL . $filePath . '" class="btn btn-sm btn-success me-2" download><i class="fas fa-download"></i></a>';
        }
        
        // View button
        $html .= '<button class="btn btn-sm btn-info me-2" onclick="viewDoc(' . $doc['id'] . ')"><i class="fas fa-eye"></i></button>';
        
        // Edit button
        $html .= '<button class="btn btn-sm btn-warning me-2" onclick="editDoc(' . $doc['id'] . ')"><i class="fas fa-edit"></i></button>';
        
        // Delete button
        $html .= '<button class="btn btn-sm btn-danger" onclick="deleteDoc(' . $doc['id'] . ')"><i class="fas fa-trash"></i></button>';
        
        $html .= '</td></tr>';
    }

    $html .= '</tbody></table>';
    echo json_encode(['html' => $html]);
}
