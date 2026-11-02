<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the administrator-managed character VIP plan catalog.
     */
    public function up(): void
    {
        Schema::create('vip_plans', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('name_en', 120);
            $table->string('name_bg', 120)->nullable();
            $table->text('description_en')->nullable();
            $table->text('description_bg')->nullable();
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedBigInteger('price_credits');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['enabled', 'sort_order']);
        });
    }

    /**
     * Removes the character VIP plan catalog.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_plans');
    }
};
