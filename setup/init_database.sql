-- Create tables for ad system

-- Ad positions table
CREATE TABLE IF NOT EXISTS ad_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(100) UNIQUE NOT NULL,
    format VARCHAR(50) NOT NULL,  -- banner, rectangle, skyscraper etc.
    width INT NOT NULL,
    height INT NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive', 'pending') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Advertisements table
CREATE TABLE IF NOT EXISTS advertisements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advertiser_id INT NOT NULL,
    position_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    image_url VARCHAR(512) NOT NULL,
    click_url VARCHAR(512) NOT NULL,
    format VARCHAR(50) NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    start_date TIMESTAMP NOT NULL,
    end_date TIMESTAMP NOT NULL,
    status ENUM('draft', 'pending', 'active', 'paused', 'completed', 'rejected') DEFAULT 'draft',
    device_targeting JSON,  -- Array of device types: desktop, mobile, tablet
    geo_targeting JSON,     -- Array of country codes
    bid_amount DECIMAL(10,2) DEFAULT 0.00,
    daily_budget DECIMAL(10,2) DEFAULT 0.00,
    total_budget DECIMAL(10,2) DEFAULT 0.00,
    impressions INT DEFAULT 0,
    viewable_impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES ad_positions(id),
    FOREIGN KEY (advertiser_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- User balances table
CREATE TABLE IF NOT EXISTS user_balances (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    UNIQUE KEY unique_user_balance (user_id)
) ENGINE=InnoDB;

-- Balance transactions table
CREATE TABLE IF NOT EXISTS balance_transactions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    type ENUM('deposit', 'withdrawal', 'ad_spend', 'refund', 'adjustment') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
    description TEXT,
    reference_id VARCHAR(100),  -- For linking to external payment systems
    admin_id INT,  -- ID of admin who processed the transaction
    previous_balance DECIMAL(10,2) NOT NULL,
    new_balance DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (admin_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- Ad spend daily tracking
CREATE TABLE IF NOT EXISTS ad_spend_daily (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    spend DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    UNIQUE KEY unique_daily_ad_spend (ad_id, date)
) ENGINE=InnoDB;

-- Impressions tracking table
CREATE TABLE IF NOT EXISTS ad_impressions (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    ip_address VARCHAR(45),
    device_type VARCHAR(20),
    viewport JSON,
    position JSON,
    cost DECIMAL(10,4) DEFAULT 0.0000,  -- Cost per impression
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
) ENGINE=InnoDB;

-- Viewability tracking table
CREATE TABLE IF NOT EXISTS ad_viewability (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    device_type VARCHAR(20),
    viewport JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
) ENGINE=InnoDB;

-- Clicks tracking table
CREATE TABLE IF NOT EXISTS ad_clicks (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    timestamp TIMESTAMP NOT NULL,
    url VARCHAR(512),
    user_agent VARCHAR(512),
    ip_address VARCHAR(45),
    device_type VARCHAR(20),
    cost DECIMAL(10,4) DEFAULT 0.0000,  -- Cost per click
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
) ENGINE=InnoDB;

-- Ad drawings table (for storing canvas-created ads)
CREATE TABLE IF NOT EXISTS ad_drawings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    image_data MEDIUMTEXT NOT NULL,  -- Base64 encoded image data
    drawing_state JSON,              -- Canvas state for editing
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
) ENGINE=InnoDB;

-- System settings table
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value TEXT NOT NULL,
    description TEXT,
    is_public BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Create indexes for better query performance
CREATE INDEX idx_ad_status ON advertisements(status);
CREATE INDEX idx_ad_dates ON advertisements(start_date, end_date);
CREATE INDEX idx_impressions_timestamp ON ad_impressions(timestamp);
CREATE INDEX idx_viewability_timestamp ON ad_viewability(timestamp);
CREATE INDEX idx_clicks_timestamp ON ad_clicks(timestamp);
CREATE INDEX idx_balance_transactions_date ON balance_transactions(created_at);
CREATE INDEX idx_balance_transactions_user ON balance_transactions(user_id);
CREATE INDEX idx_ad_spend_daily_date ON ad_spend_daily(date);

-- Create indexes for foreign keys
CREATE INDEX idx_ad_position ON advertisements(position_id);
CREATE INDEX idx_ad_advertiser ON advertisements(advertiser_id);
CREATE INDEX idx_impression_ad ON ad_impressions(ad_id);
CREATE INDEX idx_viewability_ad ON ad_viewability(ad_id);
CREATE INDEX idx_clicks_ad ON ad_clicks(ad_id);
CREATE INDEX idx_drawings_ad ON ad_drawings(ad_id);
