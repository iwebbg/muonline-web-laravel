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
        Schema::create('game_servers', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('name', 160);
            $table->string('version', 80);
            $table->string('experience', 40);
            $table->string('drop', 40);
            $table->unsignedInteger('capacity')->default(500);
            $table->unsignedInteger('online_count')->default(0);
            $table->boolean('is_online')->default(false);
            $table->boolean('is_current')->default(false)->index();
            $table->boolean('is_visible')->default(true)->index();
            $table->string('website_url', 2048)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();

            $table->index(['is_visible', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('game_servers');
    }
};
