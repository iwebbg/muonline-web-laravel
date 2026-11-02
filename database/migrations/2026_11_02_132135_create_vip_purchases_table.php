<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates immutable purchase history linking VIP time to its wallet debit.
     */
    public function up(): void
    {
        Schema::create('vip_purchases', function (Blueprint $table): void {
            $table->id();
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->foreignId('vip_entitlement_id')
                ->constrained('vip_entitlements')
                ->restrictOnDelete();
            $table->foreignId('vip_plan_id')
                ->constrained('vip_plans')
                ->restrictOnDelete();
            $table->foreignId('wallet_transaction_id')
                ->unique()
                ->constrained('wallet_transactions')
                ->restrictOnDelete();
            $table->string('account_id', 10);
            $table->string('character_name', 10);
            $table->string('plan_name', 120);
            $table->unsignedSmallInteger('duration_days');
            $table->unsignedBigInteger('price_credits');
            $table->dateTime('previous_expires_at')->nullable();
            $table->dateTime('starts_at');
            $table->dateTime('expires_at');
            $table->timestamps();

            $table->index(['account_id', 'created_at']);
            $table->index(['account_id', 'character_name', 'expires_at']);
        });
    }

    /**
     * Removes immutable character VIP purchase history.
     */
    public function down(): void
    {
        Schema::dropIfExists('vip_purchases');
    }
};
