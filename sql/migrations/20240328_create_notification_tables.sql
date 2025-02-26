-- 系统通知渠道配置表
CREATE TABLE notification_channels (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    channel_type ENUM('email', 'sms', 'in_app') NOT NULL,
    name VARCHAR(100) NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT FALSE,
    config JSON,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_channel_type (channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知模板表
CREATE TABLE notification_templates (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    code VARCHAR(50) NOT NULL COMMENT '模板唯一标识符',
    title VARCHAR(255) NOT NULL COMMENT '通知标题',
    content TEXT NOT NULL COMMENT '通知内容',
    variables JSON NOT NULL COMMENT '模板变量定义',
    supported_channels JSON NOT NULL COMMENT '支持的通知渠道',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_template_code (code)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 通知记录表
CREATE TABLE notifications (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    template_id BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NOT NULL,
    channel_type ENUM('email', 'sms', 'in_app') NOT NULL,
    title VARCHAR(255) NOT NULL,
    content TEXT NOT NULL,
    variables JSON COMMENT '实际使用的变量值',
    status ENUM('pending', 'sent', 'failed', 'read') NOT NULL DEFAULT 'pending',
    error_message TEXT,
    sent_at TIMESTAMP NULL,
    read_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (template_id) REFERENCES notification_templates(id),
    FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 用户通知偏好设置表
CREATE TABLE user_notification_preferences (
    id BIGINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    template_id BIGINT UNSIGNED NOT NULL,
    channel_type ENUM('email', 'sms', 'in_app') NOT NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id),
    FOREIGN KEY (template_id) REFERENCES notification_templates(id),
    UNIQUE KEY unique_user_template_channel (user_id, template_id, channel_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 插入默认的通知渠道配置
INSERT INTO notification_channels (channel_type, name, is_enabled, config) VALUES
('in_app', '站内信', TRUE, '{"queue": "notifications_in_app"}'),
('email', '邮件通知', FALSE, '{"smtp_host": "", "smtp_port": "", "smtp_user": "", "smtp_pass": "", "from_email": "", "from_name": "", "queue": "notifications_email"}'),
('sms', '短信通知', FALSE, '{"api_url": "", "api_key": "", "api_secret": "", "queue": "notifications_sms"}');

-- 创建索引
CREATE INDEX idx_notifications_user_status ON notifications(user_id, status);
CREATE INDEX idx_notifications_template ON notifications(template_id);
CREATE INDEX idx_user_preferences ON user_notification_preferences(user_id);
CREATE INDEX idx_template_status ON notification_templates(status); 