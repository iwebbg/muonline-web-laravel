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
        Schema::create('shop_offer_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shop_offer_id')->constrained()->cascadeOnDelete();
            $table->string('item_code', 8);
            $table->string('item_name', 120);
            $table->string('item_image')->nullable();
            $table->unsignedTinyInteger('item_width');
            $table->unsignedTinyInteger('item_height');
            $table->unsignedTinyInteger('level')->default(0);
            $table->unsignedTinyInteger('durability')->default(255);
            $table->unsignedTinyInteger('option_level')->default(0);
            $table->boolean('has_skill')->default(false);
            $table->boolean('has_luck')->default(false);
            $table->unsignedTinyInteger('excellent_mask')->default(0);
            $table->unsignedTinyInteger('ancient_option')->default(0);
            $table->unsignedInteger('weight')->default(1);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['shop_offer_id', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_offer_items');
    }
};
