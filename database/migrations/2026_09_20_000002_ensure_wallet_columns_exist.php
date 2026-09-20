<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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

            if (! Schema::hasColumn('wallets', 'coin_balance')) {
                Schema::table('wallets', function (Blueprint $table) {
                    $table->integer('coin_balance')->default(0);
                });
            }
        }
    }

    public function down(): void
    {
        // Safe no-op
    }
};
