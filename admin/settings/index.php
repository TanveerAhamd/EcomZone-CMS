<?php
/**
 * SETTINGS PAGE - All tabs
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin']);

$pageTitle = 'Settings';

global $db;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        $tab = $_POST['tab'] ?? 'general';
        
        try {
            if ($tab === 'general') {
                updateSetting('site_name', $_POST['site_name'] ?? '');
                updateSetting('site_tagline', $_POST['site_tagline'] ?? '');
                updateSetting('site_email', sanitizeEmail($_POST['site_email'] ?? ''));
                updateSetting('site_phone', $_POST['site_phone'] ?? '');
                updateSetting('currency', $_POST['currency'] ?? 'PKR');
                updateSetting('currency_symbol', $_POST['currency_symbol'] ?? '₨');
                updateSetting('timezone', $_POST['timezone'] ?? 'Asia/Karachi');
            } elseif ($tab === 'invoice') {
                updateSetting('invoice_prefix', $_POST['invoice_prefix'] ?? '');
                updateSetting('quotation_prefix', $_POST['quotation_prefix'] ?? '');
                updateSetting('default_tax_percent', $_POST['default_tax_percent'] ?? '0');
                updateSetting('invoice_footer_text', $_POST['invoice_footer_text'] ?? '');
            } elseif ($tab === 'whatsapp') {
                updateSetting('whatsapp_enabled', $_POST['whatsapp_enabled'] ?? '0');
                updateSetting('whatsapp_api_key', $_POST['whatsapp_api_key'] ?? '');
                updateSetting('whatsapp_phone_id', $_POST['whatsapp_phone_id'] ?? '');
                updateSetting('whatsapp_business_id', $_POST['whatsapp_business_id'] ?? '');
            } elseif ($tab === 'appearance') {
                updateSetting('primary_color', $_POST['primary_color'] ?? '#6418C3');
                updateSetting('theme_mode', $_POST['theme_mode'] ?? 'light');
            }
            
            setFlash('success', 'Settings updated successfully!');
        } catch (Exception $e) {
            setFlash('danger', 'Error: ' . $e->getMessage());
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .settings-container {
        display: grid;
        grid-template-columns: 200px 1fr;
        gap: 25px;
    }

    .settings-nav {
        background: white;
        border-radius: 12px;
        padding: 0;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        height: fit-content;
    }

    .settings-nav-item {
        display: block;
        padding: 15px 20px;
        border-left: 3px solid transparent;
        cursor: pointer;
        text-decoration: none;
        color: #666;
        transition: all 0.3s ease;
        font-weight: 600;
    }

    .settings-nav-item:hover {
        background: #f9f9f9;
        color: #6418C3;
    }

    .settings-nav-item.active {
        background: rgba(100,24,195,0.1);
        color: #6418C3;
        border-left-color: #6418C3;
    }

    .settings-panel {
        background: white;
        border-radius: 12px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        display: none;
    }

    .settings-panel.active {
        display: block;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 600;
        margin-bottom: 8px;
        color: #1D1D1D;
        font-size: 0.95rem;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
        width: 100%;
        padding: 10px 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        font-family: 'Poppins', sans-serif;
        font-size: 0.95rem;
    }

    .form-group input:focus,
    .form-group select:focus {
        outline: none;
        border-color: #6418C3;
        box-shadow: 0 0 0 3px rgba(100,24,195,0.15);
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .color-picker {
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .color-picker input[type="color"] {
        width: 50px;
        height: 40px;
        border: 1px solid #e0e0e0;
        border-radius: 6px;
        cursor: pointer;
    }

    .color-preview {
        width: 100px;
        height: 40px;
        border-radius: 6px;
        border: 1px solid #e0e0e0;
    }

    .btn-save {
        background: #6418C3;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-save:hover {
        background: #5910b8;
        transform: translateY(-2px);
    }

    .test-btn {
        background: #1EAAE7;
        color: white;
        padding: 8px 16px;
        border: none;
        border-radius: 6px;
        cursor: pointer;
        font-weight: 600;
    }

    @media (max-width: 768px) {
        .settings-container {
            grid-template-columns: 1fr;
        }

        .settings-nav {
            display: flex;
            gap: 0;
        }

        .settings-nav-item {
            flex: 1;
            border-left: none;
            border-bottom: 3px solid transparent;
            padding: 12px;
            text-align: center;
        }

        .settings-nav-item.active {
            border-left: none;
            border-bottom-color: #6418C3;
        }
    }
</style>

<h1 style="margin-bottom: 25px; font-weight: 700; font-size: 2rem;">
    <i class="fas fa-cog"></i> Settings
</h1>

<?php echo flashAlert(); ?>

<div class="settings-container">
    <!-- LEFT NAV -->
    <nav class="settings-nav">
        <a href="#general" class="settings-nav-item active" data-tab="general">
            <i class="fas fa-globe"></i> General
        </a>
        <a href="#invoice" class="settings-nav-item" data-tab="invoice">
            <i class="fas fa-receipt"></i> Invoice
        </a>
        <a href="#whatsapp" class="settings-nav-item" data-tab="whatsapp">
            <i class="fab fa-whatsapp"></i> WhatsApp
        </a>
        <a href="#appearance" class="settings-nav-item" data-tab="appearance">
            <i class="fas fa-palette"></i> Appearance
        </a>
        <a href="#permissions" class="settings-nav-item" data-tab="permissions">
            <i class="fas fa-lock"></i> Permissions
        </a>
    </nav>

    <!-- PANELS -->
    <div>
        <!-- GENERAL TAB -->
        <div id="general" class="settings-panel active">
            <h3 style="margin-top: 0;"><i class="fas fa-globe"></i> General Settings</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="tab" value="general">

                <div class="form-row">
                    <div class="form-group">
                        <label>Site Name</label>
                        <input type="text" name="site_name" value="<?php echo clean(getSetting('site_name', APP_NAME)); ?>">
                    </div>
                    <div class="form-group">
                        <label>Site Tagline</label>
                        <input type="text" name="site_tagline" value="<?php echo clean(getSetting('site_tagline', 'Project Management')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="site_email" value="<?php echo clean(getSetting('site_email', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Phone</label>
                        <input type="tel" name="site_phone" value="<?php echo clean(getSetting('site_phone', '')); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Currency</label>
                        <input type="text" name="currency" value="<?php echo clean(getSetting('currency', 'PKR')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Currency Symbol</label>
                        <input type="text" name="currency_symbol" value="<?php echo clean(getSetting('currency_symbol', '₨')); ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone">
                        <option value="Asia/Karachi" <?php echo getSetting('timezone') === 'Asia/Karachi' ? 'selected' : ''; ?>>Asia/Karachi</option>
                        <option value="Asia/Dubai" <?php echo getSetting('timezone') === 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai</option>
                        <option value="UTC" <?php echo getSetting('timezone') === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                        <option value="Europe/London" <?php echo getSetting('timezone') === 'Europe/London' ? 'selected' : ''; ?>>Europe/London</option>
                        <option value="America/New_York" <?php echo getSetting('timezone') === 'America/New_York' ? 'selected' : ''; ?>>America/New_York</option>
                    </select>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Save Changes</button>
            </form>
        </div>

        <!-- INVOICE TAB -->
        <div id="invoice" class="settings-panel">
            <h3 style="margin-top: 0;"><i class="fas fa-receipt"></i> Invoice Settings</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="tab" value="invoice">

                <div class="form-row">
                    <div class="form-group">
                        <label>Invoice Prefix</label>
                        <input type="text" name="invoice_prefix" value="<?php echo clean(getSetting('invoice_prefix', 'INV-')); ?>" placeholder="INV-">
                    </div>
                    <div class="form-group">
                        <label>Quotation Prefix</label>
                        <input type="text" name="quotation_prefix" value="<?php echo clean(getSetting('quotation_prefix', 'QUO-')); ?>" placeholder="QUO-">
                    </div>
                </div>

                <div class="form-group">
                    <label>Default Tax (%)</label>
                    <input type="number" name="default_tax_percent" value="<?php echo clean(getSetting('default_tax_percent', '5')); ?>" step="0.01">
                </div>

                <div class="form-group">
                    <label>Invoice Footer Text</label>
                    <textarea name="invoice_footer_text" rows="3"><?php echo clean(getSetting('invoice_footer_text', '')); ?></textarea>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Save Changes</button>
            </form>
        </div>

        <!-- WHATSAPP TAB -->
        <div id="whatsapp" class="settings-panel">
            <h3 style="margin-top: 0;"><i class="fab fa-whatsapp"></i> WhatsApp Integration</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="tab" value="whatsapp">

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="whatsapp_enabled" value="1" <?php echo getSetting('whatsapp_enabled') === '1' ? 'checked' : ''; ?>>
                        Enable WhatsApp Integration
                    </label>
                </div>

                <div class="form-group">
                    <label>API Key</label>
                    <input type="password" name="whatsapp_api_key" value="<?php echo clean(getSetting('whatsapp_api_key', '')); ?>" placeholder="Your WhatsApp API Key">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Phone Number ID</label>
                        <input type="text" name="whatsapp_phone_id" value="<?php echo clean(getSetting('whatsapp_phone_id', '')); ?>">
                    </div>
                    <div class="form-group">
                        <label>Business Account ID</label>
                        <input type="text" name="whatsapp_business_id" value="<?php echo clean(getSetting('whatsapp_business_id', '')); ?>">
                    </div>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Save Changes</button>
                <button type="button" class="test-btn" style="margin-left: 10px;">
                    <i class="fas fa-vial"></i> Test Connection
                </button>
            </form>
        </div>

        <!-- APPEARANCE TAB -->
        <div id="appearance" class="settings-panel">
            <h3 style="margin-top: 0;"><i class="fas fa-palette"></i> Appearance</h3>
            <form method="POST">
                <?php echo csrfField(); ?>
                <input type="hidden" name="tab" value="appearance">

                <div class="form-group">
                    <label>Primary Color</label>
                    <div class="color-picker">
                        <input type="color" name="primary_color" id="primaryColor" value="<?php echo clean(getSetting('primary_color', '#6418C3')); ?>" onchange="updateColorPreview()">
                        <div class="color-preview" id="colorPreview"></div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Theme Mode</label>
                    <select name="theme_mode">
                        <option value="light" <?php echo getSetting('theme_mode') === 'light' ? 'selected' : ''; ?>>Light</option>
                        <option value="dark" <?php echo getSetting('theme_mode') === 'dark' ? 'selected' : ''; ?>>Dark</option>
                    </select>
                </div>

                <button type="submit" class="btn-save"><i class="fas fa-check"></i> Save Changes</button>
            </form>
        </div>

        <!-- PERMISSIONS TAB -->
        <div id="permissions" class="settings-panel">
            <h3 style="margin-top: 0;"><i class="fas fa-lock"></i> Roles & Permissions</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Permission</th>
                        <th>Admin</th>
                        <th>Manager</th>
                        <th>Staff</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Manage Clients</td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled></td>
                    </tr>
                    <tr>
                        <td>Manage Projects</td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled></td>
                    </tr>
                    <tr>
                        <td>Manage Invoices</td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled></td>
                    </tr>
                    <tr>
                        <td>View Reports</td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled></td>
                    </tr>
                    <tr>
                        <td>System Settings</td>
                        <td><input type="checkbox" checked disabled></td>
                        <td><input type="checkbox" disabled></td>
                        <td><input type="checkbox" disabled></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    // Tab switching
    document.querySelectorAll('.settings-nav-item').forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            
            document.querySelectorAll('.settings-nav-item').forEach(l => l.classList.remove('active'));
            document.querySelectorAll('.settings-panel').forEach(p => p.classList.remove('active'));
            
            this.classList.add('active');
            const tab = this.dataset.tab;
            document.getElementById(tab).classList.add('active');
        });
    });

    function updateColorPreview() {
        const color = document.getElementById('primaryColor').value;
        document.getElementById('colorPreview').style.background = color;
    }

    // Initialize color preview
    updateColorPreview();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
