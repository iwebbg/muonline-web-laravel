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
        Schema::create('shop_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('type', 32);
            $table->string('class_scope', 32)->default('all');
            $table->string('title_en', 120);
            $table->string('title_bg', 120);
            $table->text('description_en')->nullable();
            $table->text('description_bg')->nullable();
            $table->unsignedBigInteger('price_credits');
            $table->string('status', 32)->default('draft');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->timestamps();

            $table->index(['type', 'status', 'sort_order']);
            $table->index(['status', 'available_from', 'available_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shop_offers');
    }
};
