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
        Schema::table('character_service_operations', function (Blueprint $table) {
            $table->unsignedInteger('requested_amount')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('character_service_operations', function (Blueprint $table) {
            $table->dropColumn('requested_amount');
        });
    }
};
