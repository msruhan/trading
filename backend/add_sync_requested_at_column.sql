-- Add sync_requested_at column to accounts table
-- Run this SQL in your MySQL database (phpMyAdmin, MySQL Workbench, or command line)
-- 
-- INSTRUCTIONS:
-- 1. Open phpMyAdmin (http://localhost/phpmyadmin)
-- 2. Select your database (usually 'trading' or similar)
-- 3. Go to SQL tab
-- 4. Copy and paste the SQL below
-- 5. Click "Go" to execute

-- Option 1: Simple (if column doesn't exist)
ALTER TABLE `accounts` 
ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL 
AFTER `last_sync_at`;

-- Option 2: Safe (check if column exists first - MySQL 5.7+)
-- ALTER TABLE `accounts` 
-- ADD COLUMN IF NOT EXISTS `sync_requested_at` TIMESTAMP NULL DEFAULT NULL 
-- AFTER `last_sync_at`;

-- Verify the column was added
SELECT COLUMN_NAME, DATA_TYPE, IS_NULLABLE, COLUMN_DEFAULT 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'accounts' 
  AND COLUMN_NAME = 'sync_requested_at';

