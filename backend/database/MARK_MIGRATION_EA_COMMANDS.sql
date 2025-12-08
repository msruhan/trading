-- Mark migration as completed if table already exists
-- Run this in phpMyAdmin if you get "Table 'ea_commands' already exists" error

-- Check if migrations table exists
-- If not, create it first (Laravel will do this automatically)

-- Insert migration record to mark it as completed
INSERT INTO migrations (migration, batch)
SELECT '2025_12_07_120000_create_ea_commands_table', 
       COALESCE((SELECT MAX(batch) FROM migrations), 0) + 1
WHERE NOT EXISTS (
    SELECT 1 FROM migrations 
    WHERE migration = '2025_12_07_120000_create_ea_commands_table'
);

-- Verify
-- SELECT * FROM migrations WHERE migration LIKE '%ea_commands%';

