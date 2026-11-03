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
        Schema::table('game_servers', function (Blueprint $table): void {
            $table->boolean('show_online_status')->default(true)->after('capacity');
            $table->boolean('show_online_users')->default(true)->after('show_online_status');
            $table->dropColumn(['online_count', 'is_online']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('game_servers', function (Blueprint $table): void {
            $table->unsignedInteger('online_count')->default(0)->after('capacity');
            $table->boolean('is_online')->default(false)->after('online_count');
            $table->dropColumn(['show_online_status', 'show_online_users']);
        });
    }
};
