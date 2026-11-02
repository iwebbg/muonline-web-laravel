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
        Schema::create('reset_profiles', function (Blueprint $table) {
            $table->id();
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('required_level');
            $table->unsignedInteger('maximum_resets');
            $table->unsignedBigInteger('base_zen_cost');
            $table->boolean('multiply_cost_by_reset');
            $table->boolean('clear_stats');
            $table->string('points_mode', 32);
            $table->unsignedInteger('points_per_reset');
            $table->string('inventory_policy', 32);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reset_profiles');
    }
};
