<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('enabled')->default(true);
            $table->boolean('test_mode')->default(true);
            $table->integer('priority')->default(1);
            $table->json('supported_countries')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->text('configuration')->nullable();
            $table->text('webhook_secret')->nullable();
            $table->string('status')->default('active'); // active, degraded, inactive
            $table->timestamps();
        });

        Schema::create('payment_methods', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('provider_code');
            $table->boolean('enabled')->default(true);
            $table->json('supported_countries')->nullable();
            $table->json('supported_currencies')->nullable();
            $table->decimal('minimum_amount', 10, 2)->nullable();
            $table->decimal('maximum_amount', 10, 2)->nullable();
            $table->integer('display_order')->default(0);
            $table->timestamps();

            $table->foreign('provider_code')->references('code')->on('payment_providers')->onDelete('cascade');
        });

        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('public_reference')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('coin_packages')->cascadeOnDelete();
            $table->string('provider_code');
            $table->string('payment_method_code');
            $table->string('country')->default('KE');
            $table->string('currency')->default('KES');
            $table->decimal('amount', 10, 2);
            $table->integer('expected_coins');
            $table->string('provider_reference')->nullable()->index();
            $table->string('status')->default('pending'); // created, pending, processing, successful, failed, cancelled, expired, refunded
            $table->string('idempotency_key')->nullable()->unique();
            $table->json('metadata')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });

        Schema::create('payment_webhooks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('provider_code');
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhooks');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payment_methods');
        Schema::dropIfExists('payment_providers');
    }
};
