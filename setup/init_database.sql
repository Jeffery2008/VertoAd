-- Ad system database schema

-- Create users table for authentication and basic user info
CREATE TABLE users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(100) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'advertiser', 'reviewer') NOT NULL,
    status ENUM('active', 'suspended', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create advertisers table for advertiser-specific info
CREATE TABLE advertisers (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    company_name VARCHAR(255),
    credit_score INT DEFAULT 100,
    spending_level INT DEFAULT 0,
    total_spent DECIMAL(15,2) DEFAULT 0,
    balance DECIMAL(15,2) DEFAULT 0,
    status ENUM('active', 'suspended', 'blacklisted') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create ad_positions table for managing ad slots
CREATE TABLE ad_positions (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    width INT NOT NULL,
    height INT NOT NULL,
    placement_type ENUM('sidebar', 'banner', 'popup', 'inline', 'floating') NOT NULL,
    price_per_impression DECIMAL(10,4) NOT NULL,
    price_per_click DECIMAL(10,4) NOT NULL,
    rotation_interval INT DEFAULT 5000,
    max_ads INT DEFAULT 1,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create advertisements table for storing ad content and settings
CREATE TABLE advertisements (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advertiser_id INT NOT NULL,
    position_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    content JSON NOT NULL,
    original_content JSON NOT NULL,
    start_date DATE NOT NULL,
    end_date DATE NOT NULL,
    status ENUM('pending', 'active', 'paused', 'rejected', 'completed') NOT NULL DEFAULT 'pending',
    priority INT DEFAULT 50,
    total_budget DECIMAL(15,2) NOT NULL,
    remaining_budget DECIMAL(15,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id),
    FOREIGN KEY (position_id) REFERENCES ad_positions(id)
);

-- Create ad_templates table for reusable ad designs
CREATE TABLE ad_templates (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    content JSON NOT NULL,
    width INT NOT NULL,
    height INT NOT NULL,
    category VARCHAR(100),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Create ad_reviews table for approval workflow
CREATE TABLE ad_reviews (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    reviewer_id INT NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL,
    review_level INT NOT NULL,
    comments TEXT,
    review_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    FOREIGN KEY (reviewer_id) REFERENCES users(id)
);

-- Create ad_statistics table for tracking performance metrics
CREATE TABLE ad_statistics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    date DATE NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    spent_amount DECIMAL(15,2) DEFAULT 0,
    bounce_rate DECIMAL(5,2) DEFAULT 0,
    avg_view_time INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    UNIQUE KEY unique_daily_stats (ad_id, date)
);

-- Create geographic_stats table for location-based analytics
CREATE TABLE geographic_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    country CHAR(2) NOT NULL,
    region VARCHAR(100) NOT NULL,
    city VARCHAR(100) NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    UNIQUE KEY unique_geo_stats (ad_id, country, region, city)
);

-- Create device_stats table for device-based analytics
CREATE TABLE device_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    device_type VARCHAR(50) NOT NULL,
    browser VARCHAR(50) NOT NULL,
    os VARCHAR(50) NOT NULL,
    resolution VARCHAR(20) NOT NULL,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    conversions INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id),
    UNIQUE KEY unique_device_stats (ad_id, device_type, browser, os, resolution)
);

-- Create orders table for financial transactions
CREATE TABLE orders (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advertiser_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    type ENUM('deposit', 'withdraw', 'refund', 'charge') NOT NULL,
    status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL,
    transaction_id VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id)
);

-- Create invoices table for billing
CREATE TABLE invoices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    advertiser_id INT NOT NULL,
    order_id INT NOT NULL,
    amount DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'issued', 'paid', 'cancelled') NOT NULL,
    invoice_number VARCHAR(100) NOT NULL UNIQUE,
    issued_date DATE,
    due_date DATE,
    paid_date DATE,
    billing_info JSON NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES advertisers(id),
    FOREIGN KEY (order_id) REFERENCES orders(id)
);

-- Create pricing_rules table for dynamic pricing
CREATE TABLE pricing_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    position_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    type ENUM('time', 'position', 'auction', 'discount') NOT NULL,
    conditions JSON NOT NULL,
    price_adjustment DECIMAL(10,4) NOT NULL,
    priority INT DEFAULT 0,
    start_date DATE,
    end_date DATE,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (position_id) REFERENCES ad_positions(id)
);

-- Create targeting_rules table for ad targeting
CREATE TABLE targeting_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    type ENUM('geo', 'device', 'time', 'audience') NOT NULL,
    conditions JSON NOT NULL,
    priority INT DEFAULT 0,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
);

-- Create audit_log table for operation tracking
CREATE TABLE audit_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INT NOT NULL,
    old_values JSON,
    new_values JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create error_log table for PHP error tracking
CREATE TABLE error_log (
    id INT PRIMARY KEY AUTO_INCREMENT,
    error_type VARCHAR(50) NOT NULL,
    error_message TEXT NOT NULL,
    error_file VARCHAR(255) NOT NULL,
    error_line INT NOT NULL,
    error_trace TEXT,
    request_uri TEXT,
    request_method VARCHAR(10),
    request_params JSON,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- Create performance_metrics table for detailed ad metrics
CREATE TABLE performance_metrics (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    metric_type ENUM('viewability', 'engagement', 'performance') NOT NULL,
    value FLOAT NOT NULL,
    additional_data JSON,
    session_id VARCHAR(100) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id)
);
