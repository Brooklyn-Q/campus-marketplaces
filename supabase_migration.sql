-- 🗄️ Supabase Migration Script for Campus Marketplace
-- Version: 1.1 - Profile Picture & WhatsApp Fixes
-- Run this in your Supabase SQL Editor: https://app.supabase.com/project/ontxylkqzojjqhzimrcg/sql

-- ============================================
-- 1. Add missing columns to users table
-- ============================================

-- Add whatsapp_joined column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS whatsapp_joined BOOLEAN DEFAULT FALSE;

-- Add terms_accepted column if it doesn't exist  
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS terms_accepted BOOLEAN DEFAULT FALSE;

-- Add profile_pic column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS profile_pic VARCHAR(255) DEFAULT NULL;

-- Add shop_banner column if it doesn't exist (for seller profiles)
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS shop_banner VARCHAR(255) DEFAULT NULL;

-- Add faculty column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS faculty VARCHAR(255) DEFAULT NULL;

-- Add department column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS department VARCHAR(255) DEFAULT NULL;

-- Add level column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS level VARCHAR(50) DEFAULT NULL;

-- Add hall_residence column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS hall_residence VARCHAR(255) DEFAULT NULL;

-- Add phone column if it doesn't exist
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS phone VARCHAR(50) DEFAULT NULL;

-- ============================================
-- 2. Create profile_edit_requests table if it doesn't exist
-- ============================================

CREATE TABLE IF NOT EXISTS profile_edit_requests (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    field_name VARCHAR(100) NOT NULL,
    old_value TEXT,
    new_value TEXT,
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('pending', 'approved', 'rejected')),
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    resolved_at TIMESTAMP WITH TIME ZONE
);

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_profile_edit_requests_user_id ON profile_edit_requests(user_id);
CREATE INDEX IF NOT EXISTS idx_profile_edit_requests_status ON profile_edit_requests(status);

-- ============================================
-- 3. Create storage bucket for profile pictures if it doesn't exist
-- ============================================

-- Insert storage policy for profile pictures (if bucket exists)
INSERT INTO storage.buckets (id, name, public, file_size_limit, allowed_mime_types)
VALUES (
    'profile-pictures', 
    'profile-pictures', 
    true, 
    5242880, -- 5MB limit
    ARRAY['image/jpeg', 'image/png', 'image/gif', 'image/webp']
) ON CONFLICT (id) DO NOTHING;

-- Create Row Level Security (RLS) policies for profile pictures
CREATE POLICY "Users can upload their own profile pictures" ON storage.objects
FOR INSERT WITH CHECK (
    bucket_id = 'profile-pictures' AND 
    auth.uid()::text = (storage.foldername(name))[1]
);

CREATE POLICY "Users can view their own profile pictures" ON storage.objects  
FOR SELECT USING (
    bucket_id = 'profile-pictures' AND 
    auth.uid()::text = (storage.foldername(name))[1]
);

CREATE POLICY "Users can update their own profile pictures" ON storage.objects
FOR UPDATE USING (
    bucket_id = 'profile-pictures' AND 
    auth.uid()::text = (storage.foldername(name))[1]
);

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

-- Show recent users with new columns
SELECT 
    id, 
    username, 
    email,
    whatsapp_joined, 
    terms_accepted, 
    profile_pic,
    faculty,
    department,
    created_at
FROM users 
ORDER BY created_at DESC 
LIMIT 5;

-- Show storage buckets
SELECT * FROM storage.buckets WHERE name = 'profile-pictures';

-- Success message
SELECT 'Supabase migration completed successfully!' as status;