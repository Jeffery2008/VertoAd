-- 创建系统设置表（无外键依赖）
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建用户表（无外键依赖）
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'advertiser', 'publisher') NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建激活码表（依赖 users 表）
CREATE TABLE IF NOT EXISTS activation_keys (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    key_code VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status ENUM('unused', 'used') NOT NULL DEFAULT 'unused',
    created_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    used_by INT UNSIGNED NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_key_code (key_code),
    KEY idx_status (status),
    KEY idx_created_at (created_at),
    KEY idx_used_at (used_at),
    FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 创建广告表（依赖 users 表）
CREATE TABLE IF NOT EXISTS ads (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    title VARCHAR(100) NOT NULL,
    description TEXT,
    content TEXT NOT NULL,
    image_url VARCHAR(255),
    target_url VARCHAR(255),
    status ENUM('draft', 'pending', 'approved', 'rejected', 'active', 'paused', 'deleted') NOT NULL DEFAULT 'draft',
    budget DECIMAL(10,2) NOT NULL,
    remaining_budget DECIMAL(10,2) NOT NULL,
    cost_per_view DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建广告位表（依赖 users 表）
CREATE TABLE IF NOT EXISTS zones (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    width INT NOT NULL,
    height INT NOT NULL,
    publisher_id INT UNSIGNED NOT NULL,
    status ENUM('active', 'paused', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publisher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建广告位表（依赖 users 表）
CREATE TABLE IF NOT EXISTS ad_placements (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    name VARCHAR(100) NOT NULL,
    code TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 创建展示记录表（依赖 ads 和 ad_placements 表）
CREATE TABLE IF NOT EXISTS impressions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    placement_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(200) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_impression (ad_id, placement_id, ip_address(20)),
    FOREIGN KEY (ad_id) REFERENCES ads(id),
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id)
);

-- 创建广告浏览记录表（依赖 ads 和 users 表）
CREATE TABLE IF NOT EXISTS ad_views (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    zone_id INT UNSIGNED NOT NULL,
    publisher_id INT UNSIGNED NOT NULL,
    viewer_ip VARCHAR(45) NOT NULL,
    views INT UNSIGNED NOT NULL DEFAULT 1,
    cost DECIMAL(10,2) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (zone_id) REFERENCES zones(id) ON DELETE CASCADE,
    FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_view_check (ad_id, publisher_id, viewer_ip(20), viewed_at),
    UNIQUE KEY unique_view_24h (ad_id, publisher_id, viewer_ip(20))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建点击记录表（依赖 impressions, ads, users, ad_placements 表）
CREATE TABLE IF NOT EXISTS clicks (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    impression_id INT UNSIGNED NOT NULL,
    ad_id INT UNSIGNED NOT NULL,
    publisher_id INT UNSIGNED NOT NULL,
    placement_id INT UNSIGNED NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referrer TEXT,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payout DECIMAL(10, 4) NOT NULL DEFAULT 0,
    FOREIGN KEY (impression_id) REFERENCES impressions(id) ON DELETE CASCADE,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE
);

-- 创建错误日志表（依赖 users 表）
CREATE TABLE IF NOT EXISTS errors (
    id INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255) NOT NULL,
    line INT NOT NULL,
    trace TEXT,
    request_data TEXT,
    user_id INT UNSIGNED NULL,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_progress', 'resolved', 'ignored') DEFAULT 'new',
    notes TEXT,
    INDEX (type),
    INDEX (status),
    INDEX (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- 创建广告定向规则表（依赖 ads 表）
CREATE TABLE IF NOT EXISTS ad_targeting (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    geo_countries TEXT,          -- 国家/地区列表 (JSON格式)
    geo_regions TEXT,            -- 省/州列表 (JSON格式)
    geo_cities TEXT,             -- 城市列表 (JSON格式)
    devices TEXT,                -- 设备类型列表 (JSON格式: ["desktop", "mobile", "tablet"])
    browsers TEXT,               -- 浏览器列表 (JSON格式: ["chrome", "firefox", "safari"])
    os TEXT,                     -- 操作系统列表 (JSON格式: ["windows", "macos", "ios", "android"])
    time_schedule TEXT,          -- 投放时间表 (JSON格式: {"timezone": "UTC", "hours": [9,10,11]})
    language TEXT,               -- 语言设置 (JSON格式: ["en", "zh"])
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建广告投放统计表（依赖 ads 表）
CREATE TABLE IF NOT EXISTS ad_targeting_stats (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    country VARCHAR(2),          -- 国家代码 (ISO 3166-1 alpha-2)
    region VARCHAR(6),           -- 地区代码 (ISO 3166-2)
    city VARCHAR(100),          -- 城市名称
    device VARCHAR(20),         -- 设备类型
    browser VARCHAR(20),        -- 浏览器类型
    os VARCHAR(20),            -- 操作系统
    language VARCHAR(5),        -- 语言代码 (ISO 639-1)
    hour INT,                   -- 小时 (0-23)
    views INT DEFAULT 0,        -- 展示次数
    clicks INT DEFAULT 0,       -- 点击次数
    date DATE,                  -- 统计日期
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    INDEX idx_ad_targeting_stats_ad_id (ad_id),
    INDEX idx_ad_targeting_stats_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建IP地理位置缓存表
CREATE TABLE IF NOT EXISTS ip_geo_cache (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,    -- IPv4或IPv6地址
    country VARCHAR(2),                 -- 国家代码
    region VARCHAR(6),                  -- 地区代码
    city VARCHAR(100),                 -- 城市名称
    latitude DECIMAL(10,8),            -- 纬度
    longitude DECIMAL(11,8),           -- 经度
    timezone VARCHAR(40),              -- 时区
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ip_address (ip_address),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建广告投放规则表（依赖 ads 和 zones 表）
CREATE TABLE IF NOT EXISTS ad_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ad_id INT UNSIGNED NOT NULL,
    zone_id INT UNSIGNED NOT NULL,
    priority INT DEFAULT 0,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    daily_budget DECIMAL(10,2) DEFAULT NULL,
    total_budget DECIMAL(10,2) DEFAULT NULL,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id),
    FOREIGN KEY (zone_id) REFERENCES zones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 插入默认系统设置
INSERT INTO settings (`key`, `value`) VALUES
('site_name', 'VertoAD'),
('site_description', 'Advertisement Management System'),
('admin_email', 'admin@example.com'),
('currency', 'CNY'),
('min_withdrawal', '100'),
('max_withdrawal', '10000'),
('commission_rate', '0.1'),
('maintenance_mode', '0'),
('version', '1.0.0')
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP; 