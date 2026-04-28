<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reported_contents', function (Blueprint $table) {
            $table->id();

            $table->foreignId('reporter_user_id')
                ->constrained('users')
                ->cascadeOnDelete();

            $table->foreignId('community_post_id')
                ->nullable()
                ->constrained('community_posts')
                ->cascadeOnDelete();

            $table->foreignId('community_comment_id')
                ->nullable()
                ->constrained('community_comments')
                ->cascadeOnDelete();

            $table->enum('reason', [
                'abuse',
                'harassment',
                'harmful_advice',
                'spam',
                'triggering_content',
                'privacy_issue',
                'other'
            ])->default('other');

            $table->text('details')->nullable();

            $table->enum('status', [
                'pending',
                'reviewed',
                'resolved',
                'dismissed'
            ])->default('pending');

            $table->foreignId('reviewed_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->timestamp('reviewed_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reported_contents');
    }
};