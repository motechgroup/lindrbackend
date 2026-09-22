<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'paypal_email')) {
                $table->string('paypal_email')->nullable()->after('mpesa_phone_verified');
            }
            if (! Schema::hasColumn('users', 'payout_method')) {
                $table->string('payout_method')->default('mpesa')->after('paypal_email');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'paypal_email')) {
                $table->dropColumn('paypal_email');
            }
            if (Schema::hasColumn('users', 'payout_method')) {
                $table->dropColumn('payout_method');
            }
        });
    }
};
