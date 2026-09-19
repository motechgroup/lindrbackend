<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gifts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('image_url')->nullable();
            $table->string('animation_reference')->nullable();
            $table->integer('coin_price');
            $table->decimal('recipient_share_percentage', 5, 2)->default(60.00);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('gift_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('gift_id')->constrained('gifts')->cascadeOnDelete();
            $table->integer('coin_price');
            $table->integer('platform_share');
            $table->integer('recipient_share');
            $table->decimal('recipient_earnings_amount', 10, 2);
            $table->string('status')->default('completed');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_transactions');
        Schema::dropIfExists('gifts');
    }
};
