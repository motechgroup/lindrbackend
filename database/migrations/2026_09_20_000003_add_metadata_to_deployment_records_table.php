<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('deployment_records')) {
            Schema::table('deployment_records', function (Blueprint $table) {
                if (! Schema::hasColumn('deployment_records', 'commit_hash')) {
                    $table->string('commit_hash')->nullable()->after('branch_version');
                }
                if (! Schema::hasColumn('deployment_records', 'commit_message')) {
                    $table->text('commit_message')->nullable()->after('commit_hash');
                }
                if (! Schema::hasColumn('deployment_records', 'commit_author')) {
                    $table->string('commit_author')->nullable()->after('commit_message');
                }
                if (! Schema::hasColumn('deployment_records', 'executed_migrations')) {
                    $table->json('executed_migrations')->nullable()->after('commit_author');
                }
                if (! Schema::hasColumn('deployment_records', 'pending_migrations_count')) {
                    $table->integer('pending_migrations_count')->default(0)->after('executed_migrations');
                }
            });
        }
    }

    public function down(): void
    {
        // Safe no-op
    }
};
