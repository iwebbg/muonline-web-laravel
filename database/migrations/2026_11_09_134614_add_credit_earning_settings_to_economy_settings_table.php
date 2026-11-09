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
            $table->boolean('item_exchange_enabled')->default(false);
            $table->boolean('online_time_rewards_enabled')->default(false);
            $table->unsignedSmallInteger('online_minutes_per_unit')->default(10);
            $table->unsignedBigInteger('online_credits_per_unit')->default(5);
            $table->unsignedBigInteger('online_daily_credit_limit')->default(150);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('economy_settings', function (Blueprint $table) {
            $table->dropColumn([
                'item_exchange_enabled',
                'online_time_rewards_enabled',
                'online_minutes_per_unit',
                'online_credits_per_unit',
                'online_daily_credit_limit',
            ]);
        });
    }
};
