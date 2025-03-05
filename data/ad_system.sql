-- 创建用户表
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin', 'advertiser', 'publisher') NOT NULL,
    balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 创建广告表
CREATE TABLE IF NOT EXISTS ads (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(100) NOT NULL,
    content TEXT NOT NULL,
    status ENUM('draft', 'pending', 'approved', 'rejected') NOT NULL DEFAULT 'draft',
    budget DECIMAL(10,2) NOT NULL,
    remaining_budget DECIMAL(10,2) NOT NULL,
    cost_per_view DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- 创建广告浏览记录表
CREATE TABLE IF NOT EXISTS ad_views (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    publisher_id INT NOT NULL,
    viewer_ip VARCHAR(45) NOT NULL,
    cost DECIMAL(10,2) NOT NULL,
    viewed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE CASCADE,
    KEY idx_view_check (ad_id, publisher_id, viewer_ip(20), viewed_at),
    UNIQUE KEY unique_view_24h (ad_id, publisher_id, viewer_ip(20))
);

-- 创建激活码表
CREATE TABLE IF NOT EXISTS `activation_keys` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(22) NOT NULL COMMENT '激活码',
    `amount` DECIMAL(10,2) NOT NULL COMMENT '充值金额',
    `prefix` VARCHAR(2) DEFAULT '' COMMENT '前缀标识',
    `used` TINYINT(1) NOT NULL DEFAULT 0 COMMENT '是否已使用',
    `used_at` DATETIME NULL COMMENT '使用时间',
    `used_by` INT UNSIGNED NULL COMMENT '使用者ID',
    `created_at` DATETIME NOT NULL COMMENT '创建时间',
    `updated_at` DATETIME NULL COMMENT '更新时间',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uk_code` (`code`),
    KEY `idx_used` (`used`),
    KEY `idx_created_at` (`created_at`),
    KEY `idx_used_at` (`used_at`),
    KEY `idx_used_by` (`used_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='激活码表';

-- 创建 ad_placements 表
CREATE TABLE ad_placements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    name VARCHAR(100) NOT NULL,
    code TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id)
);

-- 创建 impressions 表
CREATE TABLE impressions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad_id INT NOT NULL,
    placement_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent VARCHAR(200) NOT NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    KEY idx_impression (ad_id, placement_id, ip_address(20)),
    FOREIGN KEY (ad_id) REFERENCES ads(id),
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id)
);

-- 创建 clicks 表
CREATE TABLE clicks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    impression_id INT NOT NULL,
    ad_id INT NOT NULL,
    publisher_id INT NOT NULL,
    placement_id INT NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT,
    referrer TEXT,
    clicked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    payout DECIMAL(10, 4) NOT NULL DEFAULT 0,
    FOREIGN KEY (ad_id) REFERENCES ads(id) ON DELETE CASCADE,
    FOREIGN KEY (publisher_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (placement_id) REFERENCES ad_placements(id) ON DELETE CASCADE,
    FOREIGN KEY (impression_id) REFERENCES impressions(id) ON DELETE CASCADE
);

-- 创建错误日志表
CREATE TABLE IF NOT EXISTS errors (
    id INT PRIMARY KEY AUTO_INCREMENT,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    file VARCHAR(255) NOT NULL,
    line INT NOT NULL,
    trace TEXT,
    request_data TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    status ENUM('new', 'in_progress', 'resolved', 'ignored') DEFAULT 'new',
    notes TEXT,
    INDEX (type),
    INDEX (status),
    INDEX (created_at)
);

-- 创建系统设置表
CREATE TABLE IF NOT EXISTS settings (
    `key` VARCHAR(50) PRIMARY KEY,
    `value` TEXT NOT NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
); 