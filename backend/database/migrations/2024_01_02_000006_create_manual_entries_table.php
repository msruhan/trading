<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('manual_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->foreignId('account_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('trade_id')->nullable()->constrained()->onDelete('set null');
            $table->enum('entry_type', ['journal', 'note', 'analysis', 'plan'])->default('journal');
            $table->string('title')->nullable();
            $table->text('content');
            $table->date('entry_date');
            $table->json('tags')->nullable();
            $table->json('screenshots')->nullable();
            $table->enum('mood', ['confident', 'neutral', 'anxious', 'frustrated'])->nullable();
            $table->integer('rating')->nullable(); // 1-5 self-rating
            $table->json('lessons_learned')->nullable();
            $table->timestamps();
            
            $table->index(['user_id', 'entry_date']);
            $table->index(['account_id', 'entry_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_entries');
    }
};

