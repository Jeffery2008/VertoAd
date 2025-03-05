-- IP地理位置缓存表（适配 pconline 接口）
CREATE TABLE IF NOT EXISTS ip_geo_cache (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL,    -- IPv4或IPv6地址
    country VARCHAR(2) DEFAULT 'CN',    -- 国家代码（目前只支持中国）
    region VARCHAR(6),                  -- 省份代码（如：440000）
    city VARCHAR(6),                    -- 城市代码（如：440300）
    province VARCHAR(50),               -- 省份名称（如：广东省）
    city_name VARCHAR(50),              -- 城市名称（如：深圳市）
    address VARCHAR(255),               -- 完整地址
    timezone VARCHAR(40) DEFAULT 'Asia/Shanghai', -- 时区
    last_updated TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY idx_ip_address (ip_address),
    INDEX idx_last_updated (last_updated)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4; 