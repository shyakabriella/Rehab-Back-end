<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('device_tokens', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->text('device_token');

            $table->enum('platform', [
                'android',
                'ios',
                'web',
                'unknown'
            ])->default('unknown');

            $table->string('device_name')->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamp('last_used_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('device_tokens');
    }
};