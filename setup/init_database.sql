-- Drop existing tables if they exist
DROP TABLE IF EXISTS ad_statistics;
DROP TABLE IF EXISTS geographic_stats;
DROP TABLE IF EXISTS device_stats;
DROP TABLE IF EXISTS advertisements;
DROP TABLE IF EXISTS ad_positions;
DROP TABLE IF EXISTS advertisers;
DROP TABLE IF EXISTS users;

-- Users table
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    role ENUM('admin', 'advertiser') NOT NULL,
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Advertisers table (extends users)
CREATE TABLE advertisers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL UNIQUE,
    company_name VARCHAR(100),
    contact_name VARCHAR(100),
    contact_phone VARCHAR(20),
    billing_address TEXT,
    total_budget DECIMAL(10,2) DEFAULT 0.00,
    remaining_budget DECIMAL(10,2) DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Ad positions table
CREATE TABLE ad_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    width INT NOT NULL,
    height INT NOT NULL,
    placement_type ENUM('sidebar', 'banner', 'popup', 'inline', 'floating') NOT NULL,
    price_per_impression DECIMAL(10,4) DEFAULT 0.00,
    price_per_click DECIMAL(10,4) DEFAULT 0.00,
    rotation_interval INT DEFAULT 5000, -- milliseconds between rotations
    max_ads INT DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Advertisements table
CREATE TABLE advertisements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advertiser_id INT NOT NULL,
    position_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content LONGTEXT NOT NULL, -- JSON format for canvas data
    original_content LONGTEXT NOT NULL, -- For version history
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'active', 'paused', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    priority INT DEFAULT 50, -- 0-100, higher number = higher priority
    total_budget DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    remaining_budget DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id) ON DELETE CASCADE,
    FOREIGN KEY (position_id) REFERENCES ad_positions(id) ON DELETE CASCADE,
    INDEX idx_status_dates (status, start_date, end_date),
    INDEX idx_priority (priority)
) ENGINE=InnoDB;

-- Ad statistics table
CREATE TABLE ad_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    spent_amount DECIMAL(10,2) DEFAULT 0.00,
    bounce_rate DECIMAL(5,2) DEFAULT 0.00,
    avg_view_time INT DEFAULT 0, -- in seconds
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ad_date (ad_id, date)
) ENGINE=InnoDB;

-- Geographic statistics
CREATE TABLE geographic_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    country VARCHAR(2), -- ISO country code
    region VARCHAR(100),
    city VARCHAR(100),
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ad_location (ad_id, country, region, city)
) ENGINE=InnoDB;

-- Device statistics
CREATE TABLE device_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    device_type VARCHAR(50), -- desktop, mobile, tablet
    browser VARCHAR(50),
    os VARCHAR(50),
    resolution VARCHAR(20),
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    UNIQUE KEY unique_ad_device (ad_id, device_type, browser, os, resolution)
) ENGINE=InnoDB;

-- Insert default admin user
INSERT INTO users (username, password, email, role) 
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin@example.com', 'admin');

-- Insert sample ad positions
INSERT INTO ad_positions (name, description, width, height, placement_type, price_per_impression, price_per_click) 
VALUES 
('Sidebar Top', 'Top position in the right sidebar', 300, 250, 'sidebar', 0.001, 0.05),
('Header Banner', 'Large banner at the top of pages', 728, 90, 'banner', 0.002, 0.08),
('Content Inline', 'Advertisement within content', 468, 60, 'inline', 0.0015, 0.06);
