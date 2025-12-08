<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Check if column exists and has 'unknown' in enum
        $columnInfo = DB::select("SHOW COLUMNS FROM news_items WHERE Field = 'ea_status'");
        
        if (!empty($columnInfo)) {
            // For MySQL, we need to modify the enum column to allow NULL
            // First, update existing 'unknown' values to NULL
            DB::table('news_items')
                ->where('ea_status', 'unknown')
                ->update(['ea_status' => null]);
            
            // Change the column to allow NULL and remove 'unknown' from enum
            DB::statement("ALTER TABLE news_items MODIFY COLUMN ea_status ENUM('safe', 'caution', 'danger') NULL");
            
            // Remove default value (if exists)
            try {
                DB::statement("ALTER TABLE news_items ALTER COLUMN ea_status DROP DEFAULT");
            } catch (\Exception $e) {
                // Default might not exist, ignore error
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert: add 'unknown' back and set default
        DB::statement("ALTER TABLE news_items MODIFY COLUMN ea_status ENUM('safe', 'caution', 'danger', 'unknown') NOT NULL DEFAULT 'unknown'");
        
        // Update NULL values back to 'unknown'
        DB::table('news_items')
            ->whereNull('ea_status')
            ->update(['ea_status' => 'unknown']);
    }
};

