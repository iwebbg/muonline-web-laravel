<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Clears the former application-name fallback when a distinct title suffix exists.
     */
    public function up(): void
    {
        $legacyFallbacks = array_unique([
            (string) config('app.name', 'MuOnline Web'),
            'MuOnline Web',
        ]);

        DB::table('website_settings')
            ->whereIn('copyright_holder', $legacyFallbacks)
            ->whereColumn('copyright_holder', '!=', 'page_title_suffix')
            ->update(['copyright_holder' => null]);
    }

    /**
     * Leaves normalized values untouched because intentional nulls are indistinguishable.
     */
    public function down(): void
    {
        // This one-time data normalization cannot be reversed without overwriting valid null overrides.
    }
};
