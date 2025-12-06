-- ============================================
-- Trading Journal Database Schema
-- Compatible with MySQL 8.0+
-- Ready to import to phpMyAdmin
-- ============================================

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ============================================
-- Drop existing tables (if any)
-- ============================================
DROP TABLE IF EXISTS `news_items`;
DROP TABLE IF EXISTS `manual_entries`;
DROP TABLE IF EXISTS `sync_logs`;
DROP TABLE IF EXISTS `balances`;
DROP TABLE IF EXISTS `trades`;
DROP TABLE IF EXISTS `accounts`;
DROP TABLE IF EXISTS `personal_access_tokens`;
DROP TABLE IF EXISTS `password_reset_tokens`;
DROP TABLE IF EXISTS `failed_jobs`;
DROP TABLE IF EXISTS `jobs`;
DROP TABLE IF EXISTS `job_batches`;
DROP TABLE IF EXISTS `cache`;
DROP TABLE IF EXISTS `cache_locks`;
DROP TABLE IF EXISTS `sessions`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `migrations`;

-- ============================================
-- Migrations Table
-- ============================================
CREATE TABLE `migrations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255) NOT NULL,
    `batch` INT NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Users Table
-- ============================================
CREATE TABLE `users` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name` VARCHAR(255) NOT NULL,
    `email` VARCHAR(255) NOT NULL,
    `email_verified_at` TIMESTAMP NULL DEFAULT NULL,
    `password` VARCHAR(255) NOT NULL,
    `role` ENUM('admin', 'user') NOT NULL DEFAULT 'user',
    `two_factor_secret` TEXT NULL,
    `two_factor_recovery_codes` TEXT NULL,
    `two_factor_confirmed_at` TIMESTAMP NULL DEFAULT NULL,
    `avatar` VARCHAR(255) NULL,
    `timezone` VARCHAR(50) DEFAULT 'UTC',
    `settings` JSON NULL COMMENT 'User preferences (theme, notifications, etc)',
    `remember_token` VARCHAR(100) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sessions Table
