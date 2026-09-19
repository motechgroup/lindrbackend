<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->timestamp('connected_at')->nullable()->after('started_at');
            $table->integer('creator_credits_earned')->default(0)->after('coins_charged');
            $table->integer('lindr_share')->default(0)->after('creator_credits_earned');
            $table->float('creator_commission_pct')->default(0.0)->after('lindr_share');
            $table->string('end_reason')->nullable()->after('creator_commission_pct');
        });
    }

    public function down(): void
    {
        Schema::table('call_sessions', function (Blueprint $table) {
            $table->dropColumn([
                'connected_at',
                'creator_credits_earned',
                'lindr_share',
                'creator_commission_pct',
                'end_reason',
            ]);
        });
    }
};
