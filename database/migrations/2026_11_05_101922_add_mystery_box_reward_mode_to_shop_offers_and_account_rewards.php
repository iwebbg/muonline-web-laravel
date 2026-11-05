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
        Schema::table('shop_offers', function (Blueprint $table) {
            $table->string('mystery_box_reward_mode', 32)->default('one_random')->after('type');
        });

        Schema::table('account_rewards', function (Blueprint $table) {
            $table->string('mystery_box_reward_mode', 32)->default('one_random')->after('reward_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_rewards', function (Blueprint $table) {
            $table->dropColumn('mystery_box_reward_mode');
        });

        Schema::table('shop_offers', function (Blueprint $table) {
            $table->dropColumn('mystery_box_reward_mode');
        });
    }
};
