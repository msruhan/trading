-- Fix ea_status column to allow NULL
-- Run this script in phpMyAdmin or MySQL client
-- This will fix the error: "Column 'ea_status' cannot be null"

-- Step 1: Update existing 'unknown' values to NULL
UPDATE news_items SET ea_status = NULL WHERE ea_status = 'unknown';

-- Step 2: Modify column to allow NULL and remove 'unknown' from enum
-- Note: This will work even if column already allows NULL
ALTER TABLE news_items MODIFY COLUMN ea_status ENUM('safe', 'caution', 'danger') NULL;

-- Step 3: Remove default value (if exists)
-- If error occurs, it means default doesn't exist - that's OK
ALTER TABLE news_items ALTER COLUMN ea_status DROP DEFAULT;

-- Verify: Check if column allows NULL
-- Run this to verify:
-- DESCRIBE news_items;
-- 
-- Expected result for ea_status column:
-- - Type: enum('safe','caution','danger')
-- - Null: YES
-- - Default: NULL

