<?php
/**
 * Helper Functions - All utility functions for the CMS
 * session | CSRF | output sanitization | formatting | database helpers
 */

/**
 * ============================================
 * SESSION MANAGEMENT
 * ============================================
 */

function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
        
        // Session timeout check
        if (isset($_SESSION['last_activity'])) {
            if (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT) {
                session_destroy();
                redirect('login.php');
            }
        }
        
        $_SESSION['last_activity'] = time();
    }
}

function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

function currentUser() {
    if (!isLoggedIn()) return null;
    
    static $user = null;
    
    if ($user === null) {
        global $db;
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
        $stmt->execute([':id' => $_SESSION['user_id']]);
        $user = $stmt->fetch();
    }
    
    return $user;
}

function requireLogin() {
    if (!isLoggedIn()) {
        redirect('login.php');
    }
}

function requireRole($roles = []) {
    requireLogin();
    
    $user = currentUser();
    if (!in_array($user['role'], $roles)) {
        echo "<div class='alert alert-danger m-5'>Access Denied. You don't have permission to access this page.</div>";
        include 'includes/footer.php';
        exit;
    }
}

/**
 * ============================================
 * CSRF TOKEN HANDLING
 * ============================================
 */

function generateCsrf() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(CSRF_TOKEN_LENGTH));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrf($token) {
    return hash_equals($_SESSION['csrf_token'] ?? '', $token ?? '');
}

function csrfField() {
    return '<input type="hidden" name="csrf_token" value="' . generateCsrf() . '">';
}

/**
 * ============================================
 * OUTPUT SANITIZATION & SECURITY
 * ============================================
 */

