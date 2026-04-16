<?php
/**
 * SERVICE EXPIRY ALERTS - ENHANCED TABLE VIEW
 * With DataTables, Filters, Status Management, and WhatsApp Integration
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Service Alerts';

global $db;

// Create service_alerts table if not exists
$db->exec("
    CREATE TABLE IF NOT EXISTS `service_alerts` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `service_id` INT NOT NULL,
        `client_id` INT NOT NULL,
        `alert_type` VARCHAR(50) NOT NULL,
        `contact_method` VARCHAR(20) NOT NULL,
        `contact_value` VARCHAR(255) NOT NULL,
        `message_content` LONGTEXT,
        `status` VARCHAR(20) DEFAULT 'pending' COMMENT 'pending, sent, acknowledged, hidden',
        `alert_visibility` VARCHAR(20) DEFAULT 'show' COMMENT 'show, hide',
        `sent_at` TIMESTAMP NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (`service_id`) REFERENCES `project_services`(`id`) ON DELETE CASCADE,
        FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE
    )
");

// Add new columns to project_services if they don't exist
try {
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS last_alert_sent TIMESTAMP NULL");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS alert_count INT DEFAULT 0");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS renewed_at TIMESTAMP NULL");
    $db->exec("ALTER TABLE project_services ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
} catch (Exception $e) {
    // Columns may already exist
}

// Get all services with client info
$stmt = $db->prepare("
    SELECT 
        ps.id,
        ps.service_name,
        ps.created_at as issue_date,
        ps.expiry_date,
        ps.status,
        ps.last_alert_sent,
        ps.alert_count,
        ps.renewed_at,
        ps.updated_at,
        p.project_name,
        c.id as client_id,
        c.client_name,
        c.email,
        c.primary_phone
    FROM project_services ps
    LEFT JOIN projects p ON ps.project_id = p.id
    LEFT JOIN clients c ON p.client_id = c.id
    WHERE ps.status IN ('active', 'expired')
    ORDER BY ps.expiry_date ASC
");
$stmt->execute();
$services = $stmt->fetchAll();

// Calculate days left for each service
$servicesData = [];
foreach ($services as $service) {
    if (!$service['expiry_date']) continue;

    $today = date('Y-m-d');
    $expiryDate = date('Y-m-d', strtotime($service['expiry_date']));
    $daysLeft = (strtotime($expiryDate) - strtotime($today)) / (24 * 60 * 60);

    // Determine urgency/status
    if ($daysLeft < 0) {
        $urgency = 'expired';
        $statusLabel = 'Expired';
    }
    elseif ($daysLeft === 0) {
        $urgency = 'today';
        $statusLabel = 'Expiring Today';
    }
    elseif ($daysLeft <= 1) {
        $urgency = '1day';
        $statusLabel = 'Expiring Soon';
    }
    elseif ($daysLeft <= 3) {
        $urgency = '3days';
        $statusLabel = 'Expiring Soon';
    }
    elseif ($daysLeft <= 7) {
        $urgency = '7days';
        $statusLabel = 'Expiring Soon';
    }
    elseif ($daysLeft <= 15) {
        $urgency = '15days';
        $statusLabel = 'Expiring Soon';
    }
    elseif ($daysLeft <= 30) {
        $urgency = '30days';
        $statusLabel = 'Expiring Soon';
    } else {
        $urgency = 'normal';
        $statusLabel = 'Active';
    }

    $service['days_left'] = ceil($daysLeft);
    $service['urgency'] = $urgency;
    $service['expiry_status'] = $statusLabel;
    $servicesData[] = $service;
}

include __DIR__ . '/../../includes/header.php';
?>

<link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.dataTables.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

<style>
    .alerts-container {
        width: 100%;
        padding: 30px;
        background: #F4F4F4;
        min-height: calc(100vh - 60px);
    }

    .alerts-header {
        margin-bottom: 30px;
    }

    .alerts-header h1 {
        margin: 0 0 20px 0;
        font-size: 2rem;
        color: #1D1D1D;
        font-weight: 700;
    }

    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    }

    .filter-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
        align-items: center;
    }

    .filter-group {
        display: flex;
        flex-direction: column;
        gap: 5px;
    }

    .filter-group label {
        font-weight: 600;
        color: #1D1D1D;
        font-size: 0.9rem;
    }

    .filter-group input,
    .filter-group select {
        padding: 10px 12px;
        border: 1px solid #DDD;
        border-radius: 6px;
        font-size: 0.9rem;
    }

    .btn-filter {
        background: #6418C3;
        color: white;
        border: none;
        padding: 10px 20px;
        border-radius: 6px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        align-self: flex-end;
    }

    .btn-filter:hover {
        background: #5310a3;
    }

    .table-wrapper {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
        overflow-x: auto;
    }

    .dataTables_wrapper {
        width: 100%;
    }

    table.dataTable {
        width: 100% !important;
    }

    table.dataTable thead th {
        background: #6418C3;
        color: white;
        font-weight: 600;
        text-align: left;
        padding: 12px !important;
    }

    table.dataTable tbody td {
        padding: 12px !important;
        border-bottom: 1px solid #EEE;
    }

    table.dataTable tbody tr:hover {
        background: #f9f9f9;
    }

    .urgency-badge {
        display: inline-block;
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .urgency-expired {background: rgba(255, 94, 94, 0.2); color: #FF5E5E;}
    .urgency-today {background: rgba(255, 94, 94, 0.2); color: #FF5E5E;}
    .urgency-soon {background: rgba(255, 155, 82, 0.2); color: #FF9B52;}
    .urgency-normal {background: rgba(43, 193, 85, 0.2); color: #2BC155;}

    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }

    .btn-action {
        padding: 6px 12px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-size: 0.85rem;
        font-weight: 600;
        transition: all 0.3s ease;
    }

    .btn-view {
        background: #1EAAE7;
        color: white;
    }

    .btn-view:hover {
        background: #1a8cc9;
    }

    .btn-whatsapp {
        background: #25D366;
        color: white;
    }

    .btn-whatsapp:hover {
        background: #1FAB54;
    }

    .btn-hide {
        background: #999;
        color: white;
    }

    .btn-hide:hover {
        background: #777;
    }

    .status-select {
        padding: 6px 8px;
        border: 1px solid #DDD;
        border-radius: 4px;
        font-size: 0.85rem;
        cursor: pointer;
    }

    .dataTables_info {
        padding-top: 15px;
        font-size: 0.9rem;
        color: #666;
    }

    .dataTables_paginate {
        margin-top: 15px;
    }

    .paginate_button {
        padding: 6px 10px !important;
        margin: 0 2px !important;
        border-radius: 4px !important;
        background: #f0f0f0 !important;
        color: #333 !important;
    }

    .paginate_button.current {
        background: #6418C3 !important;
        color: white !important;
    }

    @media (max-width: 768px) {
        .alerts-container {
            margin-left: 0;
            padding: 15px;
        }

        .filter-row {
            grid-template-columns: 1fr;
        }

        .action-buttons {
            flex-direction: column;
        }

        .btn-action {
            width: 100%;
        }
    }
</style>

<div class="alerts-container">
    <div class="alerts-header">
        <h1><i class="fas fa-bell"></i> Service Expiry Alerts</h1>
    </div>

    <!-- FILTER SECTION -->
    <div class="filter-section">
        <div class="filter-row">
            <div class="filter-group">
                <label>From Date</label>
                <input type="text" id="filterDateFrom" class="datepicker" placeholder="Select start date">
            </div>

            <div class="filter-group">
                <label>To Date</label>
                <input type="text" id="filterDateTo" class="datepicker" placeholder="Select end date">
            </div>



            <button class="btn-filter" onclick="resetFilters()"><i class="fas fa-redo"></i> Reset</button>
        </div>
    </div>

    <!-- TABLE SECTION -->
    <div class="table-wrapper">
        <table id="alertsTable" class="display responsive nowrap">
            <thead>
                <tr>
                    <th>Urgency</th>
                    <th>Service Name</th>
                    <th>Project</th>
                    <th>Client Name</th>
                    <th>Issue Date</th>
                    <th>Expiry Date</th>
                    <th>Days Left</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($servicesData as $service): ?>
                <tr class="service-row" data-status="<?php echo $service['expiry_status']; ?>" data-expiry="<?php echo $service['expiry_date']; ?>">
                    <td class="urgency">
                        <?php 
                        if ($service['urgency'] === 'expired') echo '❌';
                        elseif ($service['urgency'] === 'today') echo '🔴';
                        elseif (in_array($service['urgency'], ['1day', '3days', '7days'])) echo '🟠';
                        elseif (in_array($service['urgency'], ['15days', '30days'])) echo '🟡';
                        else echo '🟢';
                        ?>
                    </td>
                    <td><strong><?php echo htmlspecialchars($service['service_name']); ?></strong></td>
                    <td>
                        <span style="background: #E8D7F1; color: #6418C3; padding: 6px 10px; border-radius: 6px; font-weight: 600; font-size: 0.9rem;">
                            📁 <?php echo htmlspecialchars($service['project_name'] ?? 'N/A'); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($service['client_name'] ?? 'N/A'); ?></td>
                    <td><?php echo formatDate($service['issue_date']); ?></td>
                    <td><strong><?php echo formatDate($service['expiry_date']); ?></strong></td>
                    <td>
                        <span class="urgency-badge urgency-<?php 
                            if ($service['urgency'] === 'expired') echo 'expired';
                            elseif (in_array($service['urgency'], ['today', '1day', '3days', '7days'])) echo 'soon';
                            else echo 'normal';
                        ?>">
                            <?php echo $service['days_left'] . ' days'; ?>
                        </span>
                    </td>
           
                    <td>
                        <div class="action-buttons">
                            <a href="<?php echo APP_URL; ?>/admin/alerts/view-service.php?id=<?php echo $service['id']; ?>" class="btn-action btn-view">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <a href="https://web.whatsapp.com/send?phone=<?php echo preg_replace('/[^0-9]/', '', $service['primary_phone']); ?>&text=Hi%20<?php echo urlencode($service['client_name']); ?>,%20Your%20service%20<?php echo urlencode($service['service_name']); ?>%20expires%20on%20<?php echo urlencode(formatDate($service['expiry_date'])); ?>" target="_blank" class="btn-action btn-whatsapp">
                                <i class="fab fa-whatsapp"></i> WhatsApp
                            </a>
                
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>



<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/responsive/2.5.0/js/dataTables.responsive.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>

<script>
let alertsTable;

$(document).ready(function() {
    // Initialize DataTable
    alertsTable = $('#alertsTable').DataTable({
        responsive: true,
        paging: true,
        pageLength: 10,
        searching: true,
        ordering: true,
        order: [[5, 'asc']], // Sort by expiry date (column 5)
        language: {
            search: "Search:",
            lengthMenu: "Show _MENU_ entries",
            info: "Showing _START_ to _END_ of _TOTAL_ entries"
        }
    });

    // Initialize date pickers
    flatpickr('.datepicker', {
        dateFormat: 'Y-m-d'
    });

    // Filter handlers
    $('#filterDateFrom, #filterDateTo, #filterStatus').on('change', function() {
        filterTable();
    });

    $('#filterSearch').on('keyup', function() {
        alertsTable.search(this.value).draw();
    });
});

function filterTable() {
    const dateFrom = $('#filterDateFrom').val();
    const dateTo = $('#filterDateTo').val();
    const status = $('#filterStatus').val();

    $.fn.dataTable.ext.search.push(function(settings, data, dataIndex) {
        const rowStatus = $(alertsTable.row(dataIndex).node()).data('status');
        const rowExpiry = $(alertsTable.row(dataIndex).node()).data('expiry');

        // Filter by status
        if (status && rowStatus !== status) return false;

        // Filter by date range
        if (dateFrom && rowExpiry < dateFrom) return false;
        if (dateTo && rowExpiry > dateTo) return false;

        return true;
    });

    alertsTable.draw();
}

function resetFilters() {
    $('#filterDateFrom').val('');
    $('#filterDateTo').val('');
    alertsTable.draw();
}



function updateStatus(serviceId, status) {
    fetch('<?php echo APP_URL; ?>/admin/api/update-alert-status.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({service_id: serviceId, status: status})
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Status Updated!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            }).then(() => {
                location.reload();
            });
        } else {
            Swal.fire({
                icon: 'error',
                title: 'Error!',
                text: data.message || 'Failed to update status'
            });
        }
    })
    .catch(err => {
        console.error(err);
        Swal.fire({
            icon: 'error',
            title: 'Error!',
            text: 'Failed to update status'
        });
    });
}

function hideAlert(serviceId) {
    Swal.fire({
        title: 'Hide Alert?',
        text: 'This alert will be hidden from the list (but kept in database)',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#6418C3',
        cancelButtonColor: '#999',
        confirmButtonText: 'Yes, Hide It!'
    }).then((result) => {
        if (result.isConfirmed) {
            fetch('<?php echo APP_URL; ?>/admin/api/hide-alert.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({service_id: serviceId})
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Hidden!',
                        text: data.message,
                        timer: 2000,
                        showConfirmButton: false
                    }).then(() => {
                        location.reload();
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Error!',
                        text: data.message || 'Failed to hide alert'
                    });
                }
            })
            .catch(err => {
                console.error(err);
                Swal.fire({
                    icon: 'error',
                    title: 'Error!',
                    text: 'Failed to hide alert'
                });
            });
        }
    });
}

function showNotification(message, type = 'info') {
    Swal.fire({
        icon: type === 'success' ? 'success' : 'info',
        title: type === 'success' ? 'Success!' : 'Info',
        text: message,
        timer: 2000,
        showConfirmButton: false
    });
}


</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
