<?php
$host = 'localhost';
$username = 'root';
$password = ''; 

try {
    $pdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("DROP DATABASE IF EXISTS campus_marketplace");
    $pdo->exec("CREATE DATABASE campus_marketplace CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    $pdo->exec("USE campus_marketplace");

    // ── USERS ──
    $pdo->exec("CREATE TABLE users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        email VARCHAR(100) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        role ENUM('buyer','seller','admin') DEFAULT 'buyer',
        seller_tier ENUM('basic','pro','premium') DEFAULT 'basic',
        department VARCHAR(100) DEFAULT NULL,
        level VARCHAR(10) DEFAULT NULL,
        hall VARCHAR(100) DEFAULT NULL,
        hall_residence VARCHAR(255) DEFAULT NULL,
        faculty VARCHAR(120) DEFAULT NULL,
        phone VARCHAR(20) DEFAULT NULL,
        bio TEXT DEFAULT NULL,
        profile_pic VARCHAR(255) DEFAULT NULL,
        shop_banner VARCHAR(255) DEFAULT NULL,
        whatsapp VARCHAR(100) DEFAULT NULL,
        instagram VARCHAR(100) DEFAULT NULL,
        linkedin VARCHAR(100) DEFAULT NULL,
        balance DECIMAL(10,2) DEFAULT 0.00,
        referral_code VARCHAR(50) UNIQUE,
        referred_by INT DEFAULT NULL,
        vacation_mode TINYINT(1) DEFAULT 0,
        verified TINYINT(1) DEFAULT 0,
        suspended TINYINT(1) DEFAULT 0,
        terms_accepted TINYINT(1) DEFAULT 0,
        accepted_at DATETIME DEFAULT NULL,
        last_upload_at DATETIME DEFAULT NULL,
        last_seen DATETIME DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_role (role),
        INDEX idx_referral (referral_code),
        FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB");

    // ── PRODUCTS ──
    $pdo->exec("CREATE TABLE products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        title VARCHAR(150) NOT NULL,
        description TEXT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        category VARCHAR(50) DEFAULT 'General',
        quantity INT DEFAULT 1,
        promo_tag VARCHAR(50) DEFAULT '',
        views INT DEFAULT 0,
        clicks INT DEFAULT 0,
        boosted_until DATETIME DEFAULT NULL,
        status ENUM('pending','approved','rejected','deletion_requested','paused') DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_status (status),
        INDEX idx_user (user_id),
        INDEX idx_category (category),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── PRODUCT IMAGES ──
    $pdo->exec("CREATE TABLE product_images (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        image_path VARCHAR(255) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── MESSAGES ──
    $pdo->exec("CREATE TABLE messages (
        id INT AUTO_INCREMENT PRIMARY KEY,
        sender_id INT NOT NULL,
        receiver_id INT NOT NULL,
        message TEXT NOT NULL,
        is_read TINYINT(1) DEFAULT 0,
        delivery_status ENUM('sent','delivered','seen') DEFAULT 'sent',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_convo (sender_id, receiver_id),
        FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── TRANSACTIONS ──
    $pdo->exec("CREATE TABLE transactions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        type ENUM('deposit','purchase','sale','referral','withdrawal','boost') NOT NULL,
        amount DECIMAL(10,2) NOT NULL,
        status ENUM('pending','completed','failed') DEFAULT 'completed',
        reference VARCHAR(255) UNIQUE,
        description VARCHAR(255),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_user_tx (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── REFERRALS ──
    $pdo->exec("CREATE TABLE referrals (
        id INT AUTO_INCREMENT PRIMARY KEY,
        referrer_id INT NOT NULL,
        referred_user_id INT NOT NULL,
        bonus DECIMAL(10,2) NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── REVIEWS ──
    $pdo->exec("CREATE TABLE reviews (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        rating TINYINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
        comment TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY unique_review (product_id, user_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── AUDIT LOG ──
    $pdo->exec("CREATE TABLE audit_log (
        id INT AUTO_INCREMENT PRIMARY KEY,
        admin_id INT NOT NULL,
        action VARCHAR(255) NOT NULL,
        target_type VARCHAR(50),
        target_id INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── ORDERS ──
    $pdo->exec("CREATE TABLE orders (
        id INT AUTO_INCREMENT PRIMARY KEY,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        product_id INT NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        status VARCHAR(20) DEFAULT 'ordered',
        buyer_confirmed TINYINT(1) DEFAULT 0,
        seller_confirmed TINYINT(1) DEFAULT 0,
        delivery_note TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // ── DEFAULT ADMIN ──
    $admin_pwd = password_hash('admin123', PASSWORD_DEFAULT);
    $pdo->exec("INSERT INTO users (username, email, password, role, referral_code, verified)
                VALUES ('admin', 'admin@campus.com', '$admin_pwd', 'admin', 'ADMIN001', 1)");

    echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Setup</title>
    <link href='https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap' rel='stylesheet'>
    <style>body{font-family:'Inter';background:#0f172a;color:#f8fafc;display:flex;justify-content:center;align-items:center;min-height:100vh;}
    .card{background:rgba(30,41,59,0.8);border:1px solid rgba(255,255,255,0.1);padding:3rem;border-radius:16px;text-align:center;max-width:500px;}
    a{color:#6366f1;text-decoration:none;padding:0.75rem 2rem;background:#6366f1;color:#fff;border-radius:8px;display:inline-block;margin-top:1.5rem;}</style></head>
    <body><div class='card'><h1>✅ Setup Complete</h1><p style='margin:1rem 0;color:#94a3b8;'>Database created with all tables. Default admin ready.</p>
    <p><strong>Admin Login:</strong> admin@campus.com / admin123</p><a href='index.php'>Launch Marketplace →</a></div></body></html>";

} catch(PDOException $e) {
    die("Setup failed: " . $e->getMessage());
}
?>
