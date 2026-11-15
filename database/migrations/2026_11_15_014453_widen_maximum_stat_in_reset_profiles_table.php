<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keeps native 65000 stat limits representable on SQL Server without changing saved policies.
 */
return new class extends Migration
{
    /**
     * Widens the application-owned setting while retaining its existing default.
     */
    public function up(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table): void {
            $table->unsignedInteger('maximum_stat')->default(32_767)->change();
        });
    }

    /**
     * Restores the portable smallint range only when all saved values still fit.
     */
    public function down(): void
    {
        if (DB::table('reset_profiles')->where('maximum_stat', '>', 32_767)->exists()) {
            throw new RuntimeException('Reduce saved maximum_stat values to 32767 or less before rolling back this migration.');
        }

        Schema::table('reset_profiles', function (Blueprint $table): void {
            $table->unsignedSmallInteger('maximum_stat')->default(32_767)->change();
        });
    }
};
