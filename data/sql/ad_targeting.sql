-- 广告定向规则表
CREATE TABLE IF NOT EXISTS ad_targeting (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
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

-- 广告投放统计表（用于优化定向）
CREATE TABLE IF NOT EXISTS ad_targeting_stats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
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

-- IP地理位置缓存表
CREATE TABLE IF NOT EXISTS ip_geo_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
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