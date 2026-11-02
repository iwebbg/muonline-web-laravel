<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->boolean('stat_reset_enabled')
                ->default(true)
                ->after('grand_reset_consume_required_resets');
            $table->unsignedSmallInteger('stat_reset_required_level')
                ->default(1)
                ->after('stat_reset_enabled');
            $table->unsignedInteger('stat_reset_required_resets')
                ->default(1)
                ->after('stat_reset_required_level');
            $table->unsignedBigInteger('stat_reset_zen_cost')
                ->default(0)
                ->after('stat_reset_required_resets');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'stat_reset_enabled',
                'stat_reset_required_level',
                'stat_reset_required_resets',
                'stat_reset_zen_cost',
            ]);
        });
    }
};
