<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coin_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('coin_amount');
            $table->integer('bonus_coins')->default(0);
            $table->decimal('price_kes', 10, 2);
            $table->boolean('is_active')->default(true);
            $table->integer('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('coin_purchases', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('coin_packages')->cascadeOnDelete();
            $table->decimal('amount_kes', 10, 2);
            $table->integer('coins_credited');
            $table->string('payment_method')->default('mpesa');
            $table->string('status')->default('pending'); // pending, successful, failed, cancelled
            $table->string('phone_number');
            $table->string('checkout_request_id')->nullable()->index();
            $table->string('merchant_request_id')->nullable();
            $table->string('mpesa_receipt_number')->nullable();
            $table->string('idempotency_key')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('coin_purchases');
        Schema::dropIfExists('coin_packages');
    }
};
