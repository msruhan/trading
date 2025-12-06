-- =====================================================
-- Trading Journal & Portfolio Dashboard
-- Database Schema - MySQL (phpMyAdmin compatible)
-- Version: 1.0.0
-- Date: 2024-12-05
-- =====================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

-- =====================================================
-- Database: `trading_journal`
-- =====================================================
CREATE DATABASE IF NOT EXISTS `trading_journal` 
    DEFAULT CHARACTER SET utf8mb4 
    COLLATE utf8mb4_unicode_ci;
USE `trading_journal`;

-- =====================================================
-- Table: migrations
-- =====================================================
DROP TABLE IF EXISTS `migrations`;
CREATE TABLE `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: users
-- =====================================================
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `avatar` VARCHAR(255) NULL DEFAULT NULL,
    `timezone` VARCHAR(255) NOT NULL DEFAULT 'UTC',
    `settings` JSON NULL DEFAULT NULL,
    `two_factor_secret` TEXT NULL DEFAULT NULL,
    `two_factor_recovery_codes` TEXT NULL DEFAULT NULL,
    `two_factor_confirmed_at` TIMESTAMP NULL DEFAULT NULL,
    `remember_token` VARCHAR(100) NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: password_reset_tokens
-- =====================================================
DROP TABLE IF EXISTS `password_reset_tokens`;
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: sessions
-- =====================================================
DROP TABLE IF EXISTS `sessions`;
CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: cache
-- =====================================================
DROP TABLE IF EXISTS `cache`;
CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: cache_locks
-- =====================================================
DROP TABLE IF EXISTS `cache_locks`;
CREATE TABLE `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: jobs
-- =====================================================
DROP TABLE IF EXISTS `jobs`;
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL DEFAULT NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: job_batches
-- =====================================================
DROP TABLE IF EXISTS `job_batches`;
CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL DEFAULT NULL,
    `cancelled_at` INT NULL DEFAULT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: failed_jobs
-- =====================================================
DROP TABLE IF EXISTS `failed_jobs`;
CREATE TABLE `failed_jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `uuid` VARCHAR(255) NOT NULL,
    `connection` TEXT NOT NULL,
    `queue` TEXT NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `exception` LONGTEXT NOT NULL,
    `failed_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: personal_access_tokens (Laravel Sanctum)
-- =====================================================
DROP TABLE IF EXISTS `personal_access_tokens`;
CREATE TABLE `personal_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `abilities` TEXT NULL DEFAULT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    KEY `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: accounts
-- =====================================================
DROP TABLE IF EXISTS `accounts`;
CREATE TABLE `accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `broker_name` VARCHAR(255) NOT NULL,
    `server` VARCHAR(255) NOT NULL,
    `login` VARCHAR(255) NOT NULL,
    `login_masked` VARCHAR(255) NULL DEFAULT NULL,
    `account_type` ENUM('demo', 'live') NOT NULL DEFAULT 'demo',
    `platform` VARCHAR(10) NOT NULL DEFAULT 'mt4',
    `credentials_encrypted` TEXT NULL DEFAULT NULL,
    `api_token` VARCHAR(64) NULL DEFAULT NULL,
    `status` ENUM('active', 'inactive', 'syncing', 'error') NOT NULL DEFAULT 'active',
    `sync_interval` INT NOT NULL DEFAULT 5,
    `is_auto_sync` TINYINT(1) NOT NULL DEFAULT 1,
    `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
    `balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `equity` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `margin` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `free_margin` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `margin_level` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `initial_balance` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `currency` VARCHAR(10) NOT NULL DEFAULT 'USD',
    `leverage` VARCHAR(20) NOT NULL DEFAULT '1:100',
    `settings` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `accounts_api_token_unique` (`api_token`),
    KEY `accounts_user_id_status_index` (`user_id`, `status`),
    KEY `accounts_api_token_index` (`api_token`),
    CONSTRAINT `accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: trades
-- =====================================================
DROP TABLE IF EXISTS `trades`;
CREATE TABLE `trades` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `ticket` BIGINT NULL DEFAULT NULL,
    `pair` VARCHAR(20) NOT NULL,
    `type` ENUM('buy', 'sell') NOT NULL,
    `open_price` DECIMAL(15,5) NOT NULL,
    `close_price` DECIMAL(15,5) NULL DEFAULT NULL,
    `lots` DECIMAL(10,4) NOT NULL,
    `profit` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `pips` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `swap` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `commission` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `fee` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `stop_loss` DECIMAL(15,5) NULL DEFAULT NULL,
    `take_profit` DECIMAL(15,5) NULL DEFAULT NULL,
    `open_time` TIMESTAMP NOT NULL,
    `close_time` TIMESTAMP NULL DEFAULT NULL,
    `duration_minutes` INT NULL DEFAULT NULL,
    `status` ENUM('open', 'closed', 'pending') NOT NULL DEFAULT 'open',
    `comment` TEXT NULL DEFAULT NULL,
    `magic_number` VARCHAR(255) NULL DEFAULT NULL,
    `is_manual` TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `trades_account_id_ticket_unique` (`account_id`, `ticket`),
    KEY `trades_account_id_status_index` (`account_id`, `status`),
    KEY `trades_account_id_open_time_index` (`account_id`, `open_time`),
    KEY `trades_account_id_close_time_index` (`account_id`, `close_time`),
    KEY `trades_account_id_pair_index` (`account_id`, `pair`),
    CONSTRAINT `trades_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: balances
-- =====================================================
DROP TABLE IF EXISTS `balances`;
CREATE TABLE `balances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `balance` DECIMAL(15,2) NOT NULL,
    `equity` DECIMAL(15,2) NOT NULL,
    `margin` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `free_margin` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `margin_level` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    `floating_pl` DECIMAL(15,2) NOT NULL DEFAULT 0.00,
    `open_trades_count` INT NOT NULL DEFAULT 0,
    `timestamp` TIMESTAMP NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `balances_account_id_timestamp_index` (`account_id`, `timestamp`),
    CONSTRAINT `balances_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: sync_logs
-- =====================================================
DROP TABLE IF EXISTS `sync_logs`;
CREATE TABLE `sync_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'processing', 'success', 'failed') NOT NULL DEFAULT 'pending',
    `source` VARCHAR(255) NOT NULL DEFAULT 'ea_bridge',
    `message` TEXT NULL DEFAULT NULL,
    `payload` JSON NULL DEFAULT NULL,
    `stats` JSON NULL DEFAULT NULL,
    `duration_ms` INT NULL DEFAULT NULL,
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `sync_logs_account_id_status_index` (`account_id`, `status`),
    KEY `sync_logs_account_id_created_at_index` (`account_id`, `created_at`),
    CONSTRAINT `sync_logs_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: manual_entries (Journal)
-- =====================================================
DROP TABLE IF EXISTS `manual_entries`;
CREATE TABLE `manual_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `trade_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `entry_type` ENUM('journal', 'note', 'analysis', 'plan') NOT NULL DEFAULT 'journal',
    `title` VARCHAR(255) NULL DEFAULT NULL,
    `content` TEXT NOT NULL,
    `entry_date` DATE NOT NULL,
    `tags` JSON NULL DEFAULT NULL,
    `screenshots` JSON NULL DEFAULT NULL,
    `mood` ENUM('confident', 'neutral', 'anxious', 'frustrated') NULL DEFAULT NULL,
    `rating` INT NULL DEFAULT NULL,
    `lessons_learned` JSON NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `manual_entries_user_id_entry_date_index` (`user_id`, `entry_date`),
    KEY `manual_entries_account_id_entry_date_index` (`account_id`, `entry_date`),
    CONSTRAINT `manual_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `manual_entries_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `manual_entries_trade_id_foreign` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: news_items
-- =====================================================
DROP TABLE IF EXISTS `news_items`;
CREATE TABLE `news_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `created_by` BIGINT UNSIGNED NULL DEFAULT NULL,
    `date` DATE NOT NULL,
    `time` TIME NULL DEFAULT NULL,
    `title` VARCHAR(255) NOT NULL,
    `summary` TEXT NULL DEFAULT NULL,
    `source` VARCHAR(255) NULL DEFAULT NULL,
    `url` VARCHAR(255) NULL DEFAULT NULL,
    `currency` VARCHAR(10) NULL DEFAULT NULL,
    `impact` ENUM('low', 'medium', 'high') NOT NULL DEFAULT 'medium',
    `actual` VARCHAR(50) NULL DEFAULT NULL,
    `forecast` VARCHAR(50) NULL DEFAULT NULL,
    `previous` VARCHAR(50) NULL DEFAULT NULL,
    `is_manual` TINYINT(1) NOT NULL DEFAULT 1,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `news_items_date_impact_index` (`date`, `impact`),
    KEY `news_items_date_index` (`date`),
    CONSTRAINT `news_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Table: activity_logs
-- =====================================================
DROP TABLE IF EXISTS `activity_logs`;
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `log_type` VARCHAR(50) NOT NULL,
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL DEFAULT NULL,
    `subject_type` VARCHAR(255) NULL DEFAULT NULL,
    `subject_id` BIGINT UNSIGNED NULL DEFAULT NULL,
    `properties` JSON NULL DEFAULT NULL,
    `ip_address` VARCHAR(45) NULL DEFAULT NULL,
    `user_agent` TEXT NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL,
    PRIMARY KEY (`id`),
    KEY `activity_logs_user_id_log_type_index` (`user_id`, `log_type`),
    KEY `activity_logs_log_type_created_at_index` (`log_type`, `created_at`),
    KEY `activity_logs_created_at_index` (`created_at`),
    KEY `activity_logs_subject_type_subject_id_index` (`subject_type`, `subject_id`),
    CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================
-- Insert migration records
-- =====================================================
INSERT INTO `migrations` (`migration`, `batch`) VALUES
    ('0001_01_01_000000_create_users_table', 1),
    ('0001_01_01_000001_create_cache_table', 1),
    ('0001_01_01_000002_create_jobs_table', 1),
    ('2024_01_02_000001_create_personal_access_tokens_table', 1),
    ('2024_01_02_000002_create_accounts_table', 1),
    ('2024_01_02_000003_create_trades_table', 1),
    ('2024_01_02_000004_create_balances_table', 1),
    ('2024_01_02_000005_create_sync_logs_table', 1),
    ('2024_01_02_000006_create_manual_entries_table', 1),
    ('2024_01_02_000007_create_news_items_table', 1),
    ('2024_01_02_000008_create_activity_logs_table', 1);

COMMIT;

-- =====================================================
-- END OF SCHEMA
-- =====================================================
-- 
-- NOTES:
-- 1. Import this file into phpMyAdmin to create the database schema
-- 2. After importing, run Laravel seeders to populate dummy data:
--    cd backend && php artisan db:seed
-- 
-- Default Users (after seeding):
-- - Admin: admin@tradingjournal.local / password
-- - Demo:  demo@tradingjournal.local / password
-- =====================================================
