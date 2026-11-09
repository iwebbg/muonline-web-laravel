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
            $table->string('reset_reward_type', 32)->default('none')->after('inventory_policy');
            $table->unsignedBigInteger('reset_reward_credits')->default(0)->after('reset_reward_type');
            $table->foreignId('reset_reward_shop_offer_id')
                ->nullable()
                ->after('reset_reward_credits')
                ->constrained('shop_offers')
                ->noActionOnDelete();
            $table->string('grand_reset_reward_type', 32)
                ->default('none')
                ->after('grand_reset_consume_required_resets');
            $table->unsignedBigInteger('grand_reset_reward_credits')
                ->default(0)
                ->after('grand_reset_reward_type');
            $table->foreignId('grand_reset_reward_shop_offer_id')
                ->nullable()
                ->after('grand_reset_reward_credits')
                ->constrained('shop_offers')
                ->noActionOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('reset_reward_shop_offer_id');
            $table->dropConstrainedForeignId('grand_reset_reward_shop_offer_id');
            $table->dropColumn([
                'reset_reward_type',
                'reset_reward_credits',
                'grand_reset_reward_type',
                'grand_reset_reward_credits',
            ]);
        });
    }
};
