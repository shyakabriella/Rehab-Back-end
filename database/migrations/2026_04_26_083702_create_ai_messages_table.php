<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();

            $table->foreignId('ai_session_id')
                ->constrained('ai_sessions')
                ->cascadeOnDelete();

            $table->enum('sender', [
                'user',
                'assistant',
                'system'
            ]);

            $table->longText('message');

            $table->enum('message_type', [
                'text',
                'recommendation',
                'warning',
                'crisis_support'
            ])->default('text');

            $table->enum('risk_level', [
                'low',
                'medium',
                'high',
                'crisis'
            ])->nullable();

            $table->text('recommendation')->nullable();

            $table->json('metadata')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_messages');
    }
};