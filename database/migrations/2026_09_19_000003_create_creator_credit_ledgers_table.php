<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('creator_credit_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('transaction_type'); // CALL_EARNING, CHAT_EARNING, GIFT_EARNING, WITHDRAWAL, ADJUSTMENT, REVERSAL
            $table->decimal('amount_credits', 12, 2);
            $table->decimal('balance_after', 12, 2);
            $table->integer('gross_token_amount')->default(0);
            $table->integer('platform_share_tokens')->default(0);
            $table->text('description')->nullable();
            $table->string('reference_id')->nullable();
            $table->string('reference_type')->nullable();
            $table->timestamp('created_at')->useCurrent();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('creator_credit_ledgers');
    }
};