-- ============================================
CREATE TABLE `sessions` (
    `id` VARCHAR(255) NOT NULL,
    `user_id` BIGINT UNSIGNED NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `payload` LONGTEXT NOT NULL,
    `last_activity` INT NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `sessions_user_id_index` (`user_id`),
    INDEX `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Cache Table
-- ============================================
CREATE TABLE `cache` (
    `key` VARCHAR(255) NOT NULL,
    `value` MEDIUMTEXT NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `cache_locks` (
    `key` VARCHAR(255) NOT NULL,
    `owner` VARCHAR(255) NOT NULL,
    `expiration` INT NOT NULL,
    PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Jobs Tables
-- ============================================
CREATE TABLE `jobs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `queue` VARCHAR(255) NOT NULL,
    `payload` LONGTEXT NOT NULL,
    `attempts` TINYINT UNSIGNED NOT NULL,
    `reserved_at` INT UNSIGNED NULL,
    `available_at` INT UNSIGNED NOT NULL,
    `created_at` INT UNSIGNED NOT NULL,
    PRIMARY KEY (`id`),
    INDEX `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `job_batches` (
    `id` VARCHAR(255) NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `total_jobs` INT NOT NULL,
    `pending_jobs` INT NOT NULL,
    `failed_jobs` INT NOT NULL,
    `failed_job_ids` LONGTEXT NOT NULL,
    `options` MEDIUMTEXT NULL,
    `cancelled_at` INT NULL,
    `created_at` INT NOT NULL,
    `finished_at` INT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

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

-- ============================================
-- Password Reset Tokens
-- ============================================
CREATE TABLE `password_reset_tokens` (
    `email` VARCHAR(255) NOT NULL,
    `token` VARCHAR(255) NOT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Personal Access Tokens (Sanctum)
-- ============================================
CREATE TABLE `personal_access_tokens` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `tokenable_type` VARCHAR(255) NOT NULL,
    `tokenable_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(255) NOT NULL,
    `token` VARCHAR(64) NOT NULL,
    `abilities` TEXT NULL,
    `last_used_at` TIMESTAMP NULL DEFAULT NULL,
    `expires_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `personal_access_tokens_token_unique` (`token`),
    INDEX `personal_access_tokens_tokenable_type_tokenable_id_index` (`tokenable_type`, `tokenable_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Trading Accounts Table
-- ============================================
CREATE TABLE `accounts` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `name` VARCHAR(100) NULL COMMENT 'Custom account name',
    `broker_name` VARCHAR(100) NOT NULL,
    `server` VARCHAR(255) NOT NULL,
    `login` VARCHAR(50) NOT NULL COMMENT 'Account login number',
    `login_masked` VARCHAR(50) NOT NULL COMMENT 'Masked login for display (e.g., ****1234)',
    `credentials_encrypted` TEXT NOT NULL COMMENT 'AES-256 encrypted investor credentials',
    `account_type` ENUM('demo', 'live') DEFAULT 'demo',
    `platform` ENUM('mt4', 'mt5', 'ctrader', 'other') DEFAULT 'mt4',
    `currency` VARCHAR(10) DEFAULT 'USD',
    `leverage` VARCHAR(20) NULL,
    `initial_balance` DECIMAL(15, 2) DEFAULT 0,
    `status` ENUM('active', 'inactive', 'error', 'syncing') DEFAULT 'active',
    `last_sync_at` TIMESTAMP NULL DEFAULT NULL,
    `sync_interval` INT DEFAULT 5 COMMENT 'Sync interval in minutes',
    `is_auto_sync` BOOLEAN DEFAULT TRUE,
    `api_token` VARCHAR(64) NULL COMMENT 'HMAC token for EA Bridge authentication',
    `error_message` TEXT NULL,
    `meta` JSON NULL COMMENT 'Additional broker-specific metadata',
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `accounts_user_id_index` (`user_id`),
    INDEX `accounts_status_index` (`status`),
    INDEX `accounts_last_sync_at_index` (`last_sync_at`),
    CONSTRAINT `accounts_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Trades Table
-- ============================================
CREATE TABLE `trades` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `ticket` BIGINT NOT NULL COMMENT 'MT4/MT5 order ticket number',
    `pair` VARCHAR(20) NOT NULL COMMENT 'Trading pair (e.g., EURUSD)',
    `type` ENUM('buy', 'sell', 'buy_limit', 'sell_limit', 'buy_stop', 'sell_stop', 'balance', 'credit') NOT NULL,
    `status` ENUM('open', 'closed', 'cancelled') DEFAULT 'closed',
    `open_time` DATETIME NOT NULL,
    `close_time` DATETIME NULL COMMENT 'NULL if trade still open',
    `open_price` DECIMAL(20, 8) NOT NULL,
    `close_price` DECIMAL(20, 8) NULL,
    `stop_loss` DECIMAL(20, 8) NULL,
    `take_profit` DECIMAL(20, 8) NULL,
    `lots` DECIMAL(10, 4) NOT NULL,
    `profit` DECIMAL(15, 2) NULL COMMENT 'Realized P/L',
    `swap` DECIMAL(15, 2) DEFAULT 0,
    `commission` DECIMAL(15, 2) DEFAULT 0,
    `pips` DECIMAL(10, 2) NULL,
    `duration_minutes` INT NULL COMMENT 'Trade duration in minutes',
    `comment` VARCHAR(255) NULL,
    `magic_number` INT NULL COMMENT 'EA magic number if applicable',
    `is_manual` BOOLEAN DEFAULT FALSE COMMENT 'True if manually entered',
    `tags` JSON NULL,
    `notes` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `trades_account_ticket_unique` (`account_id`, `ticket`),
    INDEX `trades_account_id_index` (`account_id`),
    INDEX `trades_pair_index` (`pair`),
    INDEX `trades_open_time_index` (`open_time`),
    INDEX `trades_close_time_index` (`close_time`),
    INDEX `trades_type_index` (`type`),
    INDEX `trades_status_index` (`status`),
    CONSTRAINT `trades_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Balances Table (Equity Snapshots)
-- ============================================
CREATE TABLE `balances` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `timestamp` DATETIME NOT NULL,
    `balance` DECIMAL(15, 2) NOT NULL,
    `equity` DECIMAL(15, 2) NOT NULL,
    `margin` DECIMAL(15, 2) DEFAULT 0,
    `free_margin` DECIMAL(15, 2) DEFAULT 0,
    `margin_level` DECIMAL(10, 2) NULL COMMENT 'Margin level percentage',
    `floating_pl` DECIMAL(15, 2) DEFAULT 0,
    `open_trades_count` INT DEFAULT 0,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `balances_account_id_index` (`account_id`),
    INDEX `balances_timestamp_index` (`timestamp`),
    INDEX `balances_account_timestamp_index` (`account_id`, `timestamp`),
    CONSTRAINT `balances_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Sync Logs Table
-- ============================================
CREATE TABLE `sync_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `account_id` BIGINT UNSIGNED NOT NULL,
    `status` ENUM('pending', 'processing', 'success', 'failed') NOT NULL DEFAULT 'pending',
    `type` ENUM('auto', 'manual', 'ea_bridge') DEFAULT 'auto',
    `trades_synced` INT DEFAULT 0,
    `new_trades` INT DEFAULT 0,
    `updated_trades` INT DEFAULT 0,
    `message` TEXT NULL,
    `payload` JSON NULL COMMENT 'Raw sync payload for debugging',
    `started_at` TIMESTAMP NULL DEFAULT NULL,
    `completed_at` TIMESTAMP NULL DEFAULT NULL,
    `duration_ms` INT NULL,
    `ip_address` VARCHAR(45) NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `sync_logs_account_id_index` (`account_id`),
    INDEX `sync_logs_status_index` (`status`),
    INDEX `sync_logs_created_at_index` (`created_at`),
    CONSTRAINT `sync_logs_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Manual Entries (Journal)
-- ============================================
CREATE TABLE `manual_entries` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NOT NULL,
    `account_id` BIGINT UNSIGNED NULL COMMENT 'Optional: link to specific account',
    `trade_id` BIGINT UNSIGNED NULL COMMENT 'Optional: link to specific trade',
    `entry_date` DATE NOT NULL,
    `entry_type` ENUM('journal', 'note', 'analysis', 'lesson', 'strategy') DEFAULT 'journal',
    `title` VARCHAR(255) NULL,
    `content` TEXT NULL,
    `mood` ENUM('confident', 'neutral', 'anxious', 'frustrated') NULL,
    `tags` JSON NULL,
    `attachments` JSON NULL COMMENT 'Screenshots, charts, etc',
    `is_public` BOOLEAN DEFAULT FALSE,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `manual_entries_user_id_index` (`user_id`),
    INDEX `manual_entries_account_id_index` (`account_id`),
    INDEX `manual_entries_entry_date_index` (`entry_date`),
    CONSTRAINT `manual_entries_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE,
    CONSTRAINT `manual_entries_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE SET NULL,
    CONSTRAINT `manual_entries_trade_id_foreign` FOREIGN KEY (`trade_id`) REFERENCES `trades` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- News Items Table
-- ============================================
CREATE TABLE `news_items` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `date` DATE NOT NULL,
    `time` TIME NULL,
    `title` VARCHAR(500) NOT NULL,
    `summary` TEXT NULL,
    `content` TEXT NULL,
    `source` VARCHAR(255) NULL,
    `url` VARCHAR(500) NULL,
    `impact` ENUM('low', 'medium', 'high') DEFAULT 'medium',
    `currency` VARCHAR(10) NULL COMMENT 'Affected currency (e.g., USD, EUR)',
    `actual` VARCHAR(50) NULL,
    `forecast` VARCHAR(50) NULL,
    `previous` VARCHAR(50) NULL,
    `is_manual` BOOLEAN DEFAULT FALSE,
    `created_by` BIGINT UNSIGNED NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `news_items_date_index` (`date`),
    INDEX `news_items_currency_index` (`currency`),
    INDEX `news_items_impact_index` (`impact`),
    CONSTRAINT `news_items_created_by_foreign` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Activity Logs (Audit)
-- ============================================
CREATE TABLE `activity_logs` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL,
    `log_type` VARCHAR(50) NOT NULL COMMENT 'auth, sync, trade, settings, etc',
    `action` VARCHAR(100) NOT NULL,
    `description` TEXT NULL,
    `subject_type` VARCHAR(255) NULL,
    `subject_id` BIGINT UNSIGNED NULL,
    `properties` JSON NULL,
    `ip_address` VARCHAR(45) NULL,
    `user_agent` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    INDEX `activity_logs_user_id_index` (`user_id`),
    INDEX `activity_logs_log_type_index` (`log_type`),
    INDEX `activity_logs_created_at_index` (`created_at`),
    CONSTRAINT `activity_logs_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Settings Table (Key-Value Store)
-- ============================================
CREATE TABLE `settings` (
    `id` BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id` BIGINT UNSIGNED NULL COMMENT 'NULL for global settings',
    `group` VARCHAR(50) NOT NULL DEFAULT 'general',
    `key` VARCHAR(100) NOT NULL,
    `value` TEXT NULL,
    `created_at` TIMESTAMP NULL DEFAULT NULL,
    `updated_at` TIMESTAMP NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `settings_user_group_key_unique` (`user_id`, `group`, `key`),
    INDEX `settings_group_index` (`group`),
    CONSTRAINT `settings_user_id_foreign` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;

-- ============================================
-- Insert Default Admin User
-- Password: password (hashed)
-- ============================================
INSERT INTO `users` (`name`, `email`, `password`, `role`, `email_verified_at`, `timezone`, `created_at`, `updated_at`) VALUES
('Admin', 'admin@tradingjournal.local', '$2y$12$eUJP7O8c5sDwu6hMvBPOv.vQHZD.e1fS1w9yUhCBOa.hqZ/0c9Nwi', 'admin', NOW(), 'Asia/Jakarta', NOW(), NOW()),
('Demo User', 'demo@tradingjournal.local', '$2y$12$eUJP7O8c5sDwu6hMvBPOv.vQHZD.e1fS1w9yUhCBOa.hqZ/0c9Nwi', 'user', NOW(), 'Asia/Jakarta', NOW(), NOW());

