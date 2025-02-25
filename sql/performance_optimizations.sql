-- Performance Optimization SQL Script

-- Add indexes for improved ad serving performance
CREATE INDEX IF NOT EXISTS `idx_advertisements_status_position` 
  ON `advertisements` (`status`, `position_id`);

CREATE INDEX IF NOT EXISTS `idx_advertisements_status_dates` 
  ON `advertisements` (`status`, `start_date`, `end_date`);

CREATE INDEX IF NOT EXISTS `idx_advertisements_advertiser` 
  ON `advertisements` (`advertiser_id`);

-- Add indexes for faster ad targeting queries
CREATE INDEX IF NOT EXISTS `idx_ad_targeting_type_value` 
  ON `ad_targeting` (`targeting_type`, `targeting_value`);

CREATE INDEX IF NOT EXISTS `idx_ad_targeting_ad_id` 
  ON `ad_targeting` (`ad_id`);

-- Add indexes for improved analytics performance
CREATE INDEX IF NOT EXISTS `idx_impressions_date` 
  ON `ad_impressions` (`created_at`);

CREATE INDEX IF NOT EXISTS `idx_impressions_ad_position` 
  ON `ad_impressions` (`ad_id`, `position_id`);

CREATE INDEX IF NOT EXISTS `idx_impressions_location` 
  ON `ad_impressions` (`country`, `region`);

CREATE INDEX IF NOT EXISTS `idx_impressions_device` 
  ON `ad_impressions` (`device_type`);

CREATE INDEX IF NOT EXISTS `idx_clicks_date` 
  ON `ad_clicks` (`created_at`);

CREATE INDEX IF NOT EXISTS `idx_clicks_ad_position` 
  ON `ad_clicks` (`ad_id`, `position_id`);

-- Add indexes for ad review system
CREATE INDEX IF NOT EXISTS `idx_ad_reviews_status` 
  ON `ad_reviews` (`status`);

CREATE INDEX IF NOT EXISTS `idx_ad_review_logs_created_at` 
  ON `ad_review_logs` (`created_at`);

-- Optimize tables (reclaim unused space, defragment indexes)
OPTIMIZE TABLE `advertisements`;
OPTIMIZE TABLE `ad_targeting`;
OPTIMIZE TABLE `ad_impressions`;
OPTIMIZE TABLE `ad_clicks`;
OPTIMIZE TABLE `ad_positions`;
OPTIMIZE TABLE `ad_reviews`;
OPTIMIZE TABLE `ad_review_logs`;
OPTIMIZE TABLE `violation_types`;

-- Add query hints by modifying views (if any)
-- This creates a view that pre-joins common tables for ad analytics
CREATE OR REPLACE VIEW `v_ad_performance` AS
SELECT 
    a.id AS ad_id,
    a.title AS ad_title,
    a.advertiser_id,
    u.username AS advertiser_name,
    p.id AS position_id,
    p.name AS position_name,
    COUNT(DISTINCT i.id) AS impressions,
    COUNT(DISTINCT c.id) AS clicks,
    IFNULL(COUNT(DISTINCT c.id) / NULLIF(COUNT(DISTINCT i.id), 0) * 100, 0) AS ctr,
    a.budget,
    a.bid_amount
FROM 
    `advertisements` a
    JOIN `users` u ON a.advertiser_id = u.id
    JOIN `ad_positions` p ON a.position_id = p.id
    LEFT JOIN `ad_impressions` i ON a.id = i.ad_id
    LEFT JOIN `ad_clicks` c ON a.id = c.ad_id
GROUP BY 
    a.id, a.title, a.advertiser_id, u.username, p.id, p.name, a.budget, a.bid_amount;

-- Create materialized data summaries for analytics (using regular tables that would be updated by a cron job)
CREATE TABLE IF NOT EXISTS `ad_performance_daily` (
    `id` BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    `ad_id` BIGINT UNSIGNED NOT NULL,
    `date` DATE NOT NULL,
    `impressions` INT UNSIGNED NOT NULL DEFAULT 0,
    `clicks` INT UNSIGNED NOT NULL DEFAULT 0,
    `unique_visitors` INT UNSIGNED NOT NULL DEFAULT 0,
    `spend` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `idx_ad_date` (`ad_id`, `date`),
    FOREIGN KEY (`ad_id`) REFERENCES `advertisements`(`id`) ON DELETE CASCADE
);

-- Sample procedure to populate the daily summary table (would be run by a cron job)
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS `update_daily_performance_stats`(in_date DATE)
BEGIN
    INSERT INTO `ad_performance_daily` (`ad_id`, `date`, `impressions`, `clicks`, `unique_visitors`, `spend`)
    SELECT 
        i.ad_id,
        DATE(i.created_at) AS date,
        COUNT(DISTINCT i.id) AS impressions,
        COUNT(DISTINCT c.id) AS clicks,
        COUNT(DISTINCT i.ip_address) AS unique_visitors,
        COUNT(DISTINCT i.id) * a.bid_amount / 1000 AS spend
    FROM 
        `ad_impressions` i
        LEFT JOIN `ad_clicks` c ON i.ad_id = c.ad_id AND DATE(c.created_at) = DATE(i.created_at)
        JOIN `advertisements` a ON i.ad_id = a.id
    WHERE 
        DATE(i.created_at) = in_date
    GROUP BY 
        i.ad_id, DATE(i.created_at)
    ON DUPLICATE KEY UPDATE
        impressions = VALUES(impressions),
        clicks = VALUES(clicks),
        unique_visitors = VALUES(unique_visitors),
        spend = VALUES(spend),
        updated_at = CURRENT_TIMESTAMP;
END //
DELIMITER ;

-- Add configuration variables table for system settings including cache TTL
CREATE TABLE IF NOT EXISTS `system_config` (
    `key` VARCHAR(100) PRIMARY KEY,
    `value` TEXT NOT NULL,
    `type` ENUM('string', 'integer', 'float', 'boolean', 'json') NOT NULL DEFAULT 'string',
    `description` TEXT,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default configuration values
INSERT INTO `system_config` (`key`, `value`, `type`, `description`) VALUES
('cache_ttl_ad_serving', '300', 'integer', 'Time to live for ad serving cache in seconds'),
('cache_ttl_analytics', '600', 'integer', 'Time to live for analytics cache in seconds'),
('cache_ttl_targeting', '900', 'integer', 'Time to live for targeting criteria cache in seconds'),
('maintenance_mode', 'false', 'boolean', 'Whether the system is in maintenance mode'),
('debug_mode', 'false', 'boolean', 'Whether debug mode is enabled'),
('max_ads_per_position', '5', 'integer', 'Maximum number of ads to fetch per position for rotation'),
('default_bid_amount', '1.00', 'float', 'Default bid amount for new advertisements'),
('impressions_batch_size', '100', 'integer', 'Batch size for processing impression records'); 