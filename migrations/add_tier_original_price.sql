-- Migration: Add original_price to account_tiers
ALTER TABLE account_tiers ADD COLUMN original_price DECIMAL(10,2) DEFAULT NULL;
