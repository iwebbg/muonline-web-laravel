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
        Schema::table('reset_profiles', function (Blueprint $table): void {
            $table->boolean('move_to_town_enabled')->default(true)->after('pk_clear_price_mode');
            $table->unsignedSmallInteger('move_to_town_map')->default(0)->after('move_to_town_enabled');
            $table->unsignedSmallInteger('move_to_town_x')->default(141)->after('move_to_town_map');
            $table->unsignedSmallInteger('move_to_town_y')->default(127)->after('move_to_town_x');
            $table->unsignedBigInteger('move_to_town_zen_cost')->default(0)->after('move_to_town_y');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table): void {
            $table->dropColumn([
                'move_to_town_enabled',
                'move_to_town_map',
                'move_to_town_x',
                'move_to_town_y',
                'move_to_town_zen_cost',
            ]);
        });
    }
};
