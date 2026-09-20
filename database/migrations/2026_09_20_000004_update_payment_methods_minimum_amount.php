<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('payment_methods')) {
            DB::table('payment_methods')->update(['minimum_amount' => 1.00]);
        }
    }

    public function down(): void
    {
        // No-op rollback
    }
};
