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
        Schema::create('event_schedule_entries', function (Blueprint $table) {
            $table->id();
            $table->string('profile_key', 80);
            $table->string('game_version', 80);
            $table->string('event_key', 80);
            $table->string('name_en', 120);
            $table->string('name_bg', 120)->nullable();
            $table->string('location_en', 120);
            $table->string('location_bg', 120)->nullable();
            $table->string('accent', 20)->default('crimson');
            $table->boolean('enabled')->default(true);
            $table->unsignedSmallInteger('duration_minutes')->default(0);
            $table->string('schedule_mode', 20);
            $table->json('times');
            $table->json('days_of_week');
            $table->unsignedSmallInteger('interval_minutes')->nullable();
            $table->string('interval_anchor', 5)->default('00:00');
            $table->text('source')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->unsignedInteger('revision')->default(1);
            $table->foreignId('updated_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->timestamps();

            $table->unique(['profile_key', 'game_version', 'event_key']);
            $table->index(['profile_key', 'game_version', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_schedule_entries');
    }
};
