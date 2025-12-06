<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sync_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->enum('status', ['pending', 'processing', 'success', 'failed'])->default('pending');
            $table->string('source')->default('ea_bridge'); // ea_bridge, api, manual
            $table->text('message')->nullable();
            $table->json('payload')->nullable();
            $table->json('stats')->nullable(); // trades_added, trades_updated, etc.
            $table->integer('duration_ms')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_logs');
    }
};

