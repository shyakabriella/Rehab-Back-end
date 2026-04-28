<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('mood', [
                'happy',
                'sad',
                'stressed',
                'anxious',
                'angry',
                'hopeful',
                'tired',
                'calm',
                'lonely',
                'motivated'
            ]);

            $table->unsignedTinyInteger('stress_level')->nullable();   // 0 to 10
            $table->unsignedTinyInteger('craving_level')->nullable();  // 0 to 10
            $table->unsignedTinyInteger('energy_level')->nullable();   // 0 to 10

            $table->enum('sleep_quality', [
                'poor',
                'fair',
                'good',
                'very_good'
            ])->nullable();

            $table->boolean('had_craving')->default(false);
            $table->string('main_trigger')->nullable();
            $table->text('notes')->nullable();

            $table->date('logged_date');

            $table->timestamps();

            $table->unique(['user_id', 'logged_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};