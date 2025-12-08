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
        Schema::create('ea_commands', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->string('command'); // 'close_all', 'pause', 'resume', 'schedule'
            $table->json('params')->nullable(); // For schedule: {days: [1,2,3], start_time: '10:00', end_time: '15:00'}
            $table->enum('status', ['pending', 'executing', 'completed', 'failed'])->default('pending');
            $table->text('result')->nullable(); // Execution result from EA
            $table->text('error_message')->nullable();
            $table->timestamp('executed_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ea_commands');
    }
};

