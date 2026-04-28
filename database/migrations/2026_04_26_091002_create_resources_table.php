<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();

            $table->foreignId('resource_category_id')
                ->nullable()
                ->constrained('resource_categories')
                ->nullOnDelete();

            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->string('title');
            $table->string('slug')->unique();

            $table->text('summary')->nullable();
            $table->longText('content')->nullable();

            $table->enum('resource_type', [
                'article',
                'video',
                'audio',
                'guide',
                'tip',
                'external_link'
            ])->default('article');

            $table->string('media_url')->nullable();
            $table->string('external_url')->nullable();
            $table->string('thumbnail')->nullable();

            $table->enum('status', [
                'draft',
                'published',
                'archived'
            ])->default('draft');

            $table->boolean('is_featured')->default(false);
            $table->unsignedInteger('views_count')->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};