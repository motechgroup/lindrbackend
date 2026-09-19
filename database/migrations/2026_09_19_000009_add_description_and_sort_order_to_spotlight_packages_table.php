<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('spotlight_packages', function (Blueprint $table) {
            $table->text('description')->nullable()->after('boost_multiplier');
            $table->integer('sort_order')->default(0)->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('spotlight_packages', function (Blueprint $table) {
            $table->dropColumn(['description', 'sort_order']);
        });
    }
};
