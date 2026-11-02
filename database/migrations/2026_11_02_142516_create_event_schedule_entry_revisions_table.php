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
        Schema::create('event_schedule_entry_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_schedule_entry_id')
                ->constrained()
                ->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->string('action', 20);
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot');
            $table->foreignId('changed_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->unique(['event_schedule_entry_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_schedule_entry_revisions');
    }
};
