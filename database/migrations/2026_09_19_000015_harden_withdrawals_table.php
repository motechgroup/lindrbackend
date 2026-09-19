<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            if (! Schema::hasColumn('withdrawals', 'user_id')) {
                $table->foreignId('user_id')->nullable()->after('id')->constrained('users')->cascadeOnDelete();
            }
            if (! Schema::hasColumn('withdrawals', 'credits_deducted')) {
                $table->integer('credits_deducted')->default(0)->after('user_id');
            }
            if (! Schema::hasColumn('withdrawals', 'conversion_rate')) {
                $table->decimal('conversion_rate', 10, 4)->default(1.0000)->after('amount_kes');
            }
            if (! Schema::hasColumn('withdrawals', 'cash_amount_usd')) {
                $table->decimal('cash_amount_usd', 10, 2)->nullable()->after('conversion_rate');
            }
            if (! Schema::hasColumn('withdrawals', 'method')) {
                $table->string('method')->default('mpesa')->after('cash_amount_usd');
            }
            if (! Schema::hasColumn('withdrawals', 'idempotency_key')) {
                $table->string('idempotency_key')->nullable()->after('method');
            }
            if (! Schema::hasColumn('withdrawals', 'failure_reason')) {
                $table->text('failure_reason')->nullable()->after('admin_notes');
            }
            if (! Schema::hasColumn('withdrawals', 'processed_at')) {
                $table->timestamp('processed_at')->nullable()->after('processed_by_admin_id');
            }
        });

        // Backfill user_id from legacy female_user_id
        DB::statement('UPDATE withdrawals SET user_id = female_user_id WHERE user_id IS NULL');

        Schema::table('withdrawals', function (Blueprint $table) {
            $table->index(['user_id', 'idempotency_key'], 'idx_withdrawal_user_idempotency');
            $table->index(['user_id', 'status'], 'idx_withdrawal_user_status');
        });
    }

    public function down(): void
    {
        Schema::table('withdrawals', function (Blueprint $table) {
            $table->dropIndex('idx_withdrawal_user_idempotency');
            $table->dropIndex('idx_withdrawal_user_status');
            $table->dropColumn([
                'user_id',
                'credits_deducted',
                'conversion_rate',
                'cash_amount_usd',
                'method',
                'idempotency_key',
                'failure_reason',
                'processed_at',
            ]);
        });
    }
};
