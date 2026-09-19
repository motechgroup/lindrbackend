<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('creator_credit_ledgers', function (Blueprint $table) {
            if (! Schema::hasColumn('creator_credit_ledgers', 'conversion_rate')) {
                $table->decimal('conversion_rate', 10, 4)->default(1.0000)->after('balance_after');
            }
            if (! Schema::hasColumn('creator_credit_ledgers', 'cash_value_kes')) {
                $table->decimal('cash_value_kes', 12, 2)->nullable()->after('conversion_rate');
            }
            if (! Schema::hasColumn('creator_credit_ledgers', 'cash_value_usd')) {
                $table->decimal('cash_value_usd', 12, 2)->nullable()->after('cash_value_kes');
            }

            $table->index(['user_id', 'transaction_type', 'reference_type', 'reference_id'], 'idx_ledger_idempotency');
            $table->index(['user_id', 'created_at'], 'idx_ledger_user_history');
        });
    }

    public function down(): void
    {
        Schema::table('creator_credit_ledgers', function (Blueprint $table) {
            $table->dropIndex('idx_ledger_idempotency');
            $table->dropIndex('idx_ledger_user_history');
            $table->dropColumn(['conversion_rate', 'cash_value_kes', 'cash_value_usd']);
        });
    }
};
