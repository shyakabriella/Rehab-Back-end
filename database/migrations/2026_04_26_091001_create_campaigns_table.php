<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();

            $table->enum('campaign_type', [
                'alcohol_awareness',
                'drug_prevention',
                'mental_health',
                'youth_recovery',
                'community_support',
                'general'
            ])->default('general');

            $table->enum('status', [
                'draft',
                'published',
                'archived'
            ])->default('draft');

            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();

            $table->string('banner_image')->nullable();
            $table->boolean('is_featured')->default(false);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaigns');
    }
};