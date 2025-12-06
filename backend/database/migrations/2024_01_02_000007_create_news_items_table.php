<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->onDelete('set null');
            $table->date('date');
            $table->time('time')->nullable();
            $table->string('title');
            $table->text('summary')->nullable();
            $table->string('source')->nullable();
            $table->string('url')->nullable();
            $table->string('currency', 10)->nullable(); // affected currency
            $table->enum('impact', ['low', 'medium', 'high'])->default('medium');
            $table->string('actual', 50)->nullable();
            $table->string('forecast', 50)->nullable();
            $table->string('previous', 50)->nullable();
            $table->boolean('is_manual')->default(true);
            $table->timestamps();
            
            $table->index(['date', 'impact']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};

