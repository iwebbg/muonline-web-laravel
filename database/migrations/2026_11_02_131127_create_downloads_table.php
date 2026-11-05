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
        Schema::create('downloads', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 120)->unique();
            $table->string('category', 20)->index();
            $table->string('title_en', 160);
            $table->string('title_bg', 160)->nullable();
            $table->text('description_en');
            $table->text('description_bg')->nullable();
            $table->string('version', 80)->nullable();
            $table->string('file_size', 40)->nullable();
            $table->string('download_url', 2048);
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('sort_order')->default(0)->index();
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

            $table->index(['status', 'published_at', 'sort_order']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('downloads');
    }
};
