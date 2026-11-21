<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds the selected update-safe public theme to website settings.
     */
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->string('theme_slug', 120)->default('muonline')->after('copyright_holder');
        });
    }

    /**
     * Removes the public theme selection from website settings.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->dropColumn('theme_slug');
        });
    }
};
