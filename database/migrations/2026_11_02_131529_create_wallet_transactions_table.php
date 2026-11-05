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
        Schema::create('wallet_transactions', function (Blueprint $table): void {
            $table->id();
            $table->string('idempotency_key', 64)->unique();
            $table->char('request_hash', 64);
            $table->foreignId('account_wallet_id')
                ->constrained('account_wallets')
                ->noActionOnDelete();
            $table->string('account_id', 10)->index();
            $table->string('currency', 32)->index();
            $table->string('type', 32)->index();
            $table->bigInteger('amount');
            $table->unsignedBigInteger('balance_before');
            $table->unsignedBigInteger('balance_after');
            $table->string('reason', 255);
            $table->json('metadata')->nullable();
            $table->foreignId('performed_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['account_wallet_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('wallet_transactions');
    }
};
