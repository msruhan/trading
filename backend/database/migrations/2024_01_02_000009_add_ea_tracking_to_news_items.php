<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            // EA Trading tracking fields
            $table->enum('ea_status', ['safe', 'caution', 'danger', 'unknown'])->default('unknown')->after('previous');
            $table->text('user_notes')->nullable()->after('ea_status');
            $table->boolean('should_disable_ea')->default(false)->after('user_notes');
            $table->integer('disable_minutes_before')->default(30)->after('should_disable_ea');
            $table->integer('disable_minutes_after')->default(30)->after('disable_minutes_before');
            $table->json('affected_pairs')->nullable()->after('disable_minutes_after');
            $table->foreignId('marked_by')->nullable()->after('affected_pairs')->constrained('users')->onDelete('set null');
            $table->timestamp('marked_at')->nullable()->after('marked_by');
            
            // ForexFactory specific fields
            $table->string('ff_event_id')->nullable()->after('marked_at');
            
            // Add index
            $table->index('ea_status');
            $table->index('should_disable_ea');
            $table->index('ff_event_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('news_items', function (Blueprint $table) {
            $table->dropForeign(['marked_by']);
            $table->dropColumn([
                'ea_status',
                'user_notes', 
                'should_disable_ea',
                'disable_minutes_before',
                'disable_minutes_after',
                'affected_pairs',
                'marked_by',
                'marked_at',
                'ff_event_id',
            ]);
        });
    }
};

