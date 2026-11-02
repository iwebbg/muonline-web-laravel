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
        Schema::create('download_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('download_id')
                ->constrained('downloads')
                ->cascadeOnDelete();
            $table->foreignId('administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('action', 32)->index();
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['download_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('download_revisions');
    }
};
