<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deployment_records')) {
            Schema::create('deployment_records', function (Blueprint $table) {
                $table->id();
                $table->foreignId('initiated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('action');
                $table->string('branch_version')->default('main');
                $table->string('status')->default('pending');
                $table->text('output_summary')->nullable();
                $table->text('error_summary')->nullable();
                $table->string('ip_address')->nullable();
                $table->timestamp('started_at')->useCurrent();
                $table->timestamp('completed_at')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('deployment_records');
    }
};
