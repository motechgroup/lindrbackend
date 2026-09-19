<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('user_profiles')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                if (! Schema::hasColumn('user_profiles', 'country_code')) {
                    $table->string('country_code', 10)->nullable()->after('country');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_profiles')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('user_profiles', 'country_code')) {
                    $table->dropColumn('country_code');
                }
            });
        }
    }
};
