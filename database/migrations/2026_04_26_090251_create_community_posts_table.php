<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_posts', function (Blueprint $table) {
            $table->id();

            $table->foreignId('community_group_id')
                ->constrained('community_groups')
                ->cascadeOnDelete();

            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('title')->nullable();
            $table->longText('body');

            $table->enum('mood_tag', [
                'hopeful',
                'struggling',
                'proud',
                'sad',
                'stressed',
                'motivated',
                'need_support',
                'general'
            ])->default('general');

            $table->boolean('is_anonymous')->default(true);

            $table->enum('status', [
                'published',
                'hidden',
                'deleted'
            ])->default('published');

            $table->unsignedInteger('support_count')->default(0);
            $table->unsignedInteger('comments_count')->default(0);

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_posts');
    }
};