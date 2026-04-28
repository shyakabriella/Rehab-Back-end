<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->string('assistant_type')->default('recovery_support')->after('title');
            $table->string('prompt_version')->default('v1.0')->after('assistant_type');
        });
    }

    public function down(): void
    {
        Schema::table('ai_sessions', function (Blueprint $table) {
            $table->dropColumn(['assistant_type', 'prompt_version']);
        });
    }
};