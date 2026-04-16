<?php
/**
 * Header - Sidebar & Topbar
 * Design inspired by Workload template
 */

if (!function_exists('isLoggedIn')) return;

$user = currentUser();
$notificationCount = getUnreadNotificationsCount($user['id']);
$dashboardStats = getDashboardStats();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo APP_NAME; ?> - Project Management System</title>
    
    <!-- Bootstrap 5.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome 6 -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts - Poppins -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    
    <!-- Flatpickr -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    
    <!-- ApexCharts -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/apexcharts/3.45.0/apexcharts.min.js"></script>
    
    <!-- SweetAlert2 -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.18/dist/sweetalert2.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.7.18/dist/sweetalert2.min.js"></script>
    
    <!-- Toastr.js -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/toastr.js/latest/toastr.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6418C3;
            --secondary: #1EAAE7;
            --success: #2BC155;
            --warning: #FF9B52;
            --danger: #FF5E5E;
            --dark: #1D1D1D;
            --sidebar-bg: #1D1D35;
            --card-bg: #FFFFFF;
            --body-bg: #F4F4F4;
            --border: #EEEEEE;
            --text-muted: #888888;
        }

        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            background-color: var(--body-bg);
            color: #333;
        }

        /* ============================================
           SIDEBAR STYLES
           ============================================ */
        .sidebar {
            background: var(--sidebar-bg);
            position: fixed;
            left: 0;
            top: 0;
            width: 270px;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.3s ease;
            border-right: 1px solid rgba(255,255,255,0.1);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar-logo {
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            text-align: center;
        }

        .sidebar-logo-icon {
            font-size: 2rem;
            color: var(--primary);
            margin-bottom: 10px;
            display: block;
        }

        .sidebar-logo-text {
            color: white;
            font-weight: 600;
            font-size: 0.95rem;
            margin: 0;
        }

        .sidebar-logo-subtitle {
            color: rgba(255,255,255,0.5);
            font-size: 0.7rem;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin: 5px 0 0 0;
        }

        .sidebar.collapsed .sidebar-logo-text,
        .sidebar.collapsed .sidebar-logo-subtitle {
            display: none;
        }

        .nav-section {
            padding: 20px 0 15px 0;
            margin: 10px 0 0 0;
            border-top: 1px solid rgba(255,255,255,0.05);
        }

        .nav-section-label {
            padding: 0 20px 10px 20px;
            font-size: 0.7rem;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 2px;
            font-weight: 600;
        }

        .nav-item {
            padding: 12px 20px;
            margin: 0 10px;
            border-radius: 8px;
            color: rgba(255,255,255,0.7);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            position: relative;
            height: 45px;
        }

        .nav-item i {
            font-size: 1rem;
            width: 20px;
            text-align: center;
        }

        .nav-item:hover {
            background: rgba(255,255,255,0.05);
            color: white;
        }

        .nav-item.active {
            background: rgba(100,24,195,0.2);
            color: var(--primary);
            border-left: 3px solid var(--primary);
            padding-left: 17px;
        }

        .sidebar.collapsed .nav-item {
            padding: 12px 15px;
            justify-content: center;
        }

        .sidebar.collapsed .nav-item > span:not(i) {
            display: none;
        }

        .sidebar-footer {
            position: absolute;
            bottom: 0;
            width: 100%;
            padding: 20px;
            border-top: 1px solid rgba(255,255,255,0.1);
            background: rgba(0,0,0,0.2);
        }

        .user-item {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            text-decoration: none;
            font-size: 0.85rem;
        }

        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .user-info {
            flex: 1;
        }

        .user-name {
            font-weight: 600;
            font-size: 0.85rem;
            margin: 0;
            display: block;
        }

        .user-role {
            color: rgba(255,255,255,0.5);
            font-size: 0.75rem;
            margin: 2px 0 0 0;
            display: block;
        }

        .sidebar.collapsed .user-info,
        .sidebar.collapsed .logout-btn {
            display: none;
        }

        .logout-btn {
            color: rgba(255,255,255,0.7);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .logout-btn:hover {
            color: var(--danger);
        }

        /* ============================================
           TOPBAR STYLES
           ============================================ */
        .topbar {
            background: white;
            height: 60px;
            display: flex;
            align-items: center;
            padding: 0 25px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
            position: fixed;
            top: 0;
            left: 270px;
            right: 0;
            z-index: 999;
            transition: all 0.3s ease;
        }

        .sidebar.collapsed ~ .topbar,
        .sidebar.collapsed ~ .main-content {
            left: 80px;
        }

        .topbar-left {
            display: flex;
            align-items: center;
            gap: 20px;
            flex: 1;
        }

        .hamburger-btn {
            background: none;
            border: none;
            color: var(--dark);
            font-size: 1.3rem;
            cursor: pointer;
            transition: all 0.3s ease;
            padding: 0;
        }

        .hamburger-btn:hover {
            color: var(--primary);
        }

        .breadcrumb {
            margin: 0;
            font-size: 0.9rem;
        }

        .breadcrumb-item {
            color: #999;
        }

        .breadcrumb-item.active {
            color: var(--dark);
            font-weight: 600;
        }

        .breadcrumb-item a {
            color: var(--primary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .breadcrumb-item a:hover {
            color: #5010a6;
        }

        .topbar-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .topbar-icon {
            background: none;
            border: none;
            color: #666;
            font-size: 1.2rem;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            padding: 0;
        }

        .topbar-icon:hover {
            color: var(--primary);
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .notification-dropdown {
            position: absolute;
            top: 60px;
            right: 0;
            background: white;
            width: 320px;
            border-radius: 12px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            z-index: 1100;
            max-height: 400px;
            overflow-y: auto;
            display: none;
        }

        .notification-dropdown.show {
            display: block;
        }

        .notification-header {
            padding: 15px 20px;
            border-bottom: 1px solid #f0f0f0;
            font-weight: 600;
            color: var(--dark);
        }

        .notification-item {
            padding: 12px 15px;
            border-bottom: 1px solid #f5f5f5;
            cursor: pointer;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }

        .notification-item:hover {
            background: #f9f9f9;
        }

        .notification-item.unread {
            background: rgba(100,24,195,0.05);
        }

        .notification-item.info {
            border-left-color: #1EAAE7;
        }

        .notification-item.success {
            border-left-color: #2BC155;
        }

        .notification-item.warning {
            border-left-color: #FF9B52;
        }

        .notification-item.danger {
            border-left-color: #FF5E5E;
        }

        .notification-text {
            font-size: 0.85rem;
            color: #333;
            margin: 0;
        }

        .notification-time {
            font-size: 0.75rem;
            color: #999;
            margin-top: 3px;
        }

        /* ============================================
           MAIN CONTENT AREA
           ============================================ */
        .main-content {
            margin-left: 270px;
            margin-top: 60px;
            min-height: 100vh;
            padding: 30px 25px;
            transition: all 0.3s ease;
        }

        /* ============================================
           RESPONSIVE
           ============================================ */
        @media (max-width: 768px) {
            .sidebar {
                width: 80px;
            }

            .topbar {
                left: 80px;
            }

            .main-content {
                margin-left: 80px;
            }

            .sidebar-logo-text,
            .sidebar-logo-subtitle,
            .user-info,
            .user-role,
            .logout-btn {
                display: none;
            }

            .nav-item > span {
                display: none;
            }

            .notification-dropdown {
                width: 300px;
                right: -100px;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 70px;
            }

            .topbar {
                left: 70px;
                padding: 0 15px;
            }

            .main-content {
                margin-left: 70px;
                padding: 20px 15px;
            }

            .breadcrumb {
                display: none;
            }
        }
    </style>
</head>
<body>

<!-- SIDEBAR -->
<aside class="sidebar" id="sidebar">
    <div class="sidebar-logo">
        <i class="fas fa-cube sidebar-logo-icon"></i>
        <p class="sidebar-logo-text">CMS-ecomzone</p>
        <p class="sidebar-logo-subtitle">Project Mgmt</p>
    </div>

    <nav>
        <!-- MAIN MENU -->
        <div class="nav-section">
            <a href="<?php echo APP_URL; ?>/admin/dashboard.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'dashboard') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-chart-line"></i>
                <span>Dashboard</span>
            </a>

            <div class="nav-section-label">MANAGEMENT</div>
            <a href="<?php echo APP_URL; ?>/admin/clients/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'clients') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>Clients</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/projects/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'projects') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-tasks"></i>
                <span>Projects</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/service-categories/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'service-categories') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-layer-group"></i>
                <span>Service Categories</span>
            </a>

            <div class="nav-section-label">FINANCE</div>
            <a href="<?php echo APP_URL; ?>/admin/invoices/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'invoices') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-receipt"></i>
                <span>Invoices</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/quotations/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'quotations') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-quote-left"></i>
                <span>Quotations</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/payments/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'payments') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-credit-card"></i>
                <span>Payments</span>
            </a>

            <div class="nav-section-label">COLLABORATION</div>
            <a href="<?php echo APP_URL; ?>/admin/alerts/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'alerts') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-bell"></i>
                <span>Manage Alerts</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/meetings/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'meetings') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-calendar-check"></i>
                <span>Meetings</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/whatsapp/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'whatsapp') !== false) ? 'active' : ''; ?>">
                <i class="fab fa-whatsapp"></i>
                <span>WhatsApp</span>
            </a>
        </div>

        <!-- PRIVATE SECTION -->
        <div class="nav-section">
            <div class="nav-section-label">PERSONAL</div>
            <a href="<?php echo APP_URL; ?>/admin/todos/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'todos') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-check-square"></i>
                <span>My Todos</span>
            </a>
        </div>

        <!-- ADMIN SECTION -->
        <?php if ($user['role'] === 'admin'): ?>
        <div class="nav-section">
            <div class="nav-section-label">ADMIN</div>
            <a href="<?php echo APP_URL; ?>/admin/users.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'users') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-user-tie"></i>
                <span>Users</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/settings/index.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'settings') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-sliders-h"></i>
                <span>Settings</span>
            </a>
            <a href="<?php echo APP_URL; ?>/admin/activity_log.php" class="nav-item <?php echo (strpos($_SERVER['REQUEST_URI'], 'activity') !== false) ? 'active' : ''; ?>">
                <i class="fas fa-history"></i>
                <span>Activity Log</span>
            </a>
        </div>
        <?php endif; ?>
    </nav>

    <!-- SIDEBAR FOOTER -->
    <div class="sidebar-footer">
        <a href="#" class="user-item" data-bs-toggle="dropdown">
            <div class="user-avatar"><?php echo getInitials($user['name']); ?></div>
            <div class="user-info">
                <span class="user-name"><?php echo clean($user['name']); ?></span>
                <span class="user-role"><?php echo ucfirst($user['role']); ?></span>
            </div>
            <a href="<?php echo APP_URL; ?>/logout.php" class="logout-btn" title="Logout">
                <i class="fas fa-sign-out-alt"></i>
            </a>
        </a>
    </div>
