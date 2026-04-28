<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            if (!Schema::hasColumn('ai_sessions', 'assistant_type')) {
                $table->string('assistant_type')->default('recovery_support')->after('title');
            }

            if (!Schema::hasColumn('ai_sessions', 'prompt_file')) {
                $table->string('prompt_file')->default('recovery_support.md')->after('assistant_type');
            }

            if (!Schema::hasColumn('ai_sessions', 'prompt_version')) {
                $table->string('prompt_version')->default('v1.0')->after('prompt_file');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            if (Schema::hasColumn('ai_sessions', 'prompt_version')) {
                $table->dropColumn('prompt_version');
            }

            if (Schema::hasColumn('ai_sessions', 'prompt_file')) {
                $table->dropColumn('prompt_file');
            }

            if (Schema::hasColumn('ai_sessions', 'assistant_type')) {
                $table->dropColumn('assistant_type');
            }
        });
    }
};