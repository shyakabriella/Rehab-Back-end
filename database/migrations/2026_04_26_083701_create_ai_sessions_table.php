<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_sessions', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('mood_log_id')
                ->nullable()
                ->constrained('mood_logs')
                ->nullOnDelete();

            $table->foreignId('trigger_log_id')
                ->nullable()
                ->constrained('trigger_logs')
                ->nullOnDelete();

            $table->string('title')->nullable();

            $table->enum('status', [
                'active',
                'closed',
                'archived'
            ])->default('active');

            $table->enum('risk_level', [
                'low',
                'medium',
                'high',
                'crisis'
            ])->default('low');

            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_sessions');
    }
};