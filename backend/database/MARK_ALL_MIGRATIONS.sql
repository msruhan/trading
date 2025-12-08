-- Mark all pending migrations as completed
-- Run this in phpMyAdmin to skip migrations that already have their changes applied

-- Mark ea_commands migration as completed (if table exists)
INSERT INTO migrations (migration, batch)
SELECT '2025_12_07_120000_create_ea_commands_table', 
       COALESCE((SELECT MAX(batch) FROM migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM migrations 
    WHERE migration = '2025_12_07_120000_create_ea_commands_table'
)
AND EXISTS (SELECT 1 FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'ea_commands');

-- Mark sync_requested_at migration as completed (if column exists)
INSERT INTO migrations (migration, batch)
SELECT '2025_12_08_000001_add_sync_requested_at_to_accounts_table', 
       COALESCE((SELECT MAX(batch) FROM migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM migrations 
    WHERE migration = '2025_12_08_000001_add_sync_requested_at_to_accounts_table'
)
AND EXISTS (
    SELECT 1 FROM information_schema.columns 
    WHERE table_schema = DATABASE() 
    AND table_name = 'accounts' 
    AND column_name = 'sync_requested_at'
);

-- Verify migrations
-- SELECT * FROM migrations ORDER BY id DESC LIMIT 10;

