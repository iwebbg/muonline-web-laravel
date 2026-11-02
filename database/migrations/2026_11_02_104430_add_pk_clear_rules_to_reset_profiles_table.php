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
            $table->boolean('pk_clear_enabled')
                ->default(true)
                ->after('stat_reset_zen_cost');
            $table->unsignedSmallInteger('pk_clear_required_level')
                ->default(100)
                ->after('pk_clear_enabled');
            $table->unsignedBigInteger('pk_clear_base_zen_cost')
                ->default(100_000)
                ->after('pk_clear_required_level');
            $table->string('pk_clear_price_mode', 32)
                ->default('fixed')
                ->after('pk_clear_base_zen_cost');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'pk_clear_enabled',
                'pk_clear_required_level',
                'pk_clear_base_zen_cost',
                'pk_clear_price_mode',
            ]);
        });
    }
};
