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
        Schema::table('web_storage_items', function (Blueprint $table): void {
            $table->string('source_location', 16)
                ->default('warehouse')
                ->after('source_character_name');
            $table->string('withdrawal_location', 16)
                ->nullable()
                ->after('source_slot');
            $table->string('withdrawal_character_name', 10)
                ->nullable()
                ->after('withdrawal_location');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('web_storage_items', function (Blueprint $table): void {
            $table->dropColumn([
                'source_location',
                'withdrawal_location',
                'withdrawal_character_name',
            ]);
        });
    }
};
