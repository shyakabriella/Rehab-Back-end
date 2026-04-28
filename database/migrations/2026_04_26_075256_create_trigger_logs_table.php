<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trigger_logs', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->enum('trigger_type', [
                'peer_pressure',
                'stress',
                'loneliness',
                'family_conflict',
                'money_problem',
                'relationship_issue',
                'environment',
                'bad_memory',
                'boredom',
                'other'
            ]);

            $table->unsignedTinyInteger('intensity_level')->default(0); // 0 to 10

            $table->string('location')->nullable();

            $table->text('coping_action')->nullable();

            $table->enum('result', [
                'resisted',
                'relapsed',
                'still_struggling'
            ])->default('resisted');

            $table->text('notes')->nullable();

            $table->dateTime('triggered_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trigger_logs');
    }
};