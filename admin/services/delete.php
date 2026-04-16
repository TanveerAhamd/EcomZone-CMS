<?php
/**
 * SERVICES - DEPRECATED
 * This module has been replaced by Service Categories
 * Redirecting to new module...
 */

require_once __DIR__ . '/../../includes/init.php';

requireLogin();

// Redirect to the new Service Categories module
redirect('admin/service-categories/index.php');
    }
}

redirect('admin/services/index.php');
