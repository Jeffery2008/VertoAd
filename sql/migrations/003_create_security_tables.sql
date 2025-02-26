-- Migration for Security Enhancement System

-- CSRF Tokens Table
CREATE TABLE IF NOT EXISTS `csrf_tokens` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `token` VARCHAR(100) NOT NULL,
    `session_id` VARCHAR(100) NOT NULL,
    `page_id` VARCHAR(100) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_session_page` (`session_id`, `page_id`),
    INDEX `idx_token` (`token`),
    INDEX `idx_expiry` (`expires_at`)
) ENGINE=InnoDB;

-- API Keys Table
CREATE TABLE IF NOT EXISTS `api_keys` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `key_hash` VARCHAR(100) NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `permissions` JSON NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `last_used_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_key_hash` (`key_hash`),
    INDEX `idx_user_keys` (`user_id`),
    INDEX `idx_active_keys` (`is_active`, `expires_at`)
) ENGINE=InnoDB;

-- API Key Usage Table (for rate limiting)
CREATE TABLE IF NOT EXISTS `api_key_usage` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `api_key_id` BIGINT UNSIGNED NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `timestamp` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`api_key_id`) REFERENCES `api_keys`(`id`) ON DELETE CASCADE,
    INDEX `idx_key_endpoint` (`api_key_id`, `endpoint`),
    INDEX `idx_usage_time` (`api_key_id`, `timestamp`)
) ENGINE=InnoDB;

-- Rate Limiting Table
CREATE TABLE IF NOT EXISTS `rate_limits` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `identifier` VARCHAR(100) NOT NULL COMMENT 'IP address, API key, or user ID',
    `type` ENUM('ip', 'api_key', 'user') NOT NULL,
    `endpoint` VARCHAR(255) NOT NULL,
    `request_count` INT UNSIGNED NOT NULL DEFAULT 1,
    `first_request_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `last_request_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `unique_identifier_endpoint` (`identifier`, `type`, `endpoint`),
    INDEX `idx_request_time` (`last_request_at`)
) ENGINE=InnoDB;

-- Proof of Work Challenges Table
CREATE TABLE IF NOT EXISTS `pow_challenges` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `challenge` VARCHAR(100) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `session_id` VARCHAR(100) NULL,
    `difficulty` TINYINT UNSIGNED NOT NULL DEFAULT 5,
    `is_solved` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `solved_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NOT NULL,
    INDEX `idx_ip_challenge` (`ip_address`, `challenge`),
    INDEX `idx_session_challenge` (`session_id`, `challenge`)
) ENGINE=InnoDB;

-- Encryption Keys Table
CREATE TABLE IF NOT EXISTS `encryption_keys` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `key_identifier` VARCHAR(50) NOT NULL UNIQUE,
    `encrypted_key` TEXT NOT NULL,
    `algorithm` VARCHAR(50) NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `rotated_at` TIMESTAMP NULL,
    INDEX `idx_active_keys` (`is_active`)
) ENGINE=InnoDB;

-- Authentication Attempts Table
CREATE TABLE IF NOT EXISTS `auth_attempts` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `username` VARCHAR(255) NOT NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `is_successful` TINYINT(1) NOT NULL DEFAULT 0,
    `attempt_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX `idx_username_ip` (`username`, `ip_address`),
    INDEX `idx_attempt_time` (`attempt_time`)
) ENGINE=InnoDB;

-- Access Tokens Table (for OAuth2)
CREATE TABLE IF NOT EXISTS `access_tokens` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `token_hash` VARCHAR(100) NOT NULL,
    `refresh_token_hash` VARCHAR(100) NULL,
    `client_id` VARCHAR(100) NOT NULL,
    `scope` TEXT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `revoked_at` TIMESTAMP NULL,
    `expires_at` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_token_hash` (`token_hash`),
    INDEX `idx_user_tokens` (`user_id`, `is_active`),
    INDEX `idx_refresh_token` (`refresh_token_hash`)
) ENGINE=InnoDB;

-- OAuth Clients Table
CREATE TABLE IF NOT EXISTS `oauth_clients` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `client_id` VARCHAR(100) NOT NULL UNIQUE,
    `client_secret_hash` VARCHAR(100) NOT NULL,
    `redirect_uri` TEXT NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `is_confidential` TINYINT(1) NOT NULL DEFAULT 1,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_client_id` (`client_id`)
) ENGINE=InnoDB;

-- Security Audit Log Table
CREATE TABLE IF NOT EXISTS `security_audit_log` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `action` VARCHAR(100) NOT NULL,
    `entity_type` VARCHAR(50) NULL,
    `entity_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` VARCHAR(255) NOT NULL,
    `details` JSON NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE SET NULL,
    INDEX `idx_user_action` (`user_id`, `action`),
    INDEX `idx_entity` (`entity_type`, `entity_id`),
    INDEX `idx_audit_time` (`created_at`)
) ENGINE=InnoDB;

-- Add security-related columns to existing tables
ALTER TABLE `users` 
    ADD COLUMN IF NOT EXISTS `two_factor_enabled` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_hash`,
    ADD COLUMN IF NOT EXISTS `two_factor_secret` VARCHAR(100) NULL AFTER `two_factor_enabled`,
    ADD COLUMN IF NOT EXISTS `last_login_at` TIMESTAMP NULL AFTER `updated_at`,
    ADD COLUMN IF NOT EXISTS `last_login_ip` VARCHAR(45) NULL AFTER `last_login_at`,
    ADD COLUMN IF NOT EXISTS `failed_login_attempts` INT UNSIGNED NOT NULL DEFAULT 0 AFTER `last_login_ip`,
    ADD COLUMN IF NOT EXISTS `locked_until` TIMESTAMP NULL AFTER `failed_login_attempts`;

-- Add indexes for security tables
CREATE INDEX IF NOT EXISTS `idx_two_factor` ON `users`(`two_factor_enabled`);
CREATE INDEX IF NOT EXISTS `idx_user_login` ON `users`(`username`, `status`, `locked_until`); 