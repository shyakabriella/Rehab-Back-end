<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recovery_goals', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('category', [
                'sobriety',
                'health',
                'mental_health',
                'community',
                'habit',
                'education',
                'other'
            ])->default('other');

            $table->enum('priority', [
                'low',
                'medium',
                'high'
            ])->default('medium');

            $table->enum('status', [
                'not_started',
                'in_progress',
                'completed',
                'cancelled'
            ])->default('not_started');

            $table->unsignedTinyInteger('progress_percent')->default(0); // 0 to 100

            $table->date('start_date')->nullable();
            $table->date('target_date')->nullable();
            $table->date('completed_date')->nullable();

            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recovery_goals');
    }
};