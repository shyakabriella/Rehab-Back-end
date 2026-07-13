<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_awards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('certificate_number')->unique();
            $table->enum('award_type', ['full_recovery'])->default('full_recovery');
            $table->string('title')->default('Full Recovery Recognition Award');
            $table->date('awarded_at');
            $table->foreignId('awarded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('status', ['active', 'revoked'])->default('active');
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('notes')->nullable();
            $table->json('criteria_snapshot')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'award_type', 'status']);
            $table->index('awarded_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_awards');
    }
};
