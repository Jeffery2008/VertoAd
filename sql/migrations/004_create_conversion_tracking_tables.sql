-- Migration for Conversion Tracking System

-- Create table for storing conversion types
CREATE TABLE IF NOT EXISTS `conversion_types` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `value_type` ENUM('fixed', 'variable') NOT NULL DEFAULT 'fixed',
    `default_value` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_conversion_type_name` (`name`)
) ENGINE=InnoDB;

-- Create table for storing conversion events
CREATE TABLE IF NOT EXISTS `conversions` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `ad_id` BIGINT UNSIGNED NOT NULL,
    `click_id` BIGINT UNSIGNED NULL,
    `conversion_type_id` BIGINT UNSIGNED NOT NULL,
    `visitor_id` VARCHAR(64) NULL,
    `order_id` VARCHAR(100) NULL,
    `value` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `referrer` TEXT,
    `conversion_time` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`click_id`) REFERENCES `ad_clicks`(`id`) ON DELETE SET NULL,
    FOREIGN KEY (`conversion_type_id`) REFERENCES `conversion_types`(`id`) ON DELETE CASCADE,
    INDEX `idx_conversion_ad` (`ad_id`),
    INDEX `idx_conversion_click` (`click_id`),
    INDEX `idx_conversion_type` (`conversion_type_id`),
    INDEX `idx_conversion_time` (`conversion_time`),
    INDEX `idx_conversion_visitor` (`visitor_id`)
) ENGINE=InnoDB;

-- Create table for conversion attribution rules
CREATE TABLE IF NOT EXISTS `attribution_rules` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `attribution_model` ENUM('last_click', 'first_click', 'linear', 'time_decay', 'position_based') NOT NULL DEFAULT 'last_click',
    `lookback_window` INT UNSIGNED NOT NULL DEFAULT 30, -- in days
    `is_default` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_attribution_rule_name` (`name`)
) ENGINE=InnoDB;

-- Create table for conversion funnels
CREATE TABLE IF NOT EXISTS `conversion_funnels` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `name` VARCHAR(100) NOT NULL,
    `description` TEXT,
    `steps` JSON NOT NULL, -- Array of step definitions
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_funnel_name` (`name`)
) ENGINE=InnoDB;

-- Create table for funnel step events
CREATE TABLE IF NOT EXISTS `funnel_events` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `funnel_id` BIGINT UNSIGNED NOT NULL,
    `visitor_id` VARCHAR(64) NOT NULL,
    `ad_id` BIGINT UNSIGNED NULL,
    `step` VARCHAR(50) NOT NULL,
    `step_index` INT UNSIGNED NOT NULL,
    `data` JSON NULL,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (`funnel_id`) REFERENCES `conversion_funnels`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE SET NULL,
    INDEX `idx_funnel_visitor` (`funnel_id`, `visitor_id`),
    INDEX `idx_funnel_step` (`funnel_id`, `step`, `step_index`),
    INDEX `idx_funnel_ad` (`ad_id`, `funnel_id`)
) ENGINE=InnoDB;

-- Create table for tracking visitor sessions
CREATE TABLE IF NOT EXISTS `visitor_sessions` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `visitor_id` VARCHAR(64) NOT NULL,
    `ad_id` BIGINT UNSIGNED NULL,
    `session_key` VARCHAR(64) NOT NULL,
    `landing_page` TEXT NOT NULL,
    `referrer` TEXT,
    `ip_address` VARCHAR(45) NOT NULL,
    `user_agent` TEXT,
    `device_type` VARCHAR(20),
    `browser` VARCHAR(50),
    `country` VARCHAR(2),
    `region` VARCHAR(100),
    `city` VARCHAR(100),
    `session_start` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `session_end` TIMESTAMP NULL,
    `duration_seconds` INT UNSIGNED NULL,
    `page_views` INT UNSIGNED NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE SET NULL,
    INDEX `idx_visitor_session` (`visitor_id`, `session_key`),
    INDEX `idx_session_time` (`session_start`, `session_end`),
    INDEX `idx_session_ad` (`ad_id`)
) ENGINE=InnoDB;

-- Create table for ROI analytics
CREATE TABLE IF NOT EXISTS `roi_analytics` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `ad_id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `conversions` INT UNSIGNED NOT NULL DEFAULT 0,
    `spend` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
    `revenue` DECIMAL(15, 4) NOT NULL DEFAULT 0.0000,
    `roi` DECIMAL(15, 4) AS (CASE WHEN spend > 0 THEN ((revenue - spend) / spend) * 100 ELSE 0 END) STORED,
    `conversion_rate` DECIMAL(15, 4) AS (CASE WHEN clicks > 0 THEN (conversions / clicks) * 100 ELSE 0 END) STORED,
    `cost_per_conversion` DECIMAL(15, 4) AS (CASE WHEN conversions > 0 THEN spend / conversions ELSE 0 END) STORED,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `idx_roi_ad_date` (`ad_id`, `date`),
    INDEX `idx_roi_date` (`date`)
) ENGINE=InnoDB;

-- Create table for conversion pixels
CREATE TABLE IF NOT EXISTS `conversion_pixels` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NOT NULL,
    `pixel_id` VARCHAR(64) NOT NULL,
    `conversion_type_id` BIGINT UNSIGNED NOT NULL,
    `is_active` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`conversion_type_id`) REFERENCES `conversion_types`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `idx_pixel_id` (`pixel_id`),
    INDEX `idx_pixel_user` (`user_id`),
    INDEX `idx_pixel_active` (`is_active`)
) ENGINE=InnoDB;

-- Insert default attribution rules
INSERT INTO `attribution_rules` 
    (`name`, `description`, `attribution_model`, `lookback_window`, `is_default`) 
VALUES
    ('Last Click', 'Attributes the conversion to the last clicked ad', 'last_click', 30, 1),
    ('First Click', 'Attributes the conversion to the first clicked ad', 'first_click', 30, 0),
    ('Linear', 'Distributes conversion credit equally across all touchpoints', 'linear', 30, 0),
    ('Time Decay', 'Gives more credit to touchpoints closer to conversion', 'time_decay', 30, 0),
    ('Position Based', 'Gives 40% credit to first and last touchpoints, 20% to middle', 'position_based', 30, 0);

-- Insert default conversion types
INSERT INTO `conversion_types` 
    (`name`, `description`, `value_type`, `default_value`) 
VALUES
    ('Purchase', 'Completed purchase transaction', 'variable', 0.00),
    ('Signup', 'User signup or registration', 'fixed', 5.00),
    ('Lead', 'Lead generation form submission', 'fixed', 10.00),
    ('Page View', 'Key page viewed', 'fixed', 0.50),
    ('Add to Cart', 'Product added to shopping cart', 'fixed', 1.00),
    ('Download', 'File or app download', 'fixed', 2.00),
    ('Custom', 'Custom conversion event', 'variable', 0.00); 