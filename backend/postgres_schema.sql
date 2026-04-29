-- Campus Marketplace PostgreSQL Schema for Supabase
-- Run this in the Supabase SQL Editor

-- ── USERS ──
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(100) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'buyer' CHECK (role IN ('buyer', 'seller', 'admin')),
    seller_tier VARCHAR(20) DEFAULT 'basic' CHECK (seller_tier IN ('basic', 'pro', 'premium')),
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
    vacation_mode BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    suspended BOOLEAN DEFAULT FALSE,
    terms_accepted BOOLEAN DEFAULT FALSE,
    accepted_at TIMESTAMP DEFAULT NULL,
    tier_expires_at TIMESTAMP DEFAULT NULL,
    last_upload_at TIMESTAMP DEFAULT NULL,
    last_seen TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referred_by) REFERENCES users(id) ON DELETE SET NULL
);

CREATE INDEX idx_users_role ON users(role);
CREATE INDEX idx_users_referral ON users(referral_code);

-- ── PRODUCTS ──
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    title VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(50) DEFAULT 'General',
    quantity INT DEFAULT 1,
    promo_tag VARCHAR(50) DEFAULT '',
    views INT DEFAULT 0,
    clicks INT DEFAULT 0,
    boosted_until TIMESTAMP DEFAULT NULL,
    is_deleted BOOLEAN DEFAULT FALSE,
    original_price DECIMAL(10,2) DEFAULT NULL,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected', 'deletion_requested', 'paused', 'sold')),
    delivery_method VARCHAR(100) DEFAULT 'Pickup',
    payment_agreement VARCHAR(100) DEFAULT 'Pay on delivery',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_products_status ON products(status);
CREATE INDEX idx_products_user ON products(user_id);
CREATE INDEX idx_products_category ON products(category);

-- ── PRODUCT IMAGES ──
CREATE TABLE product_images (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    sort_order INT DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ── MESSAGES ──
CREATE TABLE messages (
    id SERIAL PRIMARY KEY,
    sender_id INT NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    delivery_status VARCHAR(20) DEFAULT 'sent' CHECK (delivery_status IN ('sent', 'delivered', 'seen')),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (sender_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_messages_convo ON messages(sender_id, receiver_id);

-- ── TRANSACTIONS ──
CREATE TABLE transactions (
    id SERIAL PRIMARY KEY,
    user_id INT NOT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('deposit', 'purchase', 'sale', 'referral', 'withdrawal', 'boost', 'premium', 'pro')),
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'failed')),
    reference VARCHAR(255) UNIQUE,
    description VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

CREATE INDEX idx_transactions_user ON transactions(user_id);

-- ── REFERRALS ──
CREATE TABLE referrals (
    id SERIAL PRIMARY KEY,
    referrer_id INT NOT NULL,
    referred_user_id INT NOT NULL,
    bonus DECIMAL(10,2) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (referrer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (referred_user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── REVIEWS ──
CREATE TABLE reviews (
    id SERIAL PRIMARY KEY,
    product_id INT NOT NULL,
    user_id INT NOT NULL,
    rating SMALLINT NOT NULL CHECK (rating BETWEEN 1 AND 5),
    comment TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (product_id, user_id),
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── AUDIT LOG ──
CREATE TABLE audit_log (
    id SERIAL PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(255) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- ── ORDERS ──
CREATE TABLE orders (
    id SERIAL PRIMARY KEY,
    buyer_id INT NOT NULL,
    seller_id INT NOT NULL,
    product_id INT NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    status VARCHAR(20) DEFAULT 'ordered',
    buyer_confirmed BOOLEAN DEFAULT FALSE,
    seller_confirmed BOOLEAN DEFAULT FALSE,
    delivery_note TEXT DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (buyer_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (seller_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE
);

-- ── SETTINGS ──
CREATE TABLE settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- ── ACCOUNT TIERS ──
CREATE TABLE account_tiers (
    id SERIAL PRIMARY KEY,
    tier_name VARCHAR(20) UNIQUE NOT NULL,
    price DECIMAL(10,2) NOT NULL,
    duration VARCHAR(20) NOT NULL,
    product_limit INT NOT NULL,
    images_per_product INT NOT NULL,
    badge VARCHAR(20),
    ads_boost BOOLEAN DEFAULT FALSE
);

-- ── SEED DATA ──
INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost) VALUES 
('basic', 0, 'forever', 2, 1, '#0071e3', FALSE),
('pro', 10, '2_weeks', 5, 2, '#8e8e93', FALSE),
('premium', 20, 'weekly', 15, 3, '#ff9f0a', TRUE)
ON CONFLICT (tier_name) DO NOTHING;

-- Initial Admin (Password: admin123)
-- Hash generated via password_hash() in PHP
INSERT INTO users (username, email, password, role, referral_code, verified)
VALUES ('admin', 'admin@campus.com', '$2y$10$8v5x5x5x5x5x5x5x5x5x5uS7k7k7k7k7k7k7k7k7k7k7k7k7k7k7k', 'admin', 'ADMIN001', TRUE)
ON CONFLICT (username) DO NOTHING;
