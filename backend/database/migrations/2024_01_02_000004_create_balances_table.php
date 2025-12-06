<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->decimal('balance', 15, 2);
            $table->decimal('equity', 15, 2);
            $table->decimal('margin', 15, 2)->default(0);
            $table->decimal('free_margin', 15, 2)->default(0);
            $table->decimal('margin_level', 10, 2)->default(0);
            $table->decimal('floating_pl', 15, 2)->default(0);
            $table->integer('open_trades_count')->default(0);
            $table->timestamp('timestamp');
            $table->timestamps();
            
            $table->index(['account_id', 'timestamp']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balances');
    }
};

