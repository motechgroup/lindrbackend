<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('withdrawals', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('female_user_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('amount_kes', 10, 2);
            $table->string('mpesa_number');
            $table->string('status')->default('pending'); // pending, approved, rejected, processing, paid, failed
            $table->string('provider_reference')->nullable();
            $table->text('admin_notes')->nullable();
            $table->foreignId('processed_by_admin_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('withdrawals');
    }
};
