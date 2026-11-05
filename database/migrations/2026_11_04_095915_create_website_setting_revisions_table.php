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
        Schema::create('website_setting_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('website_setting_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('revision');
            $table->json('before_snapshot');
            $table->json('after_snapshot');
            $table->foreignId('changed_by_administrator_id')->nullable()->constrained('administrators')->nullOnDelete();
            $table->timestamps();

            $table->unique(['website_setting_id', 'revision']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('website_setting_revisions');
    }
};
