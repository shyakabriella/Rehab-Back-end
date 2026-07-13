<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mood_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            /*
             * Store mood as a string instead of enum.
             *
             * This works correctly with SQLite and makes it easier
             * to add new mood values later without changing the database.
             */
            $table->string('mood', 255);

            // Values should be between 0 and 10.
            $table->unsignedTinyInteger('stress_level')->nullable();
            $table->unsignedTinyInteger('craving_level')->nullable();
            $table->unsignedTinyInteger('energy_level')->nullable();

            $table->enum('sleep_quality', [
                'poor',
                'fair',
                'good',
                'very_good',
            ])->nullable();

            $table->boolean('had_craving')->default(false);

            $table->string('main_trigger')->nullable();

            $table->text('notes')->nullable();

            $table->date('logged_date');

            $table->timestamps();

            /*
             * A user can create only one mood log for each date.
             */
            $table->unique(
                ['user_id', 'logged_date'],
                'mood_logs_user_date_unique'
            );

            $table->index('logged_date');
            $table->index('mood');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mood_logs');
    }
};