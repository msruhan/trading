<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->string('broker_name');
            $table->string('server');
            $table->string('login'); // login number
            $table->string('login_masked')->nullable();
            $table->enum('account_type', ['demo', 'live'])->default('demo');
            $table->string('platform', 10)->default('mt4'); // mt4, mt5
            $table->text('credentials_encrypted')->nullable();
            $table->string('api_token', 64)->unique()->nullable();
            $table->enum('status', ['active', 'inactive', 'syncing', 'error'])->default('active');
            $table->integer('sync_interval')->default(5); // minutes
            $table->boolean('is_auto_sync')->default(true);
            $table->timestamp('last_sync_at')->nullable();
            $table->decimal('balance', 15, 2)->default(0);
            $table->decimal('equity', 15, 2)->default(0);
            $table->decimal('margin', 15, 2)->default(0);
            $table->decimal('free_margin', 15, 2)->default(0);
            $table->decimal('margin_level', 10, 2)->default(0);
            $table->decimal('initial_balance', 15, 2)->default(0);
            $table->string('currency', 10)->default('USD');
            $table->string('leverage', 20)->default('1:100');
            $table->json('settings')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'status']);
            $table->index('api_token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};

