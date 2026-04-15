<?php
/**
 * WHATSAPP MODULE - Configuration & Send Interface
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'WhatsApp';

global $db;

// Get recent WhatsApp logs
$stmt = $db->prepare("
    SELECT wl.*, c.name as client_name, c.phone as client_phone
    FROM whatsapp_logs wl
    LEFT JOIN clients c ON wl.client_id = c.id
    ORDER BY wl.created_at DESC
    LIMIT 100
");
$stmt->execute();
$logs = $stmt->fetchAll();

// Handle send request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } elseif ($_POST['action'] === 'send_message') {
        $clientId = (int)$_POST['client_id'];
        $message = $_POST['message'] ?? '';

        // Get client phone
        $stmt = $db->prepare("SELECT phone FROM clients WHERE id = ?");
        $stmt->execute([$clientId]);
        $client = $stmt->fetch();

        if ($client && $client['phone']) {
            // Log to database
            $stmt = $db->prepare("
                INSERT INTO whatsapp_logs (client_id, phone, message, status, created_at)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([$clientId, $client['phone'], $message, 'sent']);

            // Simulate API call (replace with real WhatsApp API)
            logActivity('CREATE', 'whatsapp', 0, "Message sent to {$client['phone']}");
            setFlash('success', 'Message sent successfully!');
        } else {
            setFlash('danger', 'Client phone number not found');
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .whatsapp-container {
        display: grid;
        grid-template-columns: 350px 1fr;
        gap: 25px;
    }

    .whatsapp-sidebar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        height: fit-content;
    }

    .whatsapp-main {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .whatsapp-status {
        background: rgba(37,211,102,0.1);
        border: 2px solid #25D366;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        text-align: center;
    }

    .whatsapp-status.inactive {
        background: rgba(255,94,94,0.1);
        border-color: #FF5E5E;
    }

    .status-indicator {
        display: inline-block;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        background: #25D366;
        margin-right: 8px;
        animation: pulse 2s infinite;
    }

    .status-indicator.inactive {
        background: #FF5E5E;
        animation: none;
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.5; }
    }

    .form-group {
        margin-bottom: 15px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 6px;
        color: #1D1D1D;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #25D366;
        box-shadow: 0 0 0 3px rgba(37,211,102,0.15);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 100px;
    }

    .send-btn {
        width: 100%;
        background: #25D366;
        color: white;
        padding: 12px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .send-btn:hover {
        background: #20c152;
        transform: translateY(-2px);
    }

    .message-log {
        max-height: 600px;
        overflow-y: auto;
    }

    .log-item {
        padding: 12px;
        border-bottom: 1px solid #f0f0f0;
        display: grid;
        grid-template-columns: 1fr;
        gap: 8px;
    }

    .log-item:last-child {
        border-bottom: none;
    }

    .log-client {
        font-weight: 600;
        color: #1D1D1D;
    }

    .log-message {
        color: #666;
        font-size: 0.9rem;
        padding: 8px;
        background: #f8f9ff;
        border-radius: 6px;
    }

    .log-time {
        font-size: 0.8rem;
        color: #999;
    }

    .log-status {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 4px;
        font-size: 0.75rem;
        font-weight: 600;
    }

    .log-status.sent {
        background: rgba(37,211,102,0.15);
        color: #25D366;
    }

    .log-status.pending {
        background: rgba(30,170,231,0.15);
        color: #1EAAE7;
    }

    .log-status.failed {
        background: rgba(255,94,94,0.15);
        color: #FF5E5E;
    }

    .tabs-nav {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        border-bottom: 2px solid #f0f0f0;
    }

    .tab-link {
        padding: 10px 0;
        border-bottom: 3px solid transparent;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        transition: all 0.3s ease;
    }

    .tab-link.active {
        color: #25D366;
        border-bottom-color: #25D366;
    }

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }

    @media (max-width: 768px) {
        .whatsapp-container {
            grid-template-columns: 1fr;
        }
    }
</style>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fab fa-whatsapp"></i> WhatsApp
</h1>

<?php echo flashAlert(); ?>

<div class="whatsapp-container">
    <!-- SIDEBAR -->
    <div class="whatsapp-sidebar">
        <?php 
        $enabled = getSetting('whatsapp_enabled') === '1';
        ?>
        <div class="whatsapp-status <?php echo !$enabled ? 'inactive' : ''; ?>">
            <span class="status-indicator <?php echo !$enabled ? 'inactive' : ''; ?>"></span>
            <div style="font-weight: 600; color: <?php echo $enabled ? '#25D366' : '#FF5E5E'; ?>">
                <?php echo $enabled ? 'Connected' : 'Not Connected'; ?>
            </div>
        </div>

        <form method="POST" action="/admin/settings/index.php">
            <?php echo csrfField(); ?>
            <input type="hidden" name="tab" value="whatsapp">

            <div class="form-group" style="margin-bottom: 20px;">
                <label>
                    <input type="checkbox" name="whatsapp_enabled" value="1" <?php echo $enabled ? 'checked' : ''; ?> onchange="this.form.submit();">
                    Enable WhatsApp
                </label>
            </div>

            <hr style="margin: 20px 0;">

            <h5 style="margin-bottom: 15px; font-weight: 600;">Connected Account</h5>
            <?php if ($enabled): ?>
            <div style="background: #f0f8f5; padding: 12px; border-radius: 8px; margin-bottom: 15px;">
                <p style="margin: 0 0 8px 0; font-size: 0.85rem; color: #666;">Business ID</p>
                <p style="margin: 0; font-weight: 600; word-break: break-all;">
                    <?php echo clean(substr(getSetting('whatsapp_business_id'), 0, 20)) . '...'; ?>
                </p>
            </div>
            <?php else: ?>
            <div style="background: #fff3cd; padding: 12px; border-radius: 8px; margin-bottom: 15px; text-align: center;">
                <p style="margin: 0; font-size: 0.9rem; color: #856404;">
                    <i class="fas fa-info-circle"></i> Configure in Settings
                </p>
            </div>
            <?php endif; ?>

            <a href="/admin/settings/index.php" class="btn" style="width: 100%; background: #1EAAE7; color: white; padding: 10px; border-radius: 8px; text-align: center; text-decoration: none; font-weight: 600;">
                <i class="fas fa-cog"></i> Configure
            </a>
        </form>
    </div>

    <!-- MAIN -->
    <div class="whatsapp-main">
        <?php if ($enabled): ?>
        <!-- SEND MESSAGE TAB -->
        <div class="tabs-nav">
            <div class="tab-link active" data-tab="send">
                <i class="fas fa-paper-plane"></i> Send Message
            </div>
            <div class="tab-link" data-tab="logs">
                <i class="fas fa-history"></i> Message Log
            </div>
        </div>

        <div id="send" class="tab-content active">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Send WhatsApp Message</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="action" value="send_message">

                <div class="form-group">
                    <label>Select Client *</label>
                    <select name="client_id" required onchange="updateClientPhone()">
                        <option value="">-- Choose Client --</option>
                        <?php
                        $stmt = $db->prepare("SELECT id, name, phone FROM clients WHERE phone IS NOT NULL AND phone != '' ORDER BY name");
                        $stmt->execute();
                        $clients = $stmt->fetchAll();
                        foreach ($clients as $client):
                        ?>
                        <option value="<?php echo $client['id']; ?>">
                            <?php echo clean($client['name']); ?> (<?php echo clean($client['phone']); ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="tel" id="clientPhone" readonly style="background: #f5f5f5;">
                </div>

                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" placeholder="Type your message..." required></textarea>
                </div>

                <div style="font-size: 0.85rem; color: #666; margin-bottom: 15px;">
                    <p><i class="fas fa-lightbulb"></i> <strong>Tips:</strong></p>
                    <ul style="margin: 5px 0; padding-left: 20px;">
                        <li>Keep messages concise and professional</li>
                        <li>Use &lt;br&gt; for line breaks</li>
                        <li>Click Send to deliver immediately</li>
                    </ul>
                </div>

                <button type="submit" class="send-btn">
                    <i class="fas fa-paper-plane"></i> Send Message
                </button>
            </form>
        </div>

        <div id="logs" class="tab-content">
            <h3 style="margin-top: 0; margin-bottom: 20px;">Recent Messages</h3>
            <div class="message-log">
                <?php if (empty($logs)): ?>
                <div style="text-align: center; padding: 40px; color: #999;">
                    <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                    No messages yet
                </div>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                    <div class="log-item">
                        <div class="log-client">
                            <i class="fab fa-whatsapp"></i> 
                            <?php echo clean($log['client_name'] ?? 'Unknown Client'); ?>
                        </div>
                        <div class="log-message"><?php echo clean($log['message']); ?></div>
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <span class="log-time"><?php echo timeAgo($log['created_at']); ?></span>
                            <span class="log-status <?php echo $log['status']; ?>">
                                <?php echo ucfirst($log['status']); ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <?php else: ?>
        <div style="text-align: center; padding: 60px 40px;">
            <div style="font-size: 3rem; color: #ddd; margin-bottom: 20px;">
                <i class="fab fa-whatsapp"></i>
            </div>
            <h4 style="color: #999; margin-bottom: 15px;">WhatsApp Not Connected</h4>
            <p style="color: #999; margin-bottom: 25px;">
                Configure your WhatsApp Business API credentials to send messages.
            </p>
            <a href="/admin/settings/index.php" class="btn" style="background: #25D366; color: white; padding: 12px 30px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block;">
                <i class="fas fa-cog"></i> Go to Settings
            </a>
        </div>
        <?php endif; ?>
    </div>
</div>

<script>
    function updateClientPhone() {
        const select = document.querySelector('select[name="client_id"]');
        const phone = select.options[select.selectedIndex].text.match(/\(([^)]+)\)/);
        document.getElementById('clientPhone').value = phone ? phone[1] : '';
    }

    // Tab switching
    document.querySelectorAll('.tab-link').forEach(link => {
        link.addEventListener('click', function() {
            const tabId = this.dataset.tab;
            document.querySelectorAll('.tab-link').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
            this.classList.add('active');
            document.getElementById(tabId).classList.add('active');
        });
    });
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
