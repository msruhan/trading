<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chart_analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('symbol'); // e.g., FX:EURUSD, BINANCE:BTCUSDT
            $table->string('interval'); // e.g., D, 1H, 4H
            $table->string('title')->nullable();
            $table->text('notes')->nullable();
            $table->json('drawings')->nullable(); // Konva drawings data
            $table->json('tradingview_state')->nullable(); // TradingView widget state if available
            $table->timestamps();
            
            $table->index(['user_id', 'symbol', 'interval']);
            $table->index(['user_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chart_analyses');
    }
};

