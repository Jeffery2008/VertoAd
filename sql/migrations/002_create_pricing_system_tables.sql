-- Pricing System Migration

-- Pricing Plans Table
CREATE TABLE IF NOT EXISTS `pricing_plans` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Pricing Models Table
CREATE TABLE IF NOT EXISTS `pricing_models` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `type` ENUM('cpm', 'cpc', 'time_based', 'position_based', 'mixed') NOT NULL,
    `description` TEXT,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Time-Based Pricing Rules Table
CREATE TABLE IF NOT EXISTS `time_pricing_rules` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `position_id` BIGINT UNSIGNED NOT NULL,
    `day_of_week` TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday, 6=Saturday',
    `start_hour` TINYINT UNSIGNED NOT NULL COMMENT '0-23',
    `end_hour` TINYINT UNSIGNED NOT NULL COMMENT '0-23',
    `multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00 COMMENT 'Price multiplier for this time slot',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`position_id`) REFERENCES `ad_positions`(`id`) ON DELETE CASCADE,
    INDEX `idx_position_time` (`position_id`, `day_of_week`, `start_hour`, `end_hour`)
) ENGINE=InnoDB;

-- Position Pricing Table
CREATE TABLE IF NOT EXISTS `position_pricing` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `position_id` BIGINT UNSIGNED NOT NULL,
    `pricing_model_id` BIGINT UNSIGNED NOT NULL,
    `base_price` DECIMAL(10,4) NOT NULL COMMENT 'Base price in the selected pricing model',
    `min_bid` DECIMAL(10,4) NOT NULL DEFAULT 0.00 COMMENT 'Minimum bid amount for this position',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`position_id`) REFERENCES `ad_positions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pricing_model_id`) REFERENCES `pricing_models`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_position_model` (`position_id`, `pricing_model_id`)
) ENGINE=InnoDB;

-- Pricing Plan Rules Table (associates plans with positions and models)
CREATE TABLE IF NOT EXISTS `pricing_plan_rules` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `plan_id` BIGINT UNSIGNED NOT NULL,
    `position_id` BIGINT UNSIGNED NOT NULL,
    `pricing_model_id` BIGINT UNSIGNED NOT NULL,
    `discount_percentage` DECIMAL(5,2) NOT NULL DEFAULT 0.00 COMMENT 'Discount percentage for this plan-position combination',
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`plan_id`) REFERENCES `pricing_plans`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`position_id`) REFERENCES `ad_positions`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`pricing_model_id`) REFERENCES `pricing_models`(`id`) ON DELETE RESTRICT,
    UNIQUE KEY `unique_plan_position_model` (`plan_id`, `position_id`, `pricing_model_id`)
) ENGINE=InnoDB;

-- Discount Codes Table
CREATE TABLE IF NOT EXISTS `discount_codes` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `code` VARCHAR(50) NOT NULL UNIQUE,
    `description` TEXT,
    `discount_type` ENUM('percentage', 'fixed_amount') NOT NULL DEFAULT 'percentage',
    `discount_value` DECIMAL(10,2) NOT NULL,
    `min_purchase_amount` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `max_discount_amount` DECIMAL(10,2) NULL,
    `valid_from` TIMESTAMP NOT NULL,
    `valid_until` TIMESTAMP NULL,
    `usage_limit` INT UNSIGNED NULL COMMENT 'Maximum number of times this code can be used',
    `usage_count` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of times this code has been used',
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_by` BIGINT UNSIGNED NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`created_by`) REFERENCES `users`(`id`),
    INDEX `idx_code` (`code`),
    INDEX `idx_valid_dates` (`valid_from`, `valid_until`)
) ENGINE=InnoDB;

-- Discount Usage Log Table
CREATE TABLE IF NOT EXISTS `discount_usage_log` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `discount_id` BIGINT UNSIGNED NOT NULL,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `order_id` BIGINT UNSIGNED NULL,
    `amount_before_discount` DECIMAL(15,4) NOT NULL,
    `discount_amount` DECIMAL(15,4) NOT NULL,
    `amount_after_discount` DECIMAL(15,4) NOT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`discount_id`) REFERENCES `discount_codes`(`id`),
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`),
    INDEX `idx_user_discount` (`user_id`, `discount_id`)
) ENGINE=InnoDB;

-- Add pricing_model_id column to advertisements table if it doesn't exist
ALTER TABLE `advertisements` 
ADD COLUMN IF NOT EXISTS `pricing_model_id` BIGINT UNSIGNED NULL AFTER `position_id`,
ADD CONSTRAINT `fk_ad_pricing_model` FOREIGN KEY (`pricing_model_id`) REFERENCES `pricing_models`(`id`);

-- Add price_multiplier column to advertisements table for custom pricing multipliers
ALTER TABLE `advertisements` 
ADD COLUMN IF NOT EXISTS `price_multiplier` DECIMAL(5,2) NOT NULL DEFAULT 1.00 AFTER `bid_amount`;

-- Insert default pricing models
INSERT INTO `pricing_models` (`name`, `type`, `description`) VALUES 
('Standard CPM', 'cpm', 'Standard cost per thousand impressions pricing'),
('Standard CPC', 'cpc', 'Standard cost per click pricing'),
('Time-Based Premium', 'time_based', 'Pricing varies based on time of day and day of week'),
('Position Premium', 'position_based', 'Premium pricing based on ad position'),
('Mixed Bidding', 'mixed', 'Combination of CPM and CPC with optimization');

-- Insert default pricing plan
INSERT INTO `pricing_plans` (`name`, `description`) VALUES 
('Standard Plan', 'Default pricing plan for all advertisers'); 