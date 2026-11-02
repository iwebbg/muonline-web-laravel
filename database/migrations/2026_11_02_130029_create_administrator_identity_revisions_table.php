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
        Schema::create('administrator_identity_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('target_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->foreignId('changed_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('action', 32)->index();
            $table->json('before_snapshot')->nullable();
            $table->json('after_snapshot');
            $table->timestamps();

            $table->index(['target_administrator_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('administrator_identity_revisions');
    }
};