function clean($str) {
    return htmlspecialchars(trim($str), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail($email) {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function sanitizeUrl($url) {
    return filter_var($url, FILTER_SANITIZE_URL);
}

function sanitizeInt($value) {
    return (int) $value;
}

/**
 * ============================================
 * REDIRECTION
 * ============================================
 */

function redirect($url) {
    if (!headers_sent()) {
        header('Location: ' . APP_URL . '/' . ltrim($url, '/'));
        exit;
    } else {
        echo "<script>window.location = '" . APP_URL . "/" . ltrim($url, '/') . "';</script>";
        exit;
    }
}

/**
 * ============================================
 * FLASH MESSAGES
 * ============================================
 */

function setFlash($type, $message) {
    if (!isset($_SESSION['flash'])) {
        $_SESSION['flash'] = [];
    }
    $_SESSION['flash'][] = [
        'type' => $type,
        'message' => $message
    ];
}

function getFlash() {
    $flash = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flash;
}

function flashAlert() {
    $flash = getFlash();
    $html = '';
    
    foreach ($flash as $alert) {
        $html .= '<div class="alert alert-' . clean($alert['type']) . ' alert-dismissible fade show" role="alert">';
        $html .= clean($alert['message']);
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
    
    return $html;
}

/**
 * ============================================
 * FORMATTING FUNCTIONS
 * ============================================
 */

function formatCurrency($amount) {
    return CURRENCY_SYMBOL . number_format((float)$amount, DECIMAL_PLACES, '.', ',');
}

function formatDate($date, $format = null) {
    if (!$date || $date === '0000-00-00') return '';
    $format = $format ?? DATE_FORMAT;
    return date($format, strtotime($date));
}

function formatDateTime($datetime, $format = null) {
    if (!$datetime || $datetime === '0000-00-00 00:00:00') return '';
    $format = $format ?? DATETIME_FORMAT;
    return date($format, strtotime($datetime));
}

function timeAgo($datetime) {
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;

    if ($diff < 60) return 'just now';
    if ($diff < 3600) return floor($diff / 60) . 'm ago';
    if ($diff < 86400) return floor($diff / 3600) . 'h ago';
    if ($diff < 604800) return floor($diff / 86400) . 'd ago';
    if ($diff < 2592000) return floor($diff / 604800) . 'w ago';
    
    return formatDate($datetime);
}

/**
 * ============================================
 * CODE GENERATION
 * ============================================
 */

function generateCode($prefix, $table, $column) {
    global $db;
    
    $stmt = $db->prepare("SELECT MAX($column) as max_code FROM $table WHERE $column LIKE :prefix");
    $stmt->execute([':prefix' => $prefix . '%']);
    
    $result = $stmt->fetch();
    $maxCode = $result['max_code'] ?? null;
    
    if ($maxCode) {
        $number = (int) substr($maxCode, strlen($prefix)) + 1;
    } else {
        $number = 1;
    }
    
    return $prefix . str_pad($number, 5, '0', STR_PAD_LEFT);
}

/**
 * ============================================
 * SETTING MANAGEMENT
 * ============================================
 */

function getSetting($key, $default = null) {
    global $db;
    
    static $settings = null;
    
    if ($settings === null) {
        $stmt = $db->prepare("SELECT setting_key, setting_value FROM site_settings");
        $stmt->execute();
        $rows = $stmt->fetchAll();
        
        $settings = [];
        foreach ($rows as $row) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
    
    return $settings[$key] ?? $default;
}

function updateSetting($key, $value, $userId = null) {
    global $db;
    
    $userId = $userId ?? (currentUser()['id'] ?? null);
    
    $stmt = $db->prepare("
        INSERT INTO site_settings (setting_key, setting_value, updated_by, updated_at)
        VALUES (:key, :value, :user_id, NOW())
        ON DUPLICATE KEY UPDATE
        setting_value = VALUES(setting_value),
        updated_by = :user_id,
        updated_at = NOW()
    ");
    
    return $stmt->execute([
        ':key' => $key,
        ':value' => $value,
        ':user_id' => $userId
    ]);
}

/**
 * ============================================
 * FILE UPLOAD HANDLING
 * ============================================
 */

function uploadFile($file, $destFolder, $allowedTypes = null) {
    global $db;
    
    if (!isset($file) || $file['error'] !== UPLOAD_ERR_OK) {
        return ['success' => false, 'error' => 'Upload error occurred'];
    }

    $allowedTypes = $allowedTypes ?? ALLOWED_UPLOAD_TYPES;
    
    // Validate file size
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return ['success' => false, 'error' => 'File size exceeds ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB'];
    }

    // Get file extension
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($ext, $allowedTypes)) {
        return ['success' => false, 'error' => 'File type not allowed'];
    }

    // Create folder if not exists
    if (!is_dir($destFolder)) {
        mkdir($destFolder, 0755, true);
    }

    // Generate unique filename
    $fileName = uniqid() . '.' . $ext;
    $filePath = $destFolder . $fileName;

    if (move_uploaded_file($file['tmp_name'], $filePath)) {
        return [
            'success' => true,
            'file_name' => $fileName,
            'original_name' => $file['name'],
            'full_path' => $filePath
        ];
    }

    return ['success' => false, 'error' => 'Failed to move uploaded file'];
}

/**
 * ============================================
 * ACTIVITY LOGGING
 * ============================================
 */

function logActivity($action, $module, $recordId = null, $description = null) {
    global $db;
    
    if (!isLoggedIn()) return;
    
    $stmt = $db->prepare("
        INSERT INTO activity_logs (user_id, action, module, record_id, description, ip_address)
        VALUES (:user_id, :action, :module, :record_id, :description, :ip_address)
    ");
    
    return $stmt->execute([
        ':user_id' => currentUser()['id'],
        ':action' => $action,
        ':module' => $module,
        ':record_id' => $recordId,
        ':description' => $description,
        ':ip_address' => $_SERVER['REMOTE_ADDR']
    ]);
}

/**
 * ============================================
 * NOTIFICATIONS
 * ============================================
 */

function addNotification($userId, $title, $message, $type = 'info', $link = null) {
    global $db;
    
    $stmt = $db->prepare("
        INSERT INTO notifications (user_id, title, message, type, link)
        VALUES (:user_id, :title, :message, :type, :link)
    ");
    
    return $stmt->execute([
        ':user_id' => $userId,
        ':title' => $title,
        ':message' => $message,
        ':type' => $type,
        ':link' => $link
    ]);
}

function getNotifications($userId, $limit = NOTIFICATIONS_LIMIT) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT * FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC
        LIMIT :limit
    ");
    
    $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();
    
    return $stmt->fetchAll();
}

function getUnreadNotificationsCount($userId) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM notifications
        WHERE user_id = :user_id AND is_read = 0
    ");
    
    $stmt->execute([':user_id' => $userId]);
    return $stmt->fetch()['count'];
}

/**
 * ============================================
 * STATUS BADGES
 * ============================================
 */

function statusBadge($status) {
    $badges = [
        'active' => '<span class="badge bg-success">Active</span>',
        'inactive' => '<span class="badge bg-secondary">Inactive</span>',
        'pending' => '<span class="badge bg-warning">Pending</span>',
        'in_progress' => '<span class="badge bg-primary">In Progress</span>',
        'completed' => '<span class="badge bg-success">Completed</span>',
        'on_hold' => '<span class="badge bg-warning">On Hold</span>',
        'draft' => '<span class="badge bg-secondary">Draft</span>',
        'sent' => '<span class="badge bg-info">Sent</span>',
        'paid' => '<span class="badge bg-success">Paid</span>',
        'partial' => '<span class="badge bg-warning">Partial</span>',
        'overdue' => '<span class="badge bg-danger">Overdue</span>',
        'cancelled' => '<span class="badge bg-danger">Cancelled</span>',
        'expired' => '<span class="badge bg-danger">Expired</span>',
        'renewed' => '<span class="badge bg-success">Renewed</span>',
        'todo' => '<span class="badge bg-secondary">Todo</span>',
        'review' => '<span class="badge bg-info">Review</span>',
        'done' => '<span class="badge bg-success">Done</span>',
        'low' => '<span class="badge bg-info">Low</span>',
        'medium' => '<span class="badge bg-warning">Medium</span>',
        'high' => '<span class="badge bg-danger">High</span>',
        'urgent' => '<span class="badge bg-danger">Urgent</span>',
    ];
    
    return $badges[$status] ?? '<span class="badge bg-secondary">' . ucfirst($status) . '</span>';
}

/**
 * ============================================
 * DASHBOARD STATS
 * ============================================
 */

function getDashboardStats() {
    global $db;
    
    $stats = [];
    
    // Total Clients
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM clients WHERE client_status != 'suspended'");
    $stmt->execute();
    $stats['total_clients'] = $stmt->fetch()['count'];
    
    // Active Clients (with active projects)
    $stmt = $db->prepare("SELECT COUNT(DISTINCT p.client_id) as count FROM projects p WHERE p.status IN ('pending', 'in_progress')");
    $stmt->execute();
    $stats['active_clients'] = $stmt->fetch()['count'];
    
    // Total Projects
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE status IN ('pending', 'in_progress', 'completed')");
    $stmt->execute();
    $stats['total_projects'] = $stmt->fetch()['count'];
    
    // Active Projects
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE status IN ('pending', 'in_progress')");
    $stmt->execute();
    $stats['active_projects'] = $stmt->fetch()['count'];
    
    // Completed Projects
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM projects WHERE status = 'completed'");
    $stmt->execute();
    $stats['completed_projects'] = $stmt->fetch()['count'];
    
    // Total Services
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_services WHERE status IN ('active', 'expired')");
    $stmt->execute();
    $stats['total_services'] = $stmt->fetch()['count'];
    
    // Active Services
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_services WHERE status = 'active'");
    $stmt->execute();
    $stats['active_services'] = $stmt->fetch()['count'];
    
    // Expiring Services (Next 30 days)
    $stmt = $db->prepare("
        SELECT COUNT(*) as count FROM project_services
        WHERE status = 'active'
        AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $stats['expiring_services'] = $stmt->fetch()['count'];
    
    // Expired Services
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM project_services WHERE status = 'expired'");
    $stmt->execute();
    $stats['expired_services'] = $stmt->fetch()['count'];
    
    // Total Invoices
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices");
    $stmt->execute();
    $stats['total_invoices'] = $stmt->fetch()['count'];
    
    // Paid Invoices
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE status = 'paid'");
    $stmt->execute();
    $stats['paid_invoices'] = $stmt->fetch()['count'];
    
    // Unpaid Invoices
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM invoices WHERE status IN ('sent', 'partial', 'overdue', 'draft')");
    $stmt->execute();
    $stats['unpaid_invoices'] = $stmt->fetch()['count'];
    
    // Total Revenue (All Invoices)
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM invoices");
    $stmt->execute();
    $stats['total_invoice_amount'] = $stmt->fetch()['total'];
    
    // Paid Revenue
    $stmt = $db->prepare("SELECT COALESCE(SUM(paid_amount), 0) as total FROM invoices WHERE status = 'paid'");
    $stmt->execute();
    $stats['total_revenue'] = $stmt->fetch()['total'];
    
    // Pending Revenue (Unpaid)
    $stmt = $db->prepare("SELECT COALESCE(SUM(CASE WHEN status IN ('sent', 'partial', 'overdue', 'draft') THEN total ELSE 0 END), 0) as total FROM invoices");
    $stmt->execute();
    $stats['pending_revenue'] = $stmt->fetch()['total'];
    
    return $stats;
}

/**
 * ============================================
 * SERVICE EXPIRY ALERTS
 * ============================================
 */

function checkExpiringServices() {
    global $db;
    
    $stmt = $db->prepare("
        SELECT 
            ps.id,
            ps.service_name,
            ps.expiry_date,
            ps.status,
            ps.price,
            p.id as project_id,
            p.project_name,
            c.id as client_id,
            c.client_name,
            c.primary_phone,
            c.email
        FROM project_services ps
        JOIN projects p ON ps.project_id = p.id
        JOIN clients c ON p.client_id = c.id
        WHERE ps.status = 'active'
        AND ps.expiry_date >= CURDATE()
        AND ps.expiry_date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
        ORDER BY ps.expiry_date ASC
        LIMIT 10
    ");
    
    $stmt->execute();
    return $stmt->fetchAll();
}

function markAlertSent($serviceId, $days) {
    global $db;
    
    $column = "alert_{$days}_sent";
    
    $stmt = $db->prepare("UPDATE client_services SET $column = 1 WHERE id = :id");
    return $stmt->execute([':id' => $serviceId]);
}

/**
 * ============================================
 * INVOICE CALCULATIONS
 * ============================================
 */

function calculateInvoiceTotal($subtotal, $taxPercent, $discountPercent) {
    $tax = ($subtotal * $taxPercent) / 100;
    $discount = ($subtotal * $discountPercent) / 100;
    $total = $subtotal + $tax - $discount;
    
    return [
        'subtotal' => round($subtotal, 2),
        'tax_percent' => $taxPercent,
        'tax_amount' => round($tax, 2),
        'discount_percent' => $discountPercent,
        'discount_amount' => round($discount, 2),
        'total' => round($total, 2)
    ];
}

/**
 * ============================================
 * TASK KANBAN
 * ============================================
 */

function getProjectTasks($projectId, $status = null) {
    global $db;
    
    $sql = "
        SELECT * FROM tasks
        WHERE project_id = :project_id
        AND parent_id IS NULL
    ";
    
    $params = [':project_id' => $projectId];
    
    if ($status) {
        $sql .= " AND status = :status";
        $params[':status'] = $status;
    }
    
    $sql .= " ORDER BY sort_order ASC, created_at DESC";
    
    return $db->fetchAll($sql, $params);
}

function getTaskSubtasks($taskId) {
    global $db;
    
    $stmt = $db->prepare("
        SELECT * FROM tasks
        WHERE parent_id = :task_id
        ORDER BY sort_order ASC
    ");
    
    $stmt->execute([':task_id' => $taskId]);
    return $stmt->fetchAll();
}

/**
 * ============================================
 * PRIORITY BADGE COLOR
 * ============================================
 */

function priorityBadge($priority) {
    $colors = [
        'low' => 'bg-info',
        'medium' => 'bg-warning',
        'high' => 'bg-danger',
        'urgent' => 'bg-danger'
    ];
    
    $color = $colors[$priority] ?? 'bg-secondary';
    
    return '<span class="badge ' . $color . '">' . ucfirst($priority) . '</span>';
}

/**
 * ============================================
 * INITIALS AVATAR
 * ============================================
 */

function getInitials($fullName) {
    $parts = explode(' ', trim($fullName));
    $initials = '';
    
    foreach ($parts as $part) {
        $initials .= strtoupper(substr($part, 0, 1));
    }
    
    return substr($initials, 0, 2);
}

/**
 * ============================================
 * COLOR GENERATOR (for avatars)
 * ============================================
 */

function getColorByName($name) {
    $colors = ['#6418C3', '#1EAAE7', '#FF9B52', '#2BC155', '#FF5E5E', '#FFA502', '#1ABC9C', '#E74C3C'];
    $hash = abs(crc32($name));
    return $colors[$hash % count($colors)];
}
