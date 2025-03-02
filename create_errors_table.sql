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