-- Manual SQL to add sync_requested_at column to accounts table
-- Run this SQL directly in your MySQL database if migration fails

ALTER TABLE `accounts` 
ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL 
AFTER `last_sync_at`;

