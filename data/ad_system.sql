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

-- 创建 activation_keys 表
CREATE TABLE activation_keys (
    id INT AUTO_INCREMENT PRIMARY KEY,
    `key` VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    used_by INT,
    used_at TIMESTAMP,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_key (`key`(100)),
    FOREIGN KEY (used_by) REFERENCES users(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

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