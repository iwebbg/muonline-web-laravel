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
        Schema::create('credit_exchange_offers', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('game_profile_key', 50)->index();
            $table->unsignedSmallInteger('item_group');
            $table->unsignedSmallInteger('item_index');
            $table->unsignedTinyInteger('level')->default(0);
            $table->unsignedTinyInteger('option_level')->default(0);
            $table->boolean('has_skill')->default(false);
            $table->boolean('has_luck')->default(false);
            $table->unsignedTinyInteger('excellent_mask')->default(0);
            $table->unsignedTinyInteger('ancient_option')->default(0);
            $table->string('item_name_snapshot', 120);
            $table->string('item_image_snapshot')->nullable();
            $table->unsignedTinyInteger('excellent_type')->default(0);
            $table->unsignedTinyInteger('option_type')->default(0);
            $table->unsignedBigInteger('credits_per_item');
            $table->unsignedSmallInteger('max_quantity_per_exchange')->default(10);
            $table->unsignedBigInteger('daily_account_credit_limit')->default(200);
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->index(['game_profile_key', 'enabled', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('credit_exchange_offers');
    }
};
