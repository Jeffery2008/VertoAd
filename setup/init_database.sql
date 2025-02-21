-- Drop existing tables if they exist in reverse order of creation to handle foreign key constraints
DROP TABLE IF EXISTS ad_clicks;
DROP TABLE IF EXISTS ad_drawings;
DROP TABLE IF EXISTS ad_impressions;
DROP TABLE IF EXISTS ad_spend_daily;
DROP TABLE IF EXISTS ad_viewability;
DROP TABLE IF EXISTS advertisements;
DROP TABLE IF EXISTS ad_positions;
DROP TABLE IF EXISTS key_activation_log;
DROP TABLE IF EXISTS key_batches;
DROP TABLE IF EXISTS product_keys;
DROP TABLE IF EXISTS system_settings;
DROP TABLE IF EXISTS users;

-- Users and Authentication
CREATE TABLE users (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'advertiser', 'publisher') NOT NULL,
    status ENUM('active', 'suspended', 'pending') NOT NULL DEFAULT 'pending',
    balance DECIMAL(15,4) NOT NULL DEFAULT 0.0000,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_email (email),
    INDEX idx_status (status)
);

-- Product Keys System
CREATE TABLE product_keys (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_hash VARCHAR(64) NOT NULL UNIQUE COMMENT 'SHA256 hash of the key',
    key_value VARCHAR(29) NOT NULL UNIQUE COMMENT 'Format: XXXXX-XXXXX-XXXXX-XXXXX-XXXXX',
    batch_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    status ENUM('active', 'used', 'revoked') NOT NULL DEFAULT 'active',
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    used_at TIMESTAMP NULL,
    used_by BIGINT UNSIGNED NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (used_by) REFERENCES users(id),
    INDEX idx_key_hash (key_hash),
    INDEX idx_key_value (key_value),
    INDEX idx_batch_status (batch_id, status)
) ENGINE=InnoDB;

CREATE TABLE key_batches (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    batch_name VARCHAR(100) NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    quantity INT UNSIGNED NOT NULL,
    created_by BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    notes TEXT,
    FOREIGN KEY (created_by) REFERENCES users(id)
) ENGINE=InnoDB;

CREATE TABLE key_activation_log (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    key_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    amount DECIMAL(15,4) NOT NULL,
    balance_before DECIMAL(15,4) NOT NULL,
    balance_after DECIMAL(15,4) NOT NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (key_id) REFERENCES product_keys(id),
    FOREIGN KEY (user_id) REFERENCES users(id),
    INDEX idx_user_created (user_id, created_at)
) ENGINE=InnoDB;

-- Ad Positions Table
CREATE TABLE ad_positions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    format VARCHAR(50) NOT NULL COMMENT 'banner, rectangle, skyscraper etc.',
    width INT NOT NULL,
    height INT NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- Advertisements Table
CREATE TABLE advertisements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    position_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    image_url VARCHAR(512) NOT NULL,
    click_url VARCHAR(512) NOT NULL,
    format VARCHAR(50) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    status ENUM('draft', 'pending', 'active', 'paused', 'completed', 'rejected') DEFAULT 'draft',
    device_targeting TEXT,
    geo_targeting TEXT,
    bid_amount DECIMAL(10,2) DEFAULT 0.00,
    daily_budget DECIMAL(10,2) DEFAULT 0.00,
    total_budget DECIMAL(10,2) DEFAULT 0.00,
    impressions INT DEFAULT 0,
    viewable_impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES ad_positions(id),
    FOREIGN KEY (advertiser_id) REFERENCES users(id),
    INDEX idx_status_date (status, start_date, end_date),
    INDEX idx_advertiser (advertiser_id)
) ENGINE=InnoDB;

-- Ad Spend Daily Tracking
CREATE TABLE ad_spend_daily (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    spend DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    UNIQUE KEY unique_daily_ad_spend (ad_id, date),
    INDEX idx_date (date)
) ENGINE=InnoDB;

-- Impressions Tracking Table
CREATE TABLE ad_impressions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    ip_address VARCHAR(45),
    device_type VARCHAR(20),
    viewport TEXT,
    position TEXT,
    cost DECIMAL(10,4) DEFAULT 0.0000 COMMENT 'Cost per impression',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    INDEX idx_ad_time (ad_id, timestamp)
) ENGINE=InnoDB;

-- Viewability Tracking Table
CREATE TABLE ad_viewability (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    device_type VARCHAR(20),
    viewport TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    INDEX idx_ad_time (ad_id, timestamp)
) ENGINE=InnoDB;

-- Clicks Tracking Table
CREATE TABLE ad_clicks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    ip_address VARCHAR(45),
    device_type VARCHAR(20),
    cost DECIMAL(10,4) DEFAULT 0.0000 COMMENT 'Cost per click',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    INDEX idx_ad_time (ad_id, timestamp)
) ENGINE=InnoDB;

-- Ad Canvas Drawings Table
CREATE TABLE ad_drawings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    image_data MEDIUMTEXT NOT NULL COMMENT 'Base64 encoded image data',
    drawing_state TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
) ENGINE=InnoDB;

-- System Settings Table
CREATE TABLE system_settings (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_key (setting_key)
) ENGINE=InnoDB;

-- Create indexes for better query performance
CREATE INDEX idx_ad_status ON advertisements(status);
CREATE INDEX idx_ad_dates ON advertisements(start_date, end_date);
CREATE INDEX idx_impressions_timestamp ON ad_impressions(timestamp);
CREATE INDEX idx_viewability_timestamp ON ad_viewability(timestamp);
CREATE INDEX idx_clicks_timestamp ON ad_clicks(timestamp);
