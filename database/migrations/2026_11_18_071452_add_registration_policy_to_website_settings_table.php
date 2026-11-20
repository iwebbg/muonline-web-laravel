<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds global registration and email activation policies with reviewed defaults.
     */
    public function up(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->boolean('registration_enabled')->default(true);
            $table->boolean('require_email_verification')->default(false);
        });
    }

    /**
     * Removes the registration policy columns without modifying game accounts.
     */
    public function down(): void
    {
        Schema::table('website_settings', function (Blueprint $table): void {
            $table->dropColumn(['registration_enabled', 'require_email_verification']);
        });
    }
};
