-- 🗄️ Database Migration Script for Alwaysdata Deployment
-- Version: 1.1 - Profile Picture & WhatsApp Fixes
-- Run this script on your alwaysdata PostgreSQL database

-- ============================================
-- 1. Check if columns exist before adding them
-- ============================================

-- Add whatsapp_joined column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='whatsapp_joined'
    ) THEN
        ALTER TABLE users ADD COLUMN whatsapp_joined BOOLEAN DEFAULT FALSE;
        RAISE NOTICE 'Added whatsapp_joined column';
    ELSE
        RAISE NOTICE 'whatsapp_joined column already exists';
    END IF;
END $$;

-- Add terms_accepted column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='terms_accepted'
    ) THEN
        ALTER TABLE users ADD COLUMN terms_accepted BOOLEAN DEFAULT FALSE;
        RAISE NOTICE 'Added terms_accepted column';
    ELSE
        RAISE NOTICE 'terms_accepted column already exists';
    END IF;
END $$;

-- Add profile_pic column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='profile_pic'
    ) THEN
        ALTER TABLE users ADD COLUMN profile_pic VARCHAR(255) DEFAULT NULL;
        RAISE NOTICE 'Added profile_pic column';
    ELSE
        RAISE NOTICE 'profile_pic column already exists';
    END IF;
END $$;

-- ============================================
-- 2. Add other potentially missing columns
-- ============================================

-- Add shop_banner column if it doesn't exist (for seller profiles)
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='shop_banner'
    ) THEN
        ALTER TABLE users ADD COLUMN shop_banner VARCHAR(255) DEFAULT NULL;
        RAISE NOTICE 'Added shop_banner column';
    ELSE
        RAISE NOTICE 'shop_banner column already exists';
    END IF;
END $$;

-- Add faculty column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='faculty'
    ) THEN
        ALTER TABLE users ADD COLUMN faculty VARCHAR(255) DEFAULT NULL;
        RAISE NOTICE 'Added faculty column';
    ELSE
        RAISE NOTICE 'faculty column already exists';
    END IF;
END $$;

-- Add department column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='department'
    ) THEN
        ALTER TABLE users ADD COLUMN department VARCHAR(255) DEFAULT NULL;
        RAISE NOTICE 'Added department column';
    ELSE
        RAISE NOTICE 'department column already exists';
    END IF;
END $$;

-- Add level column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='level'
    ) THEN
        ALTER TABLE users ADD COLUMN level VARCHAR(50) DEFAULT NULL;
        RAISE NOTICE 'Added level column';
    ELSE
        RAISE NOTICE 'level column already exists';
    END IF;
END $$;

-- Add hall_residence column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='hall_residence'
    ) THEN
        ALTER TABLE users ADD COLUMN hall_residence VARCHAR(255) DEFAULT NULL;
        RAISE NOTICE 'Added hall_residence column';
    ELSE
        RAISE NOTICE 'hall_residence column already exists';
    END IF;
END $$;

-- Add phone column if it doesn't exist
DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.columns 
        WHERE table_name='users' AND column_name='phone'
    ) THEN
        ALTER TABLE users ADD COLUMN phone VARCHAR(50) DEFAULT NULL;
        RAISE NOTICE 'Added phone column';
    ELSE
        RAISE NOTICE 'phone column already exists';
    END IF;
END $$;

-- ============================================
-- 3. Create profile_edit_requests table if it doesn't exist
-- ============================================

DO $$
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.tables 
        WHERE table_name='profile_edit_requests'
    ) THEN
        CREATE TABLE profile_edit_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id),
            field_name VARCHAR(100) NOT NULL,
            old_value TEXT,
            new_value TEXT,
            status VARCHAR(20) DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            resolved_at TIMESTAMP
        );
        CREATE INDEX idx_profile_edit_requests_user_id ON profile_edit_requests(user_id);
        CREATE INDEX idx_profile_edit_requests_status ON profile_edit_requests(status);
        RAISE NOTICE 'Created profile_edit_requests table';
    ELSE
        RAISE NOTICE 'profile_edit_requests table already exists';
    END IF;
END $$;

-- ============================================
-- 4. Verification Queries
-- ============================================

-- Show current users table structure
SELECT 
    column_name, 
    data_type, 
    is_nullable, 
    column_default 
FROM information_schema.columns 
WHERE table_name = 'users' 
    AND column_name IN ('whatsapp_joined', 'terms_accepted', 'profile_pic', 'shop_banner', 'faculty', 'department', 'level', 'hall_residence', 'phone')
ORDER BY column_name;

-- Show sample data
SELECT 
    id, 
    username, 
    whatsapp_joined, 
    terms_accepted, 
    profile_pic,
    faculty,
    department
FROM users 
ORDER BY id DESC 
LIMIT 5;

RAISE NOTICE 'Migration completed successfully!';