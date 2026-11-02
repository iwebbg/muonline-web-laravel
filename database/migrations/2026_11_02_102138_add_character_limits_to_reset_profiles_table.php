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
            $table->unsignedSmallInteger('maximum_stat')
                ->default(32_767)
                ->after('maximum_resets');
            $table->unsignedInteger('grand_reset_required_resets')
                ->default(100)
                ->after('maximum_stat');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('reset_profiles', function (Blueprint $table) {
            $table->dropColumn([
                'maximum_stat',
                'grand_reset_required_resets',
            ]);
        });
    }
};
