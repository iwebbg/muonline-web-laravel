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
        Schema::create('economy_settings', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('item_generator_markup_percentage')->default(0);
            $table->boolean('character_rename_enabled')->default(false);
            $table->unsignedBigInteger('character_rename_price_credits')->default(0);
            $table->boolean('character_class_change_enabled')->default(false);
            $table->unsignedBigInteger('character_class_change_price_credits')->default(0);
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
        Schema::dropIfExists('economy_settings');
    }
};
