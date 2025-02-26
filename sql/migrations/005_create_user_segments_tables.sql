-- Migration 005: Create User Segments Tables
-- This migration adds tables for user segmentation, audience targeting, and user behavior tracking

-- User Segments Table - Defines segments of users for targeted advertising
CREATE TABLE IF NOT EXISTS `user_segments` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'User who created this segment',
  `criteria` JSON NOT NULL COMMENT 'JSON object with segment criteria',
  `is_dynamic` TINYINT(1) UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = Auto-update based on criteria, 0 = Static list',
  `last_updated` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_user_segments_user` (`user_id`),
  CONSTRAINT `fk_user_segments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Segment Members Table - For static segments, stores the visitor IDs that belong to the segment
CREATE TABLE IF NOT EXISTS `segment_members` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT(11) UNSIGNED NOT NULL,
  `visitor_id` VARCHAR(64) NOT NULL COMMENT 'Unique visitor identifier (cookie ID)',
  `added_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_segment_visitor` (`segment_id`, `visitor_id`),
  KEY `idx_segment_members_segment` (`segment_id`),
  CONSTRAINT `fk_segment_members_segment` FOREIGN KEY (`segment_id`) REFERENCES `user_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visitor Profiles Table - Stores information about visitors for segmentation
CREATE TABLE IF NOT EXISTS `visitor_profiles` (
  `visitor_id` VARCHAR(64) NOT NULL COMMENT 'Unique visitor identifier (cookie ID)',
  `first_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_seen` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `visit_count` INT(11) UNSIGNED NOT NULL DEFAULT 1,
  `total_page_views` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_sessions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_time_spent` INT(11) UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total time in seconds',
  `first_referrer` VARCHAR(255) DEFAULT NULL,
  `last_referrer` VARCHAR(255) DEFAULT NULL,
  `first_utm_source` VARCHAR(100) DEFAULT NULL,
  `first_utm_medium` VARCHAR(100) DEFAULT NULL,
  `first_utm_campaign` VARCHAR(100) DEFAULT NULL,
  `last_utm_source` VARCHAR(100) DEFAULT NULL,
  `last_utm_medium` VARCHAR(100) DEFAULT NULL,
  `last_utm_campaign` VARCHAR(100) DEFAULT NULL,
  `geo_country` VARCHAR(2) DEFAULT NULL,
  `geo_region` VARCHAR(100) DEFAULT NULL,
  `geo_city` VARCHAR(100) DEFAULT NULL,
  `device_type` VARCHAR(20) DEFAULT NULL,
  `browser` VARCHAR(50) DEFAULT NULL,
  `os` VARCHAR(50) DEFAULT NULL,
  `language` VARCHAR(10) DEFAULT NULL,
  `interests` JSON DEFAULT NULL COMMENT 'JSON array of interest categories',
  `tags` JSON DEFAULT NULL COMMENT 'JSON array of custom tags',
  `custom_attributes` JSON DEFAULT NULL COMMENT 'JSON object with custom attributes',
  PRIMARY KEY (`visitor_id`),
  KEY `idx_visitor_profiles_geo` (`geo_country`, `geo_region`, `geo_city`),
  KEY `idx_visitor_profiles_device` (`device_type`, `browser`, `os`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Visitor Events Table - Tracks visitor behavior events for segmentation
CREATE TABLE IF NOT EXISTS `visitor_events` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `visitor_id` VARCHAR(64) NOT NULL,
  `event_type` VARCHAR(50) NOT NULL COMMENT 'page_view, ad_click, conversion, etc.',
  `event_category` VARCHAR(100) DEFAULT NULL,
  `event_action` VARCHAR(100) DEFAULT NULL,
  `event_label` VARCHAR(255) DEFAULT NULL,
  `event_value` DECIMAL(10,2) DEFAULT NULL,
  `page_url` VARCHAR(255) DEFAULT NULL,
  `page_title` VARCHAR(255) DEFAULT NULL,
  `ad_id` INT(11) UNSIGNED DEFAULT NULL,
  `referrer` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL COMMENT 'Supports IPv6',
  `user_agent` TEXT,
  `occurred_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_visitor_events_visitor` (`visitor_id`),
  KEY `idx_visitor_events_type` (`event_type`),
  KEY `idx_visitor_events_ad` (`ad_id`),
  KEY `idx_visitor_events_datetime` (`occurred_at`),
  KEY `idx_visitor_events_category_action` (`event_category`, `event_action`),
  CONSTRAINT `fk_visitor_events_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Segment Targeting Table - Links ad campaigns to user segments for targeting
CREATE TABLE IF NOT EXISTS `segment_targeting` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `ad_id` INT(11) UNSIGNED NOT NULL,
  `segment_id` INT(11) UNSIGNED NOT NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_segment_targeting_unique` (`ad_id`, `segment_id`),
  KEY `idx_segment_targeting_ad` (`ad_id`),
  KEY `idx_segment_targeting_segment` (`segment_id`),
  CONSTRAINT `fk_segment_targeting_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_segment_targeting_segment` FOREIGN KEY (`segment_id`) REFERENCES `user_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Audience Insights Table - Pre-calculated audience metrics for reporting
CREATE TABLE IF NOT EXISTS `audience_insights` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `user_id` INT(11) UNSIGNED NOT NULL COMMENT 'Advertiser ID',
  `segment_id` INT(11) UNSIGNED DEFAULT NULL COMMENT 'NULL means all audience',
  `date` DATE NOT NULL,
  `total_visitors` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `new_visitors` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `returning_visitors` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_sessions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_page_views` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_ad_impressions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_ad_clicks` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `total_conversions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `conversion_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `device_breakdown` JSON DEFAULT NULL COMMENT 'JSON object with device type counts',
  `geo_breakdown` JSON DEFAULT NULL COMMENT 'JSON object with geographic counts',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_audience_insights_unique` (`user_id`, `segment_id`, `date`),
  KEY `idx_audience_insights_user` (`user_id`),
  KEY `idx_audience_insights_segment` (`segment_id`),
  KEY `idx_audience_insights_date` (`date`),
  CONSTRAINT `fk_audience_insights_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_audience_insights_segment` FOREIGN KEY (`segment_id`) REFERENCES `user_segments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Segment Performance Table - Pre-calculated metrics for each segment's ad performance
CREATE TABLE IF NOT EXISTS `segment_performance` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `segment_id` INT(11) UNSIGNED NOT NULL,
  `ad_id` INT(11) UNSIGNED NOT NULL,
  `date` DATE NOT NULL,
  `impressions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `clicks` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `ctr` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `conversions` INT(11) UNSIGNED NOT NULL DEFAULT 0,
  `conversion_rate` DECIMAL(5,2) NOT NULL DEFAULT 0,
  `conversion_value` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `cost` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `roi` DECIMAL(10,2) NOT NULL DEFAULT 0,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_segment_performance_unique` (`segment_id`, `ad_id`, `date`),
  KEY `idx_segment_performance_segment` (`segment_id`),
  KEY `idx_segment_performance_ad` (`ad_id`),
  KEY `idx_segment_performance_date` (`date`),
  CONSTRAINT `fk_segment_performance_segment` FOREIGN KEY (`segment_id`) REFERENCES `user_segments` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_segment_performance_ad` FOREIGN KEY (`ad_id`) REFERENCES `advertisements` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Add index for performance
CREATE INDEX idx_visitor_profiles_last_seen ON visitor_profiles(last_seen);
CREATE INDEX idx_visitor_profiles_visit_count ON visitor_profiles(visit_count); 