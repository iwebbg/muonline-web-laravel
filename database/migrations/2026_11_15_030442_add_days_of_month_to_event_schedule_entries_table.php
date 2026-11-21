<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Adds optional monthly recurrence restrictions without changing existing schedules.
 */
return new class extends Migration
{
    /**
     * Adds nullable calendar days to application-owned event overrides.
     */
    public function up(): void
    {
        Schema::table('event_schedule_entries', function (Blueprint $table): void {
            $table->json('days_of_month')->nullable();
        });
    }

    /**
     * Removes the column only when no stored monthly restrictions would be lost.
     */
    public function down(): void
    {
        if (DB::table('event_schedule_entries')->whereNotNull('days_of_month')->where('days_of_month', '<>', '[]')->exists()) {
            throw new RuntimeException('Monthly event restrictions must be removed before rolling back their column.');
        }

        Schema::table('event_schedule_entries', function (Blueprint $table): void {
            $table->dropColumn('days_of_month');
        });
    }
};
