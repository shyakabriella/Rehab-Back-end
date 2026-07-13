<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patient_conditions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->enum('condition_type', ['addiction', 'illness']);
            $table->string('condition_name', 150);
            $table->enum('diagnosis_status', ['self_reported', 'suspected', 'confirmed'])
                ->default('self_reported');
            $table->enum('care_status', ['active', 'in_treatment', 'in_recovery', 'resolved'])
                ->default('active');
            $table->enum('severity', ['low', 'moderate', 'high', 'critical'])->nullable();
            $table->date('diagnosed_at')->nullable();
            $table->date('resolved_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'condition_type', 'condition_name'], 'patient_condition_unique');
            $table->index(['condition_type', 'condition_name']);
            $table->index(['user_id', 'care_status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patient_conditions');
    }
};
