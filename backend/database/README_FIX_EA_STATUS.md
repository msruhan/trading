# Fix EA Status Column to Allow NULL

## Problem
The `ea_status` column in `news_items` table currently:
- Has default value 'unknown'
- Does not allow NULL values
- This causes error when trying to set status to "Empty"

## Solution
Run the SQL script `FIX_ea_status_allow_null.sql` in phpMyAdmin.

## Steps

1. **Open phpMyAdmin**
2. **Select your database** (e.g., `fhiirevp_giglinkdb`)
3. **Go to SQL tab**
4. **Copy and paste the contents of `FIX_ea_status_allow_null.sql`**
5. **Click "Go"**

## What the script does:

1. Updates all existing 'unknown' values to NULL
2. Modifies the column to:
   - Allow NULL values
   - Remove 'unknown' from enum (only 'safe', 'caution', 'danger' allowed)
   - Remove default value

## After running:

- You can now set `ea_status` to NULL (empty)
- Existing 'unknown' values will be converted to NULL
- New news items will have NULL by default (not 'unknown')

## Verification

After running, you can verify with:
```sql
DESCRIBE news_items;
```

The `ea_status` column should show:
- Type: `enum('safe','caution','danger')`
- Null: `YES`
- Default: `NULL`

