-- CMS-ecomzone Database Schema
-- Complete Client Service & Project Management System

CREATE DATABASE IF NOT EXISTS `cms_ecomzone`;
USE `cms_ecomzone`;

-- ============================================
-- USERS TABLE
-- ============================================
CREATE TABLE `users` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `role` ENUM('admin', 'manager', 'staff') DEFAULT 'staff',
  `phone` VARCHAR(20),
  `whatsapp_number` VARCHAR(20),
  `avatar` VARCHAR(255),
  `status` ENUM('active', 'inactive') DEFAULT 'active',
  `last_login` DATETIME,
  `failed_attempts` INT DEFAULT 0,
  `locked_until` DATETIME,
  `remember_token` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `role` (`role`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CLIENTS TABLE
-- ============================================
CREATE TABLE `clients` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_code` VARCHAR(20) NOT NULL UNIQUE,
  `client_name` VARCHAR(100) NOT NULL,
  `company_name` VARCHAR(100),
  `email` VARCHAR(100),
  `primary_phone` VARCHAR(20),
  `secondary_phone` VARCHAR(20),
  `website` VARCHAR(255),
  `address` TEXT,
  `city` VARCHAR(50),
  `country` VARCHAR(50),
  `client_status` ENUM('active', 'inactive', 'suspended') DEFAULT 'active',
  `assigned_user_id` INT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `assigned_user_id` (`assigned_user_id`),
  FOREIGN KEY (`assigned_user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CLIENT DOCUMENTS TABLE
-- ============================================
CREATE TABLE `client_documents` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `document_name` VARCHAR(255) NOT NULL,
  `document_type` VARCHAR(50) NOT NULL,
  `file_path` VARCHAR(255) NOT NULL,
  `uploaded_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`uploaded_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `client_id` (`client_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- SERVICES TABLE
-- ============================================
CREATE TABLE `services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `service_name` VARCHAR(100) NOT NULL,
  `category` ENUM('tax_compliance', 'company_setup', 'accounting', 'consulting', 'legal', 'other') NOT NULL,
  `price` DECIMAL(12, 2) NOT NULL,
  `description` TEXT,
  `renewal_period` ENUM('monthly', 'quarterly', 'half_yearly', 'yearly', 'as_needed') DEFAULT 'yearly',
  `status` ENUM('active', 'inactive', 'discontinued') DEFAULT 'active',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX `category` (`category`),
  INDEX `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- CLIENT SERVICES TABLE (Assignment)
-- ============================================
CREATE TABLE `client_services` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT NOT NULL,
  `service_id` INT NOT NULL,
  `start_date` DATE NOT NULL,
  `renewal_date` DATE NOT NULL,
  `price` DECIMAL(12, 2),
  `status` ENUM('active', 'expired', 'cancelled', 'renewed') DEFAULT 'active',
  `alert_days` INT DEFAULT 30,
  `alert_30_sent` TINYINT DEFAULT 0,
  `alert_15_sent` TINYINT DEFAULT 0,
  `alert_7_sent` TINYINT DEFAULT 0,
  `alert_1_sent` TINYINT DEFAULT 0,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `client_id` (`client_id`),
  INDEX `service_id` (`service_id`),
  INDEX `renewal_date` (`renewal_date`),
  INDEX `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PROJECTS TABLE
-- ============================================
CREATE TABLE `projects` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `project_code` VARCHAR(20) NOT NULL UNIQUE,
  `project_name` VARCHAR(150) NOT NULL,
  `client_id` INT NOT NULL,
  `service_id` INT,
  `description` LONGTEXT,
  `start_date` DATE,
  `deadline` DATE,
  `budget` DECIMAL(12, 2),
  `progress` INT DEFAULT 0,
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `status` ENUM('pending', 'in_progress', 'completed', 'on_hold') DEFAULT 'pending',
  `assigned_to` INT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `client_id` (`client_id`),
  INDEX `status` (`status`),
  INDEX `deadline` (`deadline`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TASKS TABLE
-- ============================================
CREATE TABLE `tasks` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `project_id` INT NOT NULL,
  `parent_id` INT,
  `task_title` VARCHAR(200) NOT NULL,
  `description` LONGTEXT,
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `status` ENUM('todo', 'in_progress', 'review', 'done') DEFAULT 'todo',
  `due_date` DATE,
  `assigned_to` INT,
  `sort_order` INT DEFAULT 0,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`parent_id`) REFERENCES `tasks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `project_id` (`project_id`),
  INDEX `status` (`status`),
  INDEX `assigned_to` (`assigned_to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- QUOTATIONS TABLE
-- ============================================
CREATE TABLE `quotations` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `quotation_number` VARCHAR(20) NOT NULL UNIQUE,
  `client_id` INT NOT NULL,
  `project_id` INT,
  `title` VARCHAR(150),
  `issue_date` DATE NOT NULL,
  `valid_until` DATE,
  `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `tax_percent` DECIMAL(5, 2) DEFAULT 0,
  `tax_amount` DECIMAL(12, 2) DEFAULT 0,
  `discount_percent` DECIMAL(5, 2) DEFAULT 0,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `total` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `notes` LONGTEXT,
  `terms` LONGTEXT,
  `status` ENUM('draft', 'sent', 'accepted', 'rejected', 'expired') DEFAULT 'draft',
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `client_id` (`client_id`),
  INDEX `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- QUOTATION ITEMS TABLE
-- ============================================
CREATE TABLE `quotation_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `quotation_id` INT NOT NULL,
  `service_id` INT,
  `description` VARCHAR(255),
  `quantity` INT DEFAULT 1,
  `unit_price` DECIMAL(12, 2),
  `total` DECIMAL(12, 2),
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVOICES TABLE
-- ============================================
CREATE TABLE `invoices` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `invoice_number` VARCHAR(20) NOT NULL UNIQUE,
  `client_id` INT NOT NULL,
  `project_id` INT,
  `quotation_id` INT,
  `title` VARCHAR(150),
  `issue_date` DATE NOT NULL,
  `due_date` DATE,
  `subtotal` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `tax_percent` DECIMAL(5, 2) DEFAULT 0,
  `tax_amount` DECIMAL(12, 2) DEFAULT 0,
  `discount_percent` DECIMAL(5, 2) DEFAULT 0,
  `discount_amount` DECIMAL(12, 2) DEFAULT 0,
  `total` DECIMAL(12, 2) NOT NULL DEFAULT 0,
  `paid_amount` DECIMAL(12, 2) DEFAULT 0,
  `balance` DECIMAL(12, 2) DEFAULT 0,
  `notes` LONGTEXT,
  `terms` LONGTEXT,
  `status` ENUM('draft', 'sent', 'partial', 'paid', 'overdue', 'cancelled') DEFAULT 'draft',
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`quotation_id`) REFERENCES `quotations`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `client_id` (`client_id`),
  INDEX `status` (`status`),
  INDEX `due_date` (`due_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- INVOICE ITEMS TABLE
-- ============================================
CREATE TABLE `invoice_items` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `invoice_id` INT NOT NULL,
  `service_id` INT,
  `description` VARCHAR(255),
  `quantity` INT DEFAULT 1,
  `unit_price` DECIMAL(12, 2),
  `total` DECIMAL(12, 2),
  `sort_order` INT DEFAULT 0,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`service_id`) REFERENCES `services`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- PAYMENTS TABLE
-- ============================================
CREATE TABLE `payments` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `payment_number` VARCHAR(20) NOT NULL UNIQUE,
  `invoice_id` INT NOT NULL,
  `client_id` INT NOT NULL,
  `amount` DECIMAL(12, 2) NOT NULL,
  `payment_date` DATE NOT NULL,
  `payment_method` ENUM('cash', 'bank_transfer', 'cheque', 'online', 'other') DEFAULT 'bank_transfer',
  `transaction_id` VARCHAR(100),
  `notes` TEXT,
  `receipt_file` VARCHAR(255),
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`invoice_id`) REFERENCES `invoices`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `invoice_id` (`invoice_id`),
  INDEX `payment_date` (`payment_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- MEETINGS TABLE
-- ============================================
CREATE TABLE `meetings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT,
  `project_id` INT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150) NOT NULL,
  `meeting_date` DATE NOT NULL,
  `meeting_time` TIME,
  `attendees` TEXT,
  `agenda` LONGTEXT,
  `notes` LONGTEXT,
  `action_items` LONGTEXT,
  `follow_up_date` DATE,
  `is_private` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `user_id` (`user_id`),
  INDEX `meeting_date` (`meeting_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- TODOS TABLE
-- ============================================
CREATE TABLE `todos` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `project_id` INT,
  `title` VARCHAR(200) NOT NULL,
  `description` LONGTEXT,
  `status` ENUM('todo', 'in_progress', 'done') DEFAULT 'todo',
  `priority` ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
  `due_date` DATE,
  `is_private` TINYINT DEFAULT 1,
  `sort_order` INT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`project_id`) REFERENCES `projects`(`id`) ON DELETE SET NULL,
  INDEX `user_id` (`user_id`),
  INDEX `status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- WHATSAPP LOGS TABLE
-- ============================================
CREATE TABLE `whatsapp_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `client_id` INT,
  `client_service_id` INT,
  `phone_number` VARCHAR(20) NOT NULL,
  `message` LONGTEXT,
  `message_type` ENUM('service_expiry', 'invoice_due', 'payment_reminder', 'custom') DEFAULT 'custom',
  `status` ENUM('pending', 'sent', 'failed') DEFAULT 'pending',
  `sent_at` DATETIME,
  `error_message` TEXT,
  `created_by` INT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`client_id`) REFERENCES `clients`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`client_service_id`) REFERENCES `client_services`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`created_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `status` (`status`),
  INDEX `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- NOTIFICATIONS TABLE
-- ============================================
CREATE TABLE `notifications` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT NOT NULL,
  `title` VARCHAR(150),
  `message` LONGTEXT,
  `type` ENUM('info', 'success', 'warning', 'danger') DEFAULT 'info',
  `link` VARCHAR(255),
  `is_read` TINYINT DEFAULT 0,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  INDEX `user_id` (`user_id`),
  INDEX `is_read` (`is_read`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- ACTIVITY LOGS TABLE
-- ============================================
CREATE TABLE `activity_logs` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `user_id` INT,
  `action` VARCHAR(50),
  `module` VARCHAR(50),
  `record_id` INT,
  `description` TEXT,
  `ip_address` VARCHAR(45),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  INDEX `user_id` (`user_id`),
  INDEX `created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- SITE SETTINGS TABLE
-- ============================================
CREATE TABLE `site_settings` (
  `id` INT PRIMARY KEY AUTO_INCREMENT,
  `setting_key` VARCHAR(100) NOT NULL UNIQUE,
  `setting_value` LONGTEXT,
  `setting_group` VARCHAR(50),
  `updated_by` INT,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`updated_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- DEFAULT DATA INSERTION
-- ============================================

-- Default Admin User
INSERT INTO `users` (`name`, `email`, `password`, `role`, `status`) VALUES 
('Administrator', 'admin@cms-ecomzone.com', '$2y$12$j1U8eWmQXz8e8N7rVR1u4.Bvt8Z5L8X6Q9Y8P7V6K5M4N3O2L1K0', 'admin', 'active');

-- Default Services
INSERT INTO `services` (`service_name`, `category`, `price`, `description`, `renewal_period`) VALUES
('UAE Tax Filing', 'uae_tax', 5000.00, 'Professional tax filing service for UAE businesses', 'yearly'),
('USA Tax Filing', 'usa_tax', 8000.00, 'Comprehensive US tax and compliance services', 'yearly'),
('UK Tax Filing', 'uk_tax', 6000.00, 'UK tax return and compliance assistance', 'yearly'),
('VAT Registration', 'vat', 3500.00, 'VAT registration and compliance setup', 'yearly'),
('Company Formation', 'company', 10000.00, 'Complete company registration assistance', 'one_time'),
('Bookkeeping Services', 'bookkeeping', 4000.00, 'Monthly bookkeeping and account management', 'monthly'),
('Noon Integration', 'noon', 2500.00, 'Noon seller account setup and management', 'monthly'),
('Amazon Integration', 'amazon', 3000.00, 'Amazon seller account setup and optimization', 'monthly'),
('Shopify Setup', 'pioneer', 5000.00, 'E-commerce store setup and configuration', 'one_time'),
('Wise Account Setup', 'wise', 1000.00, 'International payment processing setup', 'one_time'),
('PayPal Integration', 'paypal', 1500.00, 'PayPal merchant account setup', 'one_time'),
('UK Ltd Formation', 'uk_ltd', 7000.00, 'UK limited company formation and registration', 'one_time');

-- Default Site Settings
INSERT INTO `site_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_name', 'CMS-ecomzone', 'general'),
('site_tagline', 'Client Service & Project Management System', 'general'),
('site_email', 'info@cms-ecomzone.com', 'general'),
('site_phone', '+92 300 1234567', 'general'),
('site_address', 'Dubai, UAE', 'general'),
('currency', 'PKR', 'general'),
('currency_symbol', '₨', 'general'),
('timezone', 'Asia/Karachi', 'general'),
('date_format', 'd M Y', 'general'),
('logo_file', 'logo.png', 'general'),
('invoice_prefix', 'INV-', 'invoice'),
('quotation_prefix', 'QUO-', 'invoice'),
('payment_prefix', 'PAY-', 'invoice'),
('client_prefix', 'CLI-', 'invoice'),
('project_prefix', 'PRJ-', 'invoice'),
('default_tax_percent', '5', 'invoice'),
('invoice_footer_text', 'Thank you for your business', 'invoice'),
('invoice_terms', 'Payment terms: Net 30', 'invoice'),
('whatsapp_enabled', '0', 'whatsapp'),
('whatsapp_api_key', '', 'whatsapp'),
('whatsapp_phone_id', '', 'whatsapp'),
('whatsapp_business_id', '', 'whatsapp'),
('alert_days', '30,15,7,1', 'whatsapp'),
('primary_color', '#6418C3', 'appearance'),
('sidebar_color', '#1D1D35', 'appearance'),
('theme_mode', 'light', 'appearance');