</aside>

<!-- TOPBAR -->
<header class="topbar">
    <div class="topbar-left">
        <button class="hamburger-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>
        <nav class="breadcrumb">
            <a class="breadcrumb-item" href="<?php echo APP_URL; ?>/admin/dashboard.php">Dashboard</a>
            <span class="breadcrumb-item active"><?php echo isset($pageTitle) ? clean($pageTitle) : 'Page'; ?></span>
        </nav>
    </div>

    <div class="topbar-right">
        <button class="topbar-icon" title="Search">
            <i class="fas fa-search"></i>
        </button>
        
        <div style="position: relative;">
            <button class="topbar-icon" id="notificationBell" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($notificationCount > 0): ?>
                <span class="notification-badge"><?php echo min($notificationCount, 9); ?></span>
                <?php endif; ?>
            </button>

            <div class="notification-dropdown" id="notificationDropdown">
                <div class="notification-header">
                    <i class="fas fa-bell-slash"></i> Notifications
                </div>
                <div id="notificationList">
                    <div style="padding: 20px; text-align: center; color: #999;">
                        <i class="fas fa-inbox" style="font-size: 2rem; margin-bottom: 10px; display: block;"></i>
                        No notifications
                    </div>
                </div>
            </div>
        </div>

        <button class="topbar-icon" title="Messages">
            <i class="fas fa-envelope"></i>
        </button>

        <a href="#" class="topbar-icon" title="Profile">
            <i class="fas fa-user-circle"></i>
        </a>
    </div>
</header>

<!-- MAIN CONTENT -->
<main class="main-content">
