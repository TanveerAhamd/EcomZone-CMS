<?php
/**
 * CLIENT ADD/EDIT PAGE
 * 3-column form layout with sidebar info
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();
requireRole(['admin', 'manager']);

$pageTitle = 'Add/Edit Client';
$client = null;
$isEdit = false;

global $db;

// Check if editing
if (isset($_GET['id'])) {
    $clientId = (int)$_GET['id'];
    $stmt = $db->prepare("SELECT * FROM clients WHERE id = :id");
    $stmt->execute([':id' => $clientId]);
    $client = $stmt->fetch();
    
    if (!$client) {
        setFlash('danger', 'Client not found');
        redirect('admin/clients/index.php');
    }
    
    $isEdit = true;
    $pageTitle = 'Edit Client: ' . clean($client['client_name']);
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? '')) {
        setFlash('danger', 'Security token expired');
    } else {
        $clientName = clean($_POST['client_name'] ?? '');
        $email = sanitizeEmail($_POST['email'] ?? '');
        $primaryPhone = clean($_POST['primary_phone'] ?? '');
        $secondaryPhone = clean($_POST['secondary_phone'] ?? '');
        $companyName = clean($_POST['company_name'] ?? '');
        $website = sanitizeUrl($_POST['website'] ?? '') ?: null;
        $address = clean($_POST['address'] ?? '');
        $city = clean($_POST['city'] ?? '');
        $country = clean($_POST['country'] ?? '');
        $status = $_POST['client_status'] ?? 'active';
        $assignedUserId = (int)($_POST['assigned_user_id'] ?? null) ?: null;
        
        if (empty($clientName) || empty($email) || empty($primaryPhone)) {
            setFlash('danger', 'Please fill in all required fields');
        } else {
            try {
                if ($isEdit) {
                    // UPDATE
                    $stmt = $db->prepare("
                        UPDATE clients SET
                        client_name = :name,
                        company_name = :company,
                        email = :email,
                        primary_phone = :phone1,
                        secondary_phone = :phone2,
                        website = :website,
                        address = :address,
                        city = :city,
                        country = :country,
                        client_status = :status,
                        assigned_user_id = :assigned_user
                        WHERE id = :id
                    ");
                    
                    $stmt->execute([
                        ':name' => $clientName,
                        ':company' => $companyName,
                        ':email' => $email,
                        ':phone1' => $primaryPhone,
                        ':phone2' => $secondaryPhone,
                        ':website' => $website,
                        ':address' => $address,
                        ':city' => $city,
                        ':country' => $country,
                        ':status' => $status,
                        ':assigned_user' => $assignedUserId,
                        ':id' => $client['id']
                    ]);
                    
                    logActivity('UPDATE', 'clients', $client['id'], 'Updated client information');
                    setFlash('success', 'Client updated successfully!');
                } else {
                    // INSERT
                    $clientCode = generateCode('CLI-', 'clients', 'client_code');
                    
                    $stmt = $db->prepare("
                        INSERT INTO clients (
                            client_code, client_name, company_name, email,
                            primary_phone, secondary_phone, website, address,
                            city, country, client_status, assigned_user_id, created_by
                        ) VALUES (
                            :code, :name, :company, :email,
                            :phone1, :phone2, :website, :address,
                            :city, :country, :status, :assigned_user, :created_by
                        )
                    ");
                    
                    $stmt->execute([
                        ':code' => $clientCode,
                        ':name' => $clientName,
                        ':company' => $companyName,
                        ':email' => $email,
                        ':phone1' => $primaryPhone,
                        ':phone2' => $secondaryPhone,
                        ':website' => $website,
                        ':address' => $address,
                        ':city' => $city,
                        ':country' => $country,
                        ':status' => $status,
                        ':assigned_user' => $assignedUserId,
                        ':created_by' => currentUser()['id']
                    ]);
                    
                    $newClientId = $db->lastInsertId();
                    logActivity('CREATE', 'clients', $newClientId, 'Created new client');
                    setFlash('success', 'Client created successfully!');
                    
                    if (isset($_POST['save_another'])) {
                        redirect('admin/clients/add.php');
                    }
                }
                
                redirect('admin/clients/index.php');
            } catch (Exception $e) {
                setFlash('danger', 'An error occurred: ' . $e->getMessage());
            }
        }
    }
}

// Get users for assignment
$stmt = $db->prepare("SELECT id, name FROM users WHERE role IN ('admin', 'manager', 'staff') ORDER BY name");
$stmt->execute();
$users = $stmt->fetchAll();

// Get countries list
$countries = ['United Arab Emirates', 'Pakistan', 'United States', 'United Kingdom', 'Canada', 'Australia', 'Germany', 'France', 'Singapore', 'Malaysia', 'India', 'Bangladesh'];

include __DIR__ . '/../../includes/header.php';
?>

<style>
    .form-container {
        display: grid;
        grid-template-columns: 250px 1fr 250px;
        gap: 25px;
    }

    .form-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.08);
    }

    .form-section h5 {
        font-weight: 600;
        margin-bottom: 20px;
        font-size: 1rem;
        color: #1D1D1D;
    }

    .main-form {
        grid-column: 2;
    }

    .form-group {
        margin-bottom: 20px;
    }

    .form-group label {
        display: block;
        font-weight: 500;
        font-size: 0.85rem;
        color: #444;
        margin-bottom: 8px;
    }

    .form-group label .required {
        color: #FF5E5E;
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
        transition: all 0.3s ease;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
        outline: none;
        border-color: #6418C3;
        box-shadow: 0 0 0 3px rgba(100,24,195,0.15);
    }

    .form-group textarea {
        resize: vertical;
        min-height: 80px;
    }

    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
    }

    .form-row .form-group {
        margin-bottom: 0;
    }

    .section-divider {
        border-top: 1px solid #f0f0f0;
        margin: 25px 0;
        padding-top: 25px;
    }

    .section-title {
        font-weight: 600;
        font-size: 0.95rem;
        color: #1D1D1D;
        margin-bottom: 15px;
    }

    .avatar-upload {
        text-align: center;
        padding: 20px;
    }

    .avatar-circle {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        background: linear-gradient(135deg, #6418C3, #9B59B6);
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 2.5rem;
        font-weight: 700;
        margin: 0 auto 15px;
        cursor: pointer;
    }

    .avatar-upload-label {
        font-size: 0.8rem;
        color: #999;
        cursor: pointer;
    }

    .info-item {
        margin-bottom: 15px;
        padding-bottom: 15px;
        border-bottom: 1px solid #f0f0f0;
    }

    .info-item:last-child {
        border-bottom: none;
        margin-bottom: 0;
        padding-bottom: 0;
    }

    .info-label {
        font-size: 0.75rem;
        color: #999;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 3px;
    }

    .info-value {
        font-weight: 600;
        color: #1D1D1D;
        font-size: 0.9rem;
        word-break: break-all;
    }

    .form-buttons {
        display: flex;
        gap: 10px;
        margin-top: 30px;
    }

    .btn-submit {
        flex: 1;
        padding: 12px;
        background: #6418C3;
        color: white;
        border: none;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-submit:hover {
        background: #5910b8;
        transform: translateY(-2px);
    }

    .btn-secondary {
        flex: 1;
        padding: 12px;
        background: white;
        color: #6418C3;
        border: 2px solid #6418C3;
        border-radius: 8px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
    }

    .btn-secondary:hover {
        background: #f8f5ff;
    }

    @media (max-width: 1200px) {
        .form-container {
            grid-template-columns: 1fr;
        }

        .main-form {
            grid-column: 1;
        }
    }
</style>

<!-- PAGE TITLE -->
<h1 style="margin-bottom: 25px; font-weight: 700; color: #1D1D1D;">
    <i class="fas fa-user-plus"></i> <?php echo $isEdit ? 'Edit Client' : 'Add New Client'; ?>
</h1>

<!-- FLASH MESSAGES -->
<?php echo flashAlert(); ?>

<!-- FORM CONTAINER -->
<div class="form-container">
    <!-- LEFT SIDEBAR -->
    <div>
        <div class="form-section">
            <div class="avatar-upload">
                <div class="avatar-circle">
                    <?php echo getInitials($client['client_name'] ?? 'NC'); ?>
                </div>
                <label class="avatar-upload-label">
                    <input type="file" style="display: none;" accept=".jpg,.jpeg,.png">
                    Click to change
                </label>
            </div>

            <div class="section-divider"></div>

            <div>
                <div class="info-label">Client Code</div>
                <div class="info-value" style="font-family: monospace;">
                    <?php echo $client['client_code'] ?? 'Auto-generated'; ?>
                </div>
            </div>

            <div class="section-divider"></div>

            <div class="form-group">
                <label>Status</label>
                <select name="client_status" style="width: 100%; padding: 8px 10px; border: 1px solid #e0e0e0; border-radius: 6px;">
                    <option value="active" <?php echo ($client['client_status'] ?? 'active') === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo ($client['client_status'] ?? '') === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    <option value="suspended" <?php echo ($client['client_status'] ?? '') === 'suspended' ? 'selected' : ''; ?>>Suspended</option>
                </select>
            </div>

            <div class="form-group">
                <label>Assigned To</label>
                <select name="assigned_user_id" style="width: 100%; padding: 8px 10px; border: 1px solid #e0e0e0; border-radius: 6px;" class="select2">
                    <option value="">Select user...</option>
                    <?php foreach ($users as $user): ?>
                    <option value="<?php echo $user['id']; ?>" <?php echo ($client['assigned_user_id'] ?? null) == $user['id'] ? 'selected' : ''; ?>>
                        <?php echo clean($user['name']); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>

    <!-- MAIN FORM -->
    <div class="main-form">
        <form method="POST" enctype="multipart/form-data">
            <?php echo csrfField(); ?>

            <!-- PERSONAL INFORMATION SECTION -->
            <div class="form-section">
                <h5><i class="fas fa-user"></i> Personal Information</h5>

                <div class="form-group">
                    <label>Client Name <span class="required">*</span></label>
                    <input type="text" name="client_name" value="<?php echo $client['client_name'] ?? ''; ?>" required>
                </div>

                <div class="form-group">
                    <label>Email <span class="required">*</span></label>
                    <input type="email" name="email" value="<?php echo $client['email'] ?? ''; ?>" required>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label>Primary Phone <span class="required">*</span></label>
                        <input type="tel" name="primary_phone" value="<?php echo $client['primary_phone'] ?? ''; ?>" placeholder="+92 300 1234567" required>
                    </div>
                    <div class="form-group">
                        <label>Secondary Phone</label>
                        <input type="tel" name="secondary_phone" value="<?php echo $client['secondary_phone'] ?? ''; ?>" placeholder="+92 300 9876543">
                    </div>
                </div>

                <div class="form-section" style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #f0f0f0;">
                    <h5 style="margin-top: 0;"><i class="fas fa-building"></i> Company Information</h5>

                    <div class="form-group">
                        <label>Company Name</label>
                        <input type="text" name="company_name" value="<?php echo $client['company_name'] ?? ''; ?>" placeholder="ABC Trading LLC">
                    </div>

                    <div class="form-group">
                        <label>Website</label>
                        <input type="url" name="website" value="<?php echo $client['website'] ?? ''; ?>" placeholder="https://example.com">
                    </div>

                    <div class="form-group">
                        <label>Address</label>
                        <textarea name="address"><?php echo $client['address'] ?? ''; ?></textarea>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label>City</label>
                            <input type="text" name="city" value="<?php echo $client['city'] ?? ''; ?>" placeholder="Dubai">
                        </div>
                        <div class="form-group">
                            <label>Country</label>
                            <select name="country" class="select2">
                                <option value="">Select country...</option>
                                <?php foreach ($countries as $c): ?>
                                <option value="<?php echo $c; ?>" <?php echo ($client['country'] ?? '') === $c ? 'selected' : ''; ?>>
                                    <?php echo $c; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- FORM BUTTONS -->
            <div class="form-buttons">
                <button type="submit" name="save" class="btn-submit">
                    <i class="fas fa-save"></i> Save Client
                </button>
                <?php if (!$isEdit): ?>
                <button type="submit" name="save_another" class="btn-secondary">
                    <i class="fas fa-plus"></i> Save & Add Another
                </button>
                <?php endif; ?>
                <a href="<?php echo APP_URL; ?>/admin/clients/index.php" class="btn-secondary" style="text-align: center; text-decoration: none;">
                    <i class="fas fa-times"></i> Cancel
                </a>
            </div>
        </form>
    </div>

    <!-- RIGHT SIDEBAR (If editing) -->
    <?php if ($isEdit): ?>
    <div>
        <div class="form-section">
            <h5><i class="fas fa-chart-bar"></i> Quick Stats</h5>

            <?php
            // Get client stats
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE client_id = :id");
            $stmt->execute([':id' => $client['id']]);
            $projectCount = $stmt->fetch()['count'];

            $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE client_id = :id");
            $stmt->execute([':id' => $client['id']]);
            $invoiceCount = $stmt->fetch()['count'];

            $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM invoices WHERE client_id = :id");
            $stmt->execute([':id' => $client['id']]);
            $totalInvoiced = $stmt->fetch()['total'];

            $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM invoices WHERE client_id = :id");
            $stmt->execute([':id' => $client['id']]);
            $totalPaid = $stmt->fetch()['total'];
            ?>

            <div class="info-item">
                <div class="info-label">Total Projects</div>
                <div class="info-value"><?php echo $projectCount; ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Total Invoices</div>
                <div class="info-value"><?php echo $invoiceCount; ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Total Invoiced</div>
                <div class="info-value"><?php echo formatCurrency($totalInvoiced); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Total Paid</div>
                <div class="info-value"><?php echo formatCurrency($totalPaid); ?></div>
            </div>

            <div class="info-item">
                <div class="info-label">Created</div>
                <div class="info-value"><?php echo formatDate($client['created_at']); ?></div>
            </div>

            <div style="margin-top: 15px;">
                <a href="<?php echo APP_URL; ?>/admin/clients/profile.php?id=<?php echo $client['id']; ?>" class="btn-submit" style="display: block; text-align: center; text-decoration: none;">
                    View Full Profile →
                </a>
            </div>
        </div>
    </div>
    <?php endif; ?>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
