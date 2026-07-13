<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Change mood from ENUM to VARCHAR.
     *
     * This allows values such as:
     * happy,motivated,calm,hopeful
     */
    public function up(): void
    {
        DB::statement(
            "ALTER TABLE `mood_logs`
             MODIFY COLUMN `mood` VARCHAR(255) NOT NULL"
        );
    }

    /**
     * Restore the original ENUM column.
     *
     * Rollback is blocked when multiple moods already exist because
     * MySQL ENUM cannot store comma-separated mood values.
     */
    public function down(): void
    {
        $hasMultipleMoods = DB::table('mood_logs')
            ->where('mood', 'like', '%,%')
            ->exists();

        if ($hasMultipleMoods) {
            throw new RuntimeException(
                'Cannot rollback the mood column because some records contain multiple moods.'
            );
        }

        DB::statement(
            "ALTER TABLE `mood_logs`
             MODIFY COLUMN `mood` ENUM(
                'happy',
                'sad',
                'stressed',
                'anxious',
                'angry',
                'hopeful',
                'tired',
                'calm',
                'lonely',
                'motivated'
             ) NOT NULL"
        );
    }
};