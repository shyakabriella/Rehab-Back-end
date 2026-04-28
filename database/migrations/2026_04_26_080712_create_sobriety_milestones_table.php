<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sobriety_milestones', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('milestone_type', [
                'first_day',
                'one_week',
                'two_weeks',
                'one_month',
                'three_months',
                'six_months',
                'one_year',
                'custom'
            ])->default('custom');

            $table->unsignedInteger('milestone_days')->default(1);

            $table->date('achieved_date')->nullable();

            $table->enum('status', [
                'in_progress',
                'achieved',
                'missed',
                'reset'
            ])->default('in_progress');

            $table->string('title')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sobriety_milestones');
    }
};