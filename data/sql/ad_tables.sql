-- 广告表
CREATE TABLE IF NOT EXISTS ads (
    id INT PRIMARY KEY AUTO_INCREMENT,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    image_url VARCHAR(255) NOT NULL,
    target_url VARCHAR(255) NOT NULL,
    advertiser_id INT NOT NULL,
    status ENUM('active', 'paused', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (advertiser_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 广告位表
CREATE TABLE IF NOT EXISTS ad_zones (
    id INT PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    width INT NOT NULL,
    height INT NOT NULL,
    publisher_id INT NOT NULL,
    status ENUM('active', 'paused', 'deleted') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (publisher_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 广告展示记录表
CREATE TABLE IF NOT EXISTS ad_views (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    view_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    FOREIGN KEY (ad_id) REFERENCES ads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 广告点击记录表
CREATE TABLE IF NOT EXISTS ad_clicks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    click_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NOT NULL,
    FOREIGN KEY (ad_id) REFERENCES ads(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 广告投放规则表
CREATE TABLE IF NOT EXISTS ad_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ad_id INT NOT NULL,
    zone_id INT NOT NULL,
    priority INT DEFAULT 0,
    start_date TIMESTAMP NULL,
    end_date TIMESTAMP NULL,
    daily_budget DECIMAL(10,2) DEFAULT NULL,
    total_budget DECIMAL(10,2) DEFAULT NULL,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id),
    FOREIGN KEY (zone_id) REFERENCES ad_zones(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 创建索引
CREATE INDEX idx_ad_views_ad_id ON ad_views(ad_id);
CREATE INDEX idx_ad_clicks_ad_id ON ad_clicks(ad_id);
CREATE INDEX idx_ad_rules_ad_id ON ad_rules(ad_id);
CREATE INDEX idx_ad_rules_zone_id ON ad_rules(zone_id); 