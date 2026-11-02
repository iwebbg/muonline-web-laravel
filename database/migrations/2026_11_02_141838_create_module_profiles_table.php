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
        Schema::create('module_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('profile_key', 80);
            $table->string('game_version', 80);
            $table->json('modules');
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['profile_key', 'game_version']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('module_profiles');
    }
};
