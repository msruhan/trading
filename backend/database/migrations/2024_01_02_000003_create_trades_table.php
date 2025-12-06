<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trades', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->onDelete('cascade');
            $table->bigInteger('ticket')->nullable();
            $table->string('pair', 20);
            $table->enum('type', ['buy', 'sell']);
            $table->decimal('open_price', 15, 5);
            $table->decimal('close_price', 15, 5)->nullable();
            $table->decimal('lots', 10, 4);
            $table->decimal('profit', 15, 2)->default(0);
            $table->decimal('pips', 10, 2)->default(0);
            $table->decimal('swap', 15, 2)->default(0);
            $table->decimal('commission', 15, 2)->default(0);
            $table->decimal('fee', 15, 2)->default(0);
            $table->decimal('stop_loss', 15, 5)->nullable();
            $table->decimal('take_profit', 15, 5)->nullable();
            $table->timestamp('open_time');
            $table->timestamp('close_time')->nullable();
            $table->integer('duration_minutes')->nullable();
            $table->enum('status', ['open', 'closed', 'pending'])->default('open');
            $table->text('comment')->nullable();
            $table->string('magic_number')->nullable();
            $table->boolean('is_manual')->default(false);
            $table->timestamps();
            
            $table->index(['account_id', 'status']);
            $table->index(['account_id', 'open_time']);
            $table->index(['account_id', 'close_time']);
            $table->index(['account_id', 'pair']);
            $table->unique(['account_id', 'ticket']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trades');
    }
};

