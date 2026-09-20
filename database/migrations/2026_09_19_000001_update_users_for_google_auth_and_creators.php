<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'provider')) {
                $table->string('provider')->nullable();
            }
            if (! Schema::hasColumn('users', 'provider_user_id')) {
                $table->string('provider_user_id')->nullable();
            }
            if (! Schema::hasColumn('users', 'avatar')) {
                $table->string('avatar')->nullable();
            }
            if (! Schema::hasColumn('users', 'is_creator')) {
                $table->boolean('is_creator')->default(false);
            }
            if (! Schema::hasColumn('users', 'creator_status')) {
                $table->string('creator_status')->default('unverified');
            }
            if (! Schema::hasColumn('users', 'liveness_verified_at')) {
                $table->timestamp('liveness_verified_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'mpesa_phone')) {
                $table->string('mpesa_phone')->nullable();
            }
            if (! Schema::hasColumn('users', 'mpesa_phone_verified')) {
                $table->boolean('mpesa_phone_verified')->default(false);
            }
            if (! Schema::hasColumn('users', 'mpesa_verified_at')) {
                $table->timestamp('mpesa_verified_at')->nullable();
            }
            if (! Schema::hasColumn('users', 'payout_hold_until')) {
                $table->timestamp('payout_hold_until')->nullable();
            }
        });

        if (Schema::hasTable('wallets')) {
            if (! Schema::hasColumn('wallets', 'balance')) {
                Schema::table('wallets', function (Blueprint $table) {
                    $table->integer('balance')->default(0);
                });
            }
            if (! Schema::hasColumn('wallets', 'credits')) {
                Schema::table('wallets', function (Blueprint $table) {
                    $table->integer('credits')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        // Safe no-op on rollback
    }
};
