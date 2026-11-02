<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates the current application-owned VIP entitlement per character.
     */
    public function up(): void
    {
        Schema::create('vip_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->string('account_id', 10);
            $table->string('character_name', 10);
            $table->foreignId('vip_plan_id')
                ->nullable()
                ->constrained('vip_plans')
                ->nullOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->string('sync_status', 24);
            $table->dateTime('game_synced_expires_at')->nullable();
            $table->dateTime('last_sync_attempt_at')->nullable();
            $table->unsignedInteger('sync_attempts')->default(0);
            $table->string('last_sync_error', 80)->nullable();
            $table->timestamps();

            $table->unique(['account_id', 'character_name']);
            $table->index(['sync_status', 'updated_at']);
            $table->index('expires_at');
        });
    }

    /**
     * Removes current character VIP entitlements.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_entitlements');
    }
};
