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
        Schema::create('news_post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_post_id')
                ->constrained('news_posts')
                ->cascadeOnDelete();
            $table->foreignId('administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('action', 32)->index();
            $table->json('snapshot');
            $table->timestamps();

            $table->index(['news_post_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_post_revisions');
    }
};
