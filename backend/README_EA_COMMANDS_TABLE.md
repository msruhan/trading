# Create EA Commands Table

## Problem
Error: `Table 'trading_journal.ea_commands' doesn't exist`

## Solution
Jalankan SQL script berikut di MySQL untuk membuat tabel `ea_commands`:

### Option 1: Via phpMyAdmin atau MySQL Client

1. Buka phpMyAdmin atau MySQL client
2. Pilih database `trading_journal`
3. Jalankan SQL script dari file `create_ea_commands_table.sql`

### Option 2: Via Command Line

```bash
mysql -u root -p trading_journal < create_ea_commands_table.sql
```

### Option 3: Copy-Paste SQL Script

Jalankan SQL berikut di MySQL:

```sql
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
```

## Verification

Setelah menjalankan SQL script, verifikasi tabel sudah dibuat:

```sql
SHOW TABLES LIKE 'ea_commands';
DESCRIBE ea_commands;
```

## Note

Migration file sudah ada di `database/migrations/2025_12_07_120000_create_ea_commands_table.php`, 
tapi tidak bisa dijalankan karena masalah PHP version compatibility dengan Laravel Prompts.
SQL script manual ini adalah solusi alternatif.

