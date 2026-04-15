<?php
/**
 * QUICK REFERENCE - Copy this to your notes
 */

/*
╔════════════════════════════════════════════════════════════════╗
║           ECOMZONE CMS - QUICK REFERENCE GUIDE                 ║
╚════════════════════════════════════════════════════════════════╝

📁 PROJECT LOCATION:
   c:\xampp\htdocs\EcomZone-CMS

🔑 LOGIN CREDENTIALS:
   Email:    admin@cms-ecomzone.com
   Password: Admin@123
   Role:     Administrator

🗄️  DATABASE SETUP (First Time Only):
   1. Open MySQL from XAMPP Control Panel
   2. Run: mysql -u root
   3. CREATE DATABASE cms_ecomzone;
   4. USE cms_ecomzone;
   5. SOURCE c:\xampp\htdocs\EcomZone-CMS\database\cms_ecomzone.sql;

🌐 ACCESS URLs:
   http://localhost/EcomZone-CMS/
   OR
   http://cms-ecomzone.local/ (if vhost configured)

📚 FILE LOCATIONS:

   Core Infrastructure:
   ├── config/config.php               Global constants
   ├── config/database.php             PDO singleton
   ├── includes/init.php               Bootstrap loader
   ├── includes/functions.php          40+ helper functions
   ├── includes/header.php             Sidebar + topbar
   ├── includes/footer.php             Scripts + JS
   ├── login.php                       Authentication entry
   └── logout.php                      Logout handler

   Main Pages:
   ├── admin/dashboard.php             📊 Dashboard
   ├── admin/activity_log.php          📋 Activity log
   ├── admin/users.php                 👥 User management
   ├── admin/clients/                  👤 Client module
   ├── admin/projects/                 📁 Projects module
   ├── admin/invoices/                 💰 Invoices module
   ├── admin/quotations/               📝 Quotations
   ├── admin/services/                 🔧 Services module
   ├── admin/payments/                 💳 Payments module
   ├── admin/meetings/                 📞 Meetings
   ├── admin/todos/index.php           ✅ Kanban board
   ├── admin/whatsapp/index.php        💬 WhatsApp
   ├── admin/settings/index.php        ⚙️  Settings
   └── admin/api/                      🔌 JSON APIs

🔐 SECURITY CHECKLIST:
   ✅ All forms use  <?php echo csrfField(); ?>
   ✅ All queries use prepared statements: $db->prepare()
   ✅ All output uses: echo clean($variable);
   ✅ All files check: requireLogin() & requireRole()
   ✅ Passwords hashed with BCrypt cost 12

📊 DATABASE: 18 Tables
   users, clients, client_documents, services,
   client_services, projects, tasks, quotations,
   quotation_items, invoices, invoice_items,
   payments, meetings, todos, whatsapp_logs,
   notifications, activity_logs, site_settings

🎨 COLORS (Edit in config/config.php):
   PRIMARY:      #6418C3 (Purple)
   SECONDARY:    #1EAAE7 (Cyan)
   SUCCESS:      #2BC155 (Green)
   WARNING:      #FF9B52 (Orange)
   DANGER:       #FF5E5E (Red)
   SIDEBAR_BG:   #1D1D35 (Dark)

🛠️  MOST USED FUNCTIONS in includes/functions.php:

   Session & Auth:
   - startSession()                    Initialize with timeout
   - isLoggedIn()                      Check if logged in
   - currentUser()                     Get user array
   - requireLogin()                    Redirect if unauthorized
   - requireRole(['admin', 'manager']) Check role

   Protection:
   - csrfField()                       Output hidden token
   - verifyCsrf($token)                Validate token
   - clean($input)                     Sanitize htmlspecialchars
   - sanitizeEmail($email)             Validate email
   - sanitizeInt($value)               Cast to int

   Output:
   - formatCurrency($amount)           Format money
   - formatDate($date)                 Format as 'd M Y'
   - timeAgo($datetime)                "2 hours ago"
   - statusBadge($status)              HTML badge

   Database:
   - generateCode($prefix, $table, $col)  Auto-generate CLI-00001
   - logActivity($action, $module, $id, $desc)  Audit trail
   - getDashboardStats()               Query stat counts
   - checkExpiringServices()           Query 30-day expiry

   Notifications:
   - flashAlert()                      Display message
   - setFlash($type, $msg)             Store message
   - addNotification($user_id, $msg)   Create alert
   - getNotifications($user_id)        Fetch alerts

⚡ COMMON CODE SNIPPETS:

   1. Safe Query & Output:
   ───────────────────────
   $stmt = $db->prepare("SELECT * FROM clients WHERE id = ?");
   $stmt->execute([$id]);
   $client = $stmt->fetch();
   echo clean($client['name']);

   2. Form with CSRF:
   ──────────────────
   <form method="POST">
       <?php echo csrfField(); ?>
       <input type="text" name="name" value="">
       <button type="submit">Save</button>
   </form>

   3. ActivityLogging:
   ───────────────────
   logActivity('CREATE', 'invoices', $invoiceId, "New invoice for Client #{$clientId}");

   4. Flash Message:
   ──────────────────
   setFlash('success', 'Invoice saved successfully!');
   // In template:
   <?php echo flashAlert(); ?>

   5. Role Check:
   ──────────────
   requireRole(['admin', 'manager']);  // Redirect if not this role

🚀 QUICK START:

   Step 1: Import database (see above)
   Step 2: Start Apache & MySQL (XAMPP)
   Step 3: Visit http://localhost/EcomZone-CMS
   Step 4: Login with credentials above
   Step 5: Explore modules (Clients → Projects → Invoices)

💾 IF SOMETHING BREAKS:

   1. Check error log: Check browser console (F12)
   2. Verify database exists: In MySQL, SHOW DATABASES;
   3. Check file permissions: uploads/ folder writable?
   4. Clear session: Close browser or delete cookies
   5. Review code: Check comment at top of PHP file

📞 CONTACTS IN CODE:

   Database schema:          database/cms_ecomzone.sql (top comment)
   Configuration:            config/config.php (inline comments)
   Helper functions:         includes/functions.php (function headers)
   Security patterns:        includes/functions.php (CSRF/sanitization)
   Module template:          admin/clients/index.php (structure reference)

🎯 NEXT FEATURES TO BUILD:

   □ admin/invoices/add.php      Invoice builder with line items
   □ admin/projects/view.php     Project Kanban board for tasks
   □ admin/quotations/add.php    Quotation builder
   □ admin/meetings/index.php    Meeting scheduler
   □ admin/reports/index.php     Analytics dashboard
   □ PDF generation for invoices
   □ Email notifications
   □ Client portal (read-only access)
   □ API endpoints for mobile app
   □ Webhook integrations (payment gateways)

═══════════════════════════════════════════════════════════════════

Remember:
• ALWAYS use prepared statements (never concatenate SQL)
• ALWAYS sanitize output with clean()
• ALWAYS use csrfField() on forms
• ALWAYS check roles with requireRole()
• ALWAYS log important actions with logActivity()

═══════════════════════════════════════════════════════════════════
*/

// This is a reference file - delete or rename to .txt if needed
?>
