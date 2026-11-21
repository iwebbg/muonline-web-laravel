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
        Schema::table('shop_offer_items', function (Blueprint $table) {
            $table->unsignedTinyInteger('harmony_option')->default(0);
            $table->unsignedTinyInteger('socket_count')->default(0);
            $table->boolean('has_380_option')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('shop_offer_items', function (Blueprint $table) {
            $table->dropColumn(['harmony_option', 'socket_count', 'has_380_option']);
        });
    }
};
