<?php
/**
 * Comprehensive Schema Patch for Supabase PostgreSQL
 * Aligns the live database with what the PHP admin dashboard expects.
 */
require 'includes/db.php';

echo "=== Campus Marketplace Schema Patch ===\n\n";

$patches = [];

// 1. ANNOUNCEMENTS: PHP uses 'message' + 'title' + 'type', Supabase has 'content' (NOT NULL)
$patches[] = [
    "ALTER TABLE announcements ALTER COLUMN content DROP NOT NULL",
    "Make 'content' nullable (dashboard uses 'message' instead)"
];
$patches[] = [
    "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS message TEXT",
    "Add 'message' column for admin dashboard"
];
$patches[] = [
    "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS title VARCHAR(255) DEFAULT NULL",
    "Add 'title' column for admin dashboard"
];
$patches[] = [
    "ALTER TABLE announcements ADD COLUMN IF NOT EXISTS type VARCHAR(50) DEFAULT 'info'",
    "Add 'type' column for admin dashboard"
];

// 2. AUDIT_LOG: Admin dashboard uses auditLog() which inserts into audit_log
$patches[] = [
    "CREATE TABLE IF NOT EXISTS audit_log (
        id SERIAL PRIMARY KEY,
        admin_id INT NOT NULL,
        action TEXT NOT NULL,
        target_type VARCHAR(50) DEFAULT '',
        target_id INT DEFAULT 0,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "Create audit_log table"
];

// 3. SETTINGS TABLE
$patches[] = [
    "CREATE TABLE IF NOT EXISTS settings (
        id SERIAL PRIMARY KEY,
        setting_key VARCHAR(100) UNIQUE NOT NULL,
        setting_value TEXT DEFAULT '',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "Create settings table"
];

// 4. PAYMENT VERIFICATION LOGS
$patches[] = [
    "CREATE TABLE IF NOT EXISTS payment_verification_logs (
        id SERIAL PRIMARY KEY,
        user_id INT DEFAULT NULL,
        reference VARCHAR(255) NOT NULL,
        status VARCHAR(50) NOT NULL,
        error_message TEXT DEFAULT NULL,
        ip_address VARCHAR(45) DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "Create payment_verification_logs table"
];

// 5. ACCOUNT_TIERS: ensure table exists
$patches[] = [
    "CREATE TABLE IF NOT EXISTS account_tiers (
        id SERIAL PRIMARY KEY,
        tier_name VARCHAR(50) UNIQUE NOT NULL,
        price DECIMAL(10,2) DEFAULT 0.00,
        duration VARCHAR(50) DEFAULT '0',
        product_limit INT DEFAULT 2,
        images_per_product INT DEFAULT 1,
        badge VARCHAR(50) DEFAULT 'blue',
        ads_boost BOOLEAN DEFAULT FALSE,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )",
    "Create account_tiers table"
];

// 6. Ensure users table has needed columns
$user_cols = [
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS seller_tier VARCHAR(50) DEFAULT 'basic'",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_upload_at TIMESTAMP DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS last_seen TIMESTAMP DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(500) DEFAULT NULL",
    "ALTER TABLE users ADD COLUMN IF NOT EXISTS referral_code VARCHAR(20) DEFAULT NULL",
];
foreach ($user_cols as $sql) {
    $patches[] = [$sql, "Ensure users column exists"];
}

// 7. ORDERS TABLE
$patches[] = [
    "CREATE TABLE IF NOT EXISTS orders (
        id SERIAL PRIMARY KEY,
        buyer_id INT NOT NULL,
        seller_id INT NOT NULL,
        product_id INT NOT NULL,
        quantity INT DEFAULT 1,
        total_price DECIMAL(10,2) NOT NULL,
        status VARCHAR(30) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )",
    "Create orders table"
];

// 8. PRODUCT_IMAGES TABLE
$patches[] = [
    "CREATE TABLE IF NOT EXISTS product_images (
        id SERIAL PRIMARY KEY,
        product_id INT NOT NULL,
        image_url VARCHAR(500) NOT NULL,
        sort_order INT DEFAULT 0,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
    )",
    "Create product_images table"
];

// 9. REVIEWS TABLE
$patches[] = [
    "CREATE TABLE IF NOT EXISTS reviews (
        id SERIAL PRIMARY KEY,
        product_id INT NOT NULL,
        user_id INT NOT NULL,
        rating INT NOT NULL CHECK (rating >= 1 AND rating <= 5),
        comment TEXT DEFAULT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
    )",
    "Create reviews table"
];

// Run all patches
$success = 0;
$skipped = 0;
$failed = 0;

foreach ($patches as [$sql, $desc]) {
    try {
        $pdo->exec($sql);
        echo "[OK] $desc\n";
        $success++;
    } catch (PDOException $e) {
        $msg = $e->getMessage();
        if (strpos($msg, 'already exists') !== false || strpos($msg, 'duplicate') !== false) {
            echo "[SKIP] $desc (already done)\n";
            $skipped++;
        } else {
            echo "[FAIL] $desc — $msg\n";
            $failed++;
        }
    }
}

// Seed default tiers if empty
try {
    $count = (int) $pdo->query("SELECT COUNT(*) FROM account_tiers")->fetchColumn();
    if ($count === 0) {
        $pdo->exec("INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
            ('basic', 0.00, '0', 2, 1, 'blue', false),
            ('pro', 10.00, '1', 5, 2, 'silver', false),
            ('premium', 20.00, '1', 15, 3, 'gold', true)
            ON CONFLICT (tier_name) DO NOTHING
        ");
        echo "[OK] Seeded default account tiers\n";
    } else {
        echo "[SKIP] Account tiers already seeded ($count rows)\n";
    }
} catch (Exception $e) {
    echo "[FAIL] Seeding tiers: " . $e->getMessage() . "\n";
}

echo "\n=== DONE: $success patched, $skipped skipped, $failed failed ===\n";
?>
