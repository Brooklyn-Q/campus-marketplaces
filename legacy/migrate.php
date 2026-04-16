<?php
require_once 'db.php';

echo '<!DOCTYPE html><html><head><title>System Migration</title><style>body{background:#0a0f1e;color:#fff;font-family:sans-serif;padding:3rem;} .log{background:rgba(255,255,255,0.05);padding:2rem;border-radius:16px;font-family:monospace;border:1px solid rgba(255,255,255,0.1);}</style></head><body><h1>Platform Migration Assistant</h1><div class="log">';

if (php_sapi_name() !== 'cli' && !isAdmin() && !empty($pdo->query("SELECT id FROM users LIMIT 1")->fetch())) {
    die("Access denied. Please login as admin to run migrations.");
}

function run_query($pdo, $sql, $description) {
    try {
        $pdo->exec($sql);
        echo "<p style='color:#4ade80;'>✅ Success: $description</p>";
    } catch (Exception $e) {
        echo "<p style='color:#f87171;'>⚠️ Error: $description (" . $e->getMessage() . ")</p>";
    }
}

echo "<h3>Running System Migrations...</h3>";

// 1. Core Users Table Enhancements
run_query($pdo, "ALTER TABLE users ADD UNIQUE IF NOT EXISTS (username)", "Unique Username");
run_query($pdo, "ALTER TABLE users ADD UNIQUE IF NOT EXISTS (email)", "Unique Email");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS terms_accepted TINYINT(1) DEFAULT 0", "Terms Accepted Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS accepted_at DATETIME DEFAULT NULL", "Accepted At Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS suspended TINYINT(1) DEFAULT 0", "Suspended Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_upload_at DATETIME DEFAULT NULL", "Last Upload At Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS faculty VARCHAR(120) DEFAULT NULL", "Faculty Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS hall_residence VARCHAR(255) DEFAULT NULL", "Hall Residence Column");
run_query($pdo, "ALTER TABLE users MODIFY COLUMN seller_tier ENUM('basic','pro','premium') DEFAULT 'basic'", "Seller Tier ENUM");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS tier_expires_at DATETIME DEFAULT NULL", "Tier Expiration Column");
run_query($pdo, "ALTER TABLE users ADD COLUMN IF NOT EXISTS vacation_mode TINYINT(1) DEFAULT 0", "Vacation Mode Column");

// 2. Products Enhancements
run_query($pdo, "ALTER TABLE products ADD COLUMN IF NOT EXISTS promo_tag VARCHAR(50) DEFAULT '' AFTER quantity", "Product Promo Tag");
run_query($pdo, "ALTER TABLE products ADD COLUMN IF NOT EXISTS delivery_method VARCHAR(50) DEFAULT 'Pickup'", "Product Delivery Method");
run_query($pdo, "ALTER TABLE products ADD COLUMN IF NOT EXISTS payment_agreement VARCHAR(50) DEFAULT 'Pay on delivery'", "Product Payment Agreement");

// 3. Transactions & Messages
run_query($pdo, "ALTER TABLE transactions MODIFY COLUMN type ENUM('deposit','purchase','sale','referral','withdrawal','boost','premium') NOT NULL", "Transactions Type ENUM");
run_query($pdo, "ALTER TABLE messages ADD COLUMN IF NOT EXISTS delivery_status ENUM('sent','delivered','seen') DEFAULT 'sent'", "Message Delivery Status");

// 4. Create Support Tables
run_query($pdo, "CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB", "Settings Table");

run_query($pdo, "INSERT IGNORE INTO settings (setting_key, setting_value) VALUES 
    ('premium_price', '20'), ('ad_boost_price', '5'),
    ('basic_product_limit', '2'), ('pro_product_limit', '5'), ('premium_product_limit', '15'),
    ('basic_image_limit', '1'), ('pro_image_limit', '1'), ('premium_image_limit', '3'),
    ('pro_fee', '10'), ('premium_fee', '20'),
    ('pro_batch_days', '14'), ('premium_batch_days', '7')", "Initial Settings Seed");

run_query($pdo, "CREATE TABLE IF NOT EXISTS account_tiers (
    tier_name VARCHAR(50) PRIMARY KEY,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration VARCHAR(50) NOT NULL DEFAULT 'forever',
    product_limit INT NOT NULL DEFAULT 2,
    images_per_product INT NOT NULL DEFAULT 1,
    badge VARCHAR(50) NOT NULL DEFAULT 'blue',
    ads_boost TINYINT(1) NOT NULL DEFAULT 0,
    priority VARCHAR(50) NOT NULL DEFAULT 'normal'
) ENGINE=InnoDB", "Account Tiers Table");

run_query($pdo, "INSERT IGNORE INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost, priority) VALUES 
    ('basic', 0.00, 'forever', 2, 1, 'blue', 0, 'normal'),
    ('pro', 10.00, '2_weeks', 5, 1, 'silver', 0, 'normal'),
    ('premium', 20.00, 'weekly', 15, 3, 'gold', 1, 'top')", "Account Tiers Seed");

run_query($pdo, "CREATE TABLE IF NOT EXISTS orders (
    id INT AUTO_INCREMENT PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status ENUM('ordered','seller_seen','delivered','completed','disputed') DEFAULT 'ordered',
    delivery_note TEXT,
    buyer_confirmed TINYINT(1) DEFAULT 0,
    seller_confirmed TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_buyer (buyer_id),
    INDEX idx_seller (seller_id)
) ENGINE=InnoDB", "Orders Table");

run_query($pdo, "CREATE TABLE IF NOT EXISTS disputes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL,
    complainant_id INT NOT NULL,
    target_id INT NOT NULL,
    reason TEXT NOT NULL,
    status ENUM('open', 'resolved', 'closed') DEFAULT 'open',
    admin_note TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", "Disputes Table");

run_query($pdo, "CREATE TABLE IF NOT EXISTS ad_placements (
    id INT AUTO_INCREMENT PRIMARY KEY, 
    title VARCHAR(255) NOT NULL, 
    image_url VARCHAR(500) DEFAULT '', 
    link_url VARCHAR(500) DEFAULT '#', 
    placement ENUM('homepage','category','product') DEFAULT 'homepage', 
    is_active TINYINT(1) DEFAULT 1, 
    impressions INT DEFAULT 0, 
    clicks INT DEFAULT 0, 
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB", "Ad Placements Table");

run_query($pdo, "CREATE TABLE IF NOT EXISTS notifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(50) NOT NULL,
    message TEXT NOT NULL,
    reference_id INT DEFAULT NULL,
    is_read TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user (user_id)
) ENGINE=InnoDB", "Notifications Table");

run_query($pdo, "CREATE TABLE IF NOT EXISTS vacation_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seller_id INT NOT NULL,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_seller (seller_id)
) ENGINE=InnoDB", "Vacation Requests Table");

run_query($pdo, "CREATE TABLE IF NOT EXISTS profile_edit_requests (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    field_name VARCHAR(64) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    status ENUM('pending','approved','rejected') DEFAULT 'pending',
    admin_id INT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_user_status (user_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB", "Profile Edit Requests Table");

echo "<h3>Migration Complete!</h3><p><a href='index.php'>Go to Homepage</a></p></div></body></html>";
