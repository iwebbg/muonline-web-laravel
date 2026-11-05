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
        Schema::table('vote_sites', function (Blueprint $table) {
            $table->boolean('auto_reward_on_click')
                ->default(false)
                ->after('reward_zen');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('vote_sites', function (Blueprint $table) {
            $table->dropColumn('auto_reward_on_click');
        });
    }
};
