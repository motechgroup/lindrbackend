<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('wallets')) {
            Schema::create('wallets', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
                $table->integer('coin_balance')->default(0);
                $table->integer('balance')->default(0);
                $table->integer('credits')->default(0);
                $table->timestamps();
            });
        }

        Schema::create('wallet_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('wallet_id')->constrained('wallets')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_type'); // CREDIT, DEBIT, REFUND, BONUS, ADJUSTMENT
            $table->integer('amount');
            $table->integer('balance_before');
            $table->integer('balance_after');
            $table->string('reference_type')->nullable();
            $table->string('reference_id')->nullable();
            $table->text('description')->nullable();
            $table->string('status')->default('completed');
            $table->string('idempotency_key')->nullable()->index();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('creator_earnings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('female_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('source_type'); // message, gift, call
            $table->string('source_id')->nullable();
            $table->integer('gross_coins');
            $table->decimal('recipient_share_percentage', 5, 2);
            $table->decimal('recipient_earnings_amount', 10, 2); // KES or earnings currency
            $table->string('status')->default('available'); // pending, available, withdrawn, reversed
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['female_user_id', 'status']);
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->text('value');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('creator_earnings');
        Schema::dropIfExists('wallet_transactions');
        Schema::dropIfExists('wallets');
    }
};
