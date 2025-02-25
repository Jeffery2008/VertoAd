-- Error Tracking System Tables
-- Migration: 001_create_error_tracking_tables

-- Error Logs Table
CREATE TABLE IF NOT EXISTS `error_logs` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `error_type` VARCHAR(50) NOT NULL COMMENT 'Error type (e.g., PHP, JavaScript, Application)',
    `error_code` INT NULL COMMENT 'Error code if available',
    `error_message` TEXT NOT NULL COMMENT 'Error message',
    `error_file` VARCHAR(255) NULL COMMENT 'File where the error occurred',
    `error_line` INT NULL COMMENT 'Line number where the error occurred',
    `stack_trace` TEXT NULL COMMENT 'Stack trace if available',
    `request_uri` VARCHAR(255) NULL COMMENT 'Request URI when the error occurred',
    `request_method` VARCHAR(10) NULL COMMENT 'HTTP method used',
    `request_params` TEXT NULL COMMENT 'Request parameters as JSON',
    `user_id` BIGINT UNSIGNED NULL COMMENT 'User ID if authenticated',
    `user_type` VARCHAR(20) NULL COMMENT 'User type (admin, advertiser, publisher)',
    `ip_address` VARCHAR(45) NULL COMMENT 'IP address of the client',
    `user_agent` TEXT NULL COMMENT 'User agent string',
    `referer` VARCHAR(255) NULL COMMENT 'HTTP referer',
    `severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium' COMMENT 'Error severity',
    `status` ENUM('new', 'in_progress', 'resolved', 'ignored') NOT NULL DEFAULT 'new' COMMENT 'Error status',
    `notes` TEXT NULL COMMENT 'Notes about the error',
    `resolved_by` BIGINT UNSIGNED NULL COMMENT 'User who resolved the error',
    `resolved_at` DATETIME NULL COMMENT 'When the error was resolved',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the error was logged',
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT 'When the error log was updated',
    INDEX `idx_error_logs_type` (`error_type`),
    INDEX `idx_error_logs_severity` (`severity`),
    INDEX `idx_error_logs_status` (`status`),
    INDEX `idx_error_logs_created_at` (`created_at`),
    INDEX `idx_error_logs_user_id` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Error Notification Subscriptions Table
CREATE TABLE IF NOT EXISTS `error_notification_subscriptions` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL COMMENT 'User to be notified',
    `notification_method` ENUM('email', 'sms', 'dashboard') NOT NULL DEFAULT 'dashboard' COMMENT 'Notification method',
    `notification_target` VARCHAR(255) NULL COMMENT 'Email or phone number for notification',
    `min_severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'high' COMMENT 'Minimum severity to trigger notification',
    `error_types` VARCHAR(255) NULL COMMENT 'Comma-separated list of error types to notify for (NULL for all)',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1 COMMENT 'Whether the subscription is active',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_error_subscriptions_user` (`user_id`),
    INDEX `idx_error_subscriptions_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Error Categories Table
CREATE TABLE IF NOT EXISTS `error_categories` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL COMMENT 'Category name',
    `description` TEXT NULL COMMENT 'Category description',
    `default_severity` ENUM('low', 'medium', 'high', 'critical') NOT NULL DEFAULT 'medium' COMMENT 'Default severity for this category',
    `auto_assign_patterns` TEXT NULL COMMENT 'JSON patterns to auto-assign errors to this category',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `unq_error_categories_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Error Notifications Table
CREATE TABLE IF NOT EXISTS `error_notifications` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `error_log_id` BIGINT UNSIGNED NOT NULL COMMENT 'Related error log',
    `subscription_id` BIGINT UNSIGNED NOT NULL COMMENT 'Related subscription',
    `notification_method` ENUM('email', 'sms', 'dashboard') NOT NULL COMMENT 'Notification method used',
    `notification_target` VARCHAR(255) NULL COMMENT 'Email or phone number used',
    `notification_content` TEXT NOT NULL COMMENT 'Content of the notification',
    `status` ENUM('pending', 'sent', 'failed') NOT NULL DEFAULT 'pending' COMMENT 'Notification status',
    `error_message` TEXT NULL COMMENT 'Error message if sending failed',
    `sent_at` DATETIME NULL COMMENT 'When the notification was sent',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX `idx_error_notifications_error` (`error_log_id`),
    INDEX `idx_error_notifications_subscription` (`subscription_id`),
    INDEX `idx_error_notifications_status` (`status`),
    FOREIGN KEY (`error_log_id`) REFERENCES `error_logs` (`id`) ON DELETE CASCADE,
    FOREIGN KEY (`subscription_id`) REFERENCES `error_notification_subscriptions` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default error categories
INSERT INTO `error_categories` (`name`, `description`, `default_severity`, `auto_assign_patterns`) VALUES
('PHP Errors', 'PHP runtime errors including notices, warnings, and fatal errors', 'high', '{"error_type": "PHP"}'),
('Database Errors', 'Database connection and query errors', 'critical', '{"error_message": ["database", "sql", "query", "mysqli", "PDO"]}'),
('API Errors', 'Errors in API requests and responses', 'high', '{"request_uri": ["/api/"]}'),
('JavaScript Errors', 'Client-side JavaScript errors', 'medium', '{"error_type": "JavaScript"}'),
('Authentication Errors', 'Authentication and authorization failures', 'high', '{"error_message": ["auth", "login", "permission", "access denied"]}'),
('System Errors', 'System-level errors like file operations and resource usage', 'critical', '{"error_message": ["filesystem", "disk", "memory"]}');

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS `idx_error_logs_resolved` ON `error_logs` (`resolved_at`);
CREATE INDEX IF NOT EXISTS `idx_error_logs_user_agent` ON `error_logs` (`user_agent`(50));
CREATE INDEX IF NOT EXISTS `idx_error_logs_request_uri` ON `error_logs` (`request_uri`(50)); 