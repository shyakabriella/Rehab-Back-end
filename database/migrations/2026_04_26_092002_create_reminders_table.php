<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title');
            $table->text('description')->nullable();

            $table->enum('reminder_type', [
                'mood_checkin',
                'goal_progress',
                'sobriety_milestone',
                'ai_followup',
                'campaign_alert',
                'custom'
            ])->default('custom');

            $table->enum('frequency', [
                'once',
                'daily',
                'weekly',
                'monthly'
            ])->default('once');

            $table->time('remind_time')->nullable();
            $table->date('remind_date')->nullable();

            $table->json('days_of_week')->nullable();

            $table->enum('status', [
                'active',
                'paused',
                'completed',
                'cancelled'
            ])->default('active');

            $table->timestamp('last_triggered_at')->nullable();
            $table->timestamp('next_trigger_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index('next_trigger_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};