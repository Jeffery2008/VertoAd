-- 广告表
CREATE TABLE IF NOT EXISTS advertisements (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    advertiser_id BIGINT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    image_url VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NOT NULL,
    status ENUM('active', 'paused', 'pending', 'rejected') NOT NULL DEFAULT 'pending',
    budget DECIMAL(10,2) NOT NULL DEFAULT '0.00',
    daily_budget DECIMAL(10,2) NOT NULL DEFAULT '0.00',
    bid_amount DECIMAL(10,4) NOT NULL DEFAULT '0.0000',
    start_date DATE DEFAULT NULL,
    end_date DATE DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 广告位表
CREATE TABLE IF NOT EXISTS ad_positions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL,
    description TEXT,
    width INT NOT NULL,
    height INT NOT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_name (name)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 广告展示记录表
CREATE TABLE IF NOT EXISTS impressions (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    viewer_id VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    referer VARCHAR(255) DEFAULT NULL,
    location_country VARCHAR(2) DEFAULT NULL,
    location_region VARCHAR(50) DEFAULT NULL,
    location_city VARCHAR(50) DEFAULT NULL,
    device_type VARCHAR(20) DEFAULT NULL,
    cost DECIMAL(10,4) NOT NULL DEFAULT '0.0000',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    KEY idx_viewer (viewer_id),
    KEY idx_location (location_country, location_region, location_city),
    KEY idx_device (device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 广告点击记录表
CREATE TABLE IF NOT EXISTS clicks (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    impression_id BIGINT UNSIGNED NOT NULL,
    viewer_id VARCHAR(50) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(255) NOT NULL,
    referer VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (impression_id) REFERENCES impressions(id) ON DELETE CASCADE,
    KEY idx_viewer (viewer_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 广告定向表
CREATE TABLE IF NOT EXISTS ad_targeting (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    ad_id BIGINT UNSIGNED NOT NULL,
    target_type VARCHAR(50) NOT NULL,
    target_value VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES advertisements(id) ON DELETE CASCADE,
    KEY idx_targeting (target_type, target_value)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 