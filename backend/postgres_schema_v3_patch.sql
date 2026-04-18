-- Campus Marketplace PostgreSQL Schema Patch 3
-- Adds missing columns to ad_placements for tracking ad performance

-- Add impressions column
ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS impressions INT DEFAULT 0;

-- Add clicks column
ALTER TABLE ad_placements ADD COLUMN IF NOT EXISTS clicks INT DEFAULT 0;
