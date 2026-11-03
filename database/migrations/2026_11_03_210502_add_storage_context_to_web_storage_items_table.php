<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds explicit module ownership and classifies existing Jewel Bank custody.
     */
    public function up(): void
    {
        Schema::table('web_storage_items', function (Blueprint $table): void {
            $table->string('storage_context', 24)
                ->default('web_vault')
                ->after('account_id');
        });

        $supportedJewelCodes = [
            '12:15',
            '14:13',
            '14:14',
            '14:16',
            '14:22',
        ];
        $storedItems = DB::table('web_storage_items')
            ->select(['id', 'item_snapshot'])
            ->orderBy('id')
            ->get();

        foreach ($storedItems as $storedItem) {
            $snapshot = json_decode((string) $storedItem->item_snapshot, true);
            $itemCode = is_array($snapshot) ? ($snapshot['code'] ?? null) : null;

            if (! is_string($itemCode) || ! in_array($itemCode, $supportedJewelCodes, true)) {
                continue;
            }

            DB::table('web_storage_items')
                ->where('id', $storedItem->id)
                ->update(['storage_context' => 'jewel_bank']);
        }
    }

    /**
     * Removes explicit module ownership from exact-item custody.
     */
    public function down(): void
    {
        Schema::table('web_storage_items', function (Blueprint $table): void {
            $table->dropColumn('storage_context');
        });
    }
};
