-- CAMPUS MARKETPLACE SUPABASE SETUP SCRIPT (PostgreSQL)
-- Copy and Paste this into the Supabase SQL Editor

-- 1. Users Table
CREATE TABLE IF NOT EXISTS users (
    id BIGSERIAL PRIMARY KEY,
    full_name VARCHAR(255),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    role VARCHAR(50) DEFAULT 'buyer',
    seller_tier VARCHAR(50) DEFAULT 'basic',
    balance DECIMAL(10,2) DEFAULT 0.00,
    profile_pic VARCHAR(500),
    shop_banner VARCHAR(500),
    bio TEXT,
    phone VARCHAR(20),
    whatsapp VARCHAR(20),
    instagram VARCHAR(100),
    linkedin VARCHAR(255),
    faculty VARCHAR(120),
    department VARCHAR(120),
    level VARCHAR(50),
    hall_residence VARCHAR(255),
    terms_accepted BOOLEAN DEFAULT FALSE,
    accepted_at TIMESTAMP,
    suspended BOOLEAN DEFAULT FALSE,
    vacation_mode BOOLEAN DEFAULT FALSE,
    verified BOOLEAN DEFAULT FALSE,
    last_upload_at TIMESTAMP,
    tier_expires_at TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 2. Products Table
CREATE TABLE IF NOT EXISTS products (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    price DECIMAL(10,2) NOT NULL,
    category VARCHAR(100),
    quantity INT DEFAULT 1,
    promo_tag VARCHAR(50) DEFAULT '',
    delivery_method VARCHAR(50) DEFAULT 'Pickup',
    payment_agreement VARCHAR(50) DEFAULT 'Pay on delivery',
    status VARCHAR(50) DEFAULT 'pending',
    views INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 3. Product Images
CREATE TABLE IF NOT EXISTS product_images (
    id BIGSERIAL PRIMARY KEY,
    product_id BIGINT REFERENCES products(id) ON DELETE CASCADE,
    image_path VARCHAR(500) NOT NULL,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 4. Messages Table
CREATE TABLE IF NOT EXISTS messages (
    id BIGSERIAL PRIMARY KEY,
    sender_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    receiver_id BIGINT REFERENCES users(id) ON DELETE CASCADE,
    message TEXT,
    attachment_url VARCHAR(500),
    message_type VARCHAR(20) DEFAULT 'text',
    delivery_status VARCHAR(20) DEFAULT 'sent',
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 5. Orders Table
CREATE TABLE IF NOT EXISTS orders (
    id BIGSERIAL PRIMARY KEY,
    buyer_id BIGINT REFERENCES users(id),
    seller_id BIGINT REFERENCES users(id),
    product_id BIGINT REFERENCES products(id),
    price DECIMAL(10,2),
    quantity INT DEFAULT 1,
    delivery_note TEXT,
    status VARCHAR(50) DEFAULT 'ordered',
    buyer_confirmed BOOLEAN DEFAULT FALSE,
    seller_confirmed BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. Transactions Table
CREATE TABLE IF NOT EXISTS transactions (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT REFERENCES users(id),
    type VARCHAR(50) NOT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) DEFAULT 'pending',
    reference VARCHAR(255),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 7. Account Tiers Table
CREATE TABLE IF NOT EXISTS account_tiers (
    tier_name VARCHAR(50) PRIMARY KEY,
    price DECIMAL(10,2) NOT NULL DEFAULT 0,
    duration INT NOT NULL DEFAULT 1, -- Represented in months
    product_limit INT NOT NULL DEFAULT 2,
    images_per_product INT NOT NULL DEFAULT 1,
    badge VARCHAR(50) DEFAULT 'blue',
    ads_boost BOOLEAN DEFAULT FALSE,
    priority VARCHAR(50) DEFAULT 'normal',
    benefits JSONB DEFAULT '[]'
);

-- 8. Ad Placements
CREATE TABLE IF NOT EXISTS ad_placements (
    id BIGSERIAL PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    image_url VARCHAR(500),
    link_url VARCHAR(500),
    placement VARCHAR(50) DEFAULT 'homepage',
    is_active BOOLEAN DEFAULT TRUE,
    impressions INT DEFAULT 0,
    clicks INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 9. Settings Table
CREATE TABLE IF NOT EXISTS settings (
    setting_key VARCHAR(100) PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 10. Audit Logs
CREATE TABLE IF NOT EXISTS admin_audit_log (
    id BIGSERIAL PRIMARY KEY,
    admin_id BIGINT REFERENCES users(id),
    action VARCHAR(255) NOT NULL,
    item_type VARCHAR(50),
    item_id BIGINT,
    details TEXT,
    ip_address VARCHAR(50),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 11. Initial Data Seeding
INSERT INTO account_tiers (tier_name, price, duration, product_limit, images_per_product, badge, ads_boost, priority) VALUES 
('basic', 0.00, 999, 2, 1, 'blue', FALSE, 'normal'),
('pro', 10.00, 1, 5, 2, 'silver', FALSE, 'normal'),
('premium', 20.00, 1, 15, 3, 'gold', TRUE, 'top')
ON CONFLICT (tier_name) DO NOTHING;

INSERT INTO settings (setting_key, setting_value) VALUES 
('maintenance_mode', 'off'),
('premium_price', '20')
ON CONFLICT (setting_key) DO NOTHING;
