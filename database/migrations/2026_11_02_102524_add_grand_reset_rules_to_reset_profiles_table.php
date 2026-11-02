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
            $table->boolean('grand_reset_enabled')
                ->default(true)
                ->after('grand_reset_required_resets');
            $table->unsignedSmallInteger('grand_reset_required_level')
                ->default(350)
                ->after('grand_reset_enabled');
            $table->unsignedInteger('maximum_grand_resets')
                ->default(999)
                ->after('grand_reset_required_level');
            $table->unsignedBigInteger('grand_reset_base_zen_cost')
                ->default(5_000_000)
                ->after('maximum_grand_resets');
            $table->boolean('grand_reset_multiply_cost')
                ->default(false)
                ->after('grand_reset_base_zen_cost');
            $table->boolean('grand_reset_clear_stats')
                ->default(false)
                ->after('grand_reset_multiply_cost');
            $table->string('grand_reset_points_mode', 32)
                ->default('add_fixed')
                ->after('grand_reset_clear_stats');
            $table->unsignedInteger('grand_reset_points')
                ->default(500)
                ->after('grand_reset_points_mode');
            $table->string('grand_reset_inventory_policy', 32)
                ->default('keep_all')
                ->after('grand_reset_points');
            $table->boolean('grand_reset_consume_required_resets')
                ->default(true)
                ->after('grand_reset_inventory_policy');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'grand_reset_enabled',
                'grand_reset_required_level',
                'maximum_grand_resets',
                'grand_reset_base_zen_cost',
                'grand_reset_multiply_cost',
                'grand_reset_clear_stats',
                'grand_reset_points_mode',
                'grand_reset_points',
                'grand_reset_inventory_policy',
                'grand_reset_consume_required_resets',
            ]);
        });
    }
};
