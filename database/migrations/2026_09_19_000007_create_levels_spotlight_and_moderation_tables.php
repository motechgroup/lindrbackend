<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('level_rules', function (Blueprint $table) {
            $table->id();
            $table->integer('level')->unique();
            $table->string('name');
            $table->integer('threshold_score')->default(0);
            $table->float('exposure_multiplier')->default(1.0);
            $table->float('creator_commission_pct')->default(60.0);
            $table->json('benefits')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('level_penalties', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('moderation_action_id')->nullable();
            $table->integer('score_penalty');
            $table->string('reason');
            $table->timestamps();
        });

        Schema::create('spotlight_packages', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->integer('duration_minutes');
            $table->integer('token_cost');
            $table->float('boost_multiplier')->default(2.0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('spotlight_purchases', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('spotlight_packages')->cascadeOnDelete();
            $table->integer('tokens_spent');
            $table->timestamp('starts_at');
            $table->timestamp('expires_at');
            $table->string('status')->default('active'); // active, expired, revoked
            $table->timestamps();
        });

        Schema::create('community_guidelines', function (Blueprint $table) {
            $table->id();
            $table->string('version')->unique();
            $table->string('title');
            $table->json('content');
            $table->string('status')->default('draft'); // draft, published, retired
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
        });

        Schema::table('reports', function (Blueprint $table) {
            $table->string('category')->nullable()->after('reported_id');
            $table->string('priority')->default('normal')->after('status');
            $table->string('resolution')->nullable()->after('priority');
            $table->timestamp('resolved_at')->nullable()->after('resolution');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('guidelines_version_id')->nullable()->after('resolved_by')->constrained('community_guidelines')->nullOnDelete();
        });

        Schema::create('moderation_actions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('admin_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('report_id')->nullable()->constrained('reports')->nullOnDelete();
            $table->string('action'); // warning, level_penalty, reduce_exposure, restrict_feature, suspend, ban, delete_account, dismiss, no_violation
            $table->text('reason')->nullable();
            $table->integer('score_penalty')->default(0);
            $table->integer('previous_level')->nullable();
            $table->integer('new_level')->nullable();
            $table->string('previous_status')->nullable();
            $table->string('new_status')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('moderation_actions');

        Schema::table('reports', function (Blueprint $table) {
            $table->dropForeign(['guidelines_version_id']);
            $table->dropForeign(['resolved_by']);
            $table->dropColumn(['category', 'priority', 'resolution', 'resolved_at', 'resolved_by', 'guidelines_version_id']);
        });

        Schema::dropIfExists('community_guidelines');
        Schema::dropIfExists('spotlight_purchases');
        Schema::dropIfExists('spotlight_packages');
        Schema::dropIfExists('level_penalties');
        Schema::dropIfExists('level_rules');
    }
};
