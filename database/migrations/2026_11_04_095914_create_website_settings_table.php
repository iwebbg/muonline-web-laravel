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
        Schema::create('website_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('brand_title', 80)->nullable();
            $table->string('brand_subtitle', 120)->nullable();
            $table->string('logo_path', 2048)->nullable();
            $table->string('home_title_en', 160)->nullable();
            $table->string('home_title_bg', 160)->nullable();
            $table->string('page_title_suffix', 120)->nullable();
            $table->string('meta_description_en', 320)->nullable();
            $table->string('meta_description_bg', 320)->nullable();
            $table->string('meta_keywords_en', 500)->nullable();
            $table->string('meta_keywords_bg', 500)->nullable();
            $table->string('og_image_path', 2048)->nullable();
            $table->string('copyright_holder', 160)->nullable();
            $table->unsignedInteger('revision')->default(0);
            $table->foreignId('updated_by_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_settings');
    }
};
