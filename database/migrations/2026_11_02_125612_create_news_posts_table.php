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
        Schema::create('news_posts', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('title_en', 160);
            $table->string('title_bg', 160)->nullable();
            $table->string('excerpt_en', 300);
            $table->string('excerpt_bg', 300)->nullable();
            $table->text('body_en');
            $table->text('body_bg')->nullable();
            $table->string('status', 20)->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->foreignId('created_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->noActionOnDelete();
            $table->foreignId('updated_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->noActionOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_posts');
    }
};
