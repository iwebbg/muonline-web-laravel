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
        Schema::table('economy_settings', function (Blueprint $table) {
            $table->boolean('character_reset_purchase_enabled')->default(false);
            $table->unsignedBigInteger('character_reset_price_credits')->default(100);
            $table->boolean('character_level_up_points_purchase_enabled')->default(false);
            $table->unsignedInteger('character_level_up_points_package_size')->default(1_000);
            $table->unsignedBigInteger('character_level_up_points_price_credits')->default(100);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('economy_settings', function (Blueprint $table) {
            $table->dropColumn([
                'character_reset_purchase_enabled',
                'character_reset_price_credits',
                'character_level_up_points_purchase_enabled',
                'character_level_up_points_package_size',
                'character_level_up_points_price_credits',
            ]);
        });
    }
};
