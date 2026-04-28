<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('gender', ['male', 'female', 'other', 'prefer_not_to_say'])->nullable();
            $table->enum('age_range', ['under_18', '18_25', '26_35', '36_45', '46_plus'])->nullable();

            $table->enum('addiction_type', ['alcohol', 'drugs', 'both', 'other'])->nullable();
            $table->enum('recovery_stage', ['beginner', 'ongoing', 'stable', 'relapse_risk'])->default('beginner');

            $table->date('recovery_start_date')->nullable();
            $table->text('main_goal')->nullable();
            $table->enum('support_level', ['low', 'medium', 'high'])->default('medium');

            $table->enum('privacy_mode', ['private', 'anonymous', 'public'])->default('anonymous');

            $table->string('emergency_contact_name')->nullable();
            $table->string('emergency_contact_phone')->nullable();

            $table->text('bio')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};