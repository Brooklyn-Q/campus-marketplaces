-- Security Logging Tables for Deployment
-- Run this migration after fixing the code

-- 1. Security events log
CREATE TABLE IF NOT EXISTS security_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(50) NOT NULL, -- 'failed_login', 'rate_limit_exceeded', 'suspicious_activity', etc.
    description TEXT,
    user_id INT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Payment verification log
CREATE TABLE IF NOT EXISTS payment_verification_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    reference VARCHAR(100) NOT NULL UNIQUE,
    status ENUM('success', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_reference (reference),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 3. Enable query logging in MySQL (optional - for deeper auditing)
-- SET GLOBAL general_log = 'ON';
-- SET GLOBAL log_output = 'TABLE';
-- To view: SELECT * FROM mysql.general_log;
