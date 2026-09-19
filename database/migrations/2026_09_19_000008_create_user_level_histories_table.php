<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_level_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->integer('previous_level');
            $table->integer('new_level');
            $table->integer('previous_score')->default(0);
            $table->integer('new_score')->default(0);
            $table->string('reason'); // PROGRESSION, PENALTY, ADMIN_ADJUSTMENT, RECALCULATION
            $table->foreignId('admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_level_histories');
    }
};
