-- Create ea_commands table manually
-- Run this SQL script if migration fails due to PHP version compatibility

CREATE TABLE IF NOT EXISTS `ea_commands` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `account_id` bigint(20) unsigned NOT NULL,
  `command` varchar(255) NOT NULL COMMENT 'close_all, pause, resume, schedule',
  `params` json DEFAULT NULL COMMENT 'For schedule: {days: [1,2,3], start_time: ''10:00'', end_time: ''15:00'', magic_buy: 123, magic_sell: 456, intercept_all: true}',
  `status` enum('pending','executing','completed','failed') NOT NULL DEFAULT 'pending',
  `result` text DEFAULT NULL COMMENT 'Execution result from EA',
  `error_message` text DEFAULT NULL,
  `executed_at` timestamp NULL DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `ea_commands_account_id_status_index` (`account_id`,`status`),
  KEY `ea_commands_account_id_created_at_index` (`account_id`,`created_at`),
  CONSTRAINT `ea_commands_account_id_foreign` FOREIGN KEY (`account_id`) REFERENCES `accounts` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
