# Fix: Sync Now Button Error

## Problem
Error ketika klik tombol "Sync Now":
```
SQLSTATE[42S22]: Column not found: 1054 Unknown column 'sync_requested_at' in 'field list'
```

## Solution

### Option 1: Auto-Migration (Recommended)
Controller sudah diupdate untuk auto-create kolom. Coba klik "Sync Now" lagi - kolom akan dibuat otomatis.

### Option 2: Manual SQL (If auto-migration fails)

1. **Buka phpMyAdmin:**
   - Buka browser: `http://localhost/phpmyadmin`
   - Login dengan username dan password MySQL

2. **Pilih Database:**
   - Pilih database Anda (biasanya `trading` atau nama database Anda)

3. **Jalankan SQL:**
   - Klik tab "SQL"
   - Copy dan paste SQL berikut:
   ```sql
   ALTER TABLE `accounts` 
   ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL 
   AFTER `last_sync_at`;
   ```
   - Klik "Go" untuk execute

4. **Verify:**
   - Klik tab "Structure" pada tabel `accounts`
   - Pastikan kolom `sync_requested_at` sudah ada

### Option 3: Via Command Line (MySQL)

```bash
mysql -u root -p
USE trading;  # Ganti dengan nama database Anda
ALTER TABLE `accounts` 
ADD COLUMN `sync_requested_at` TIMESTAMP NULL DEFAULT NULL 
AFTER `last_sync_at`;
EXIT;
```

## After Migration

Setelah kolom ditambahkan:
1. Refresh halaman website
2. Klik "Sync Now" lagi
3. EA akan sync dalam beberapa detik

## File SQL

File SQL lengkap ada di: `backend/add_sync_requested_at_column.sql`

