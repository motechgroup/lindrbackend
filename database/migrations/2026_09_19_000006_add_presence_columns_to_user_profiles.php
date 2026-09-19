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
                if (! Schema::hasColumn('user_profiles', 'last_heartbeat_at')) {
                    $table->timestamp('last_heartbeat_at')->nullable()->after('online_status');
                }
                if (! Schema::hasColumn('user_profiles', 'current_call_session_id')) {
                    $table->string('current_call_session_id')->nullable()->after('last_heartbeat_at');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('user_profiles')) {
            Schema::table('user_profiles', function (Blueprint $table) {
                if (Schema::hasColumn('user_profiles', 'last_heartbeat_at')) {
                    $table->dropColumn('last_heartbeat_at');
                }
                if (Schema::hasColumn('user_profiles', 'current_call_session_id')) {
                    $table->dropColumn('current_call_session_id');
                }
            });
        }
    }
};
