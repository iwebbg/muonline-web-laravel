<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates recoverable exact-byte player web storage records.
     */
    public function up(): void
    {
        $usesSqlServer = Schema::getConnection()->getDriverName() === 'sqlsrv';

        Schema::create('web_storage_items', function (Blueprint $table) use ($usesSqlServer): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_hash', 64);
            $table->string('account_id', 10)->index();
            $table->string('source_character_name', 10);
            $table->unsignedTinyInteger('source_vault');
            $table->unsignedSmallInteger('source_slot');
            $table->binary('item_binary');
            $table->char('item_fingerprint', 64)->index();
            $escrowKey = $table->char('escrow_key', 64)->nullable();

            if (! $usesSqlServer) {
                $escrowKey->unique();
            }
            $table->json('item_snapshot');
            $table->string('status', 24)->index();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->dateTime('last_sync_attempt_at')->nullable();
            $table->string('last_sync_error', 80)->nullable();
            $table->dateTime('stored_at')->nullable();
            $table->dateTime('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'status']);
        });

        if ($usesSqlServer) {
            DB::statement(
                'CREATE UNIQUE INDEX web_storage_items_escrow_key_unique '
                .'ON web_storage_items (escrow_key) WHERE escrow_key IS NOT NULL',
            );
        }
    }

    /**
     * Removes player web storage records.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_storage_items');
    }
};
