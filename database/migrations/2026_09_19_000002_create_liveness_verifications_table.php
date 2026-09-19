<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('liveness_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('challenge_id')->nullable();
            $table->json('challenge_sequence')->nullable();
            $table->json('gestures_completed')->nullable();
            $table->string('status')->default('pending'); // pending, passed, failed, rejected
            $table->string('selfie_path')->nullable();
            $table->text('failure_reason')->nullable();
            $table->string('ip_address')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('liveness_verifications');
    }
};
