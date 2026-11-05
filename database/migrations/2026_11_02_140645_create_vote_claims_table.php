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
        Schema::create('vote_claims', function (Blueprint $table) {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->string('idempotency_key', 64)->unique();
            $table->foreignId('vote_site_id')
                ->constrained('vote_sites')
                ->noActionOnDelete();
            $table->string('account_id', 10);
            $table->char('ip_hash', 64);
            $table->unsignedBigInteger('reward_credits');
            $table->unsignedBigInteger('reward_zen');
            $table->string('status', 24);
            $table->foreignId('wallet_transaction_id')
                ->nullable()
                ->constrained('wallet_transactions')
                ->noActionOnDelete();
            $table->unsignedBigInteger('zen_balance_before')->nullable();
            $table->unsignedBigInteger('zen_balance_after')->nullable();
            $table->foreignId('reviewed_by_administrator_id')
                ->nullable()
                ->constrained('administrators')
                ->nullOnDelete();
            $table->string('rejection_reason', 255)->nullable();
            $table->dateTime('initiated_at');
            $table->dateTime('expires_at');
            $table->dateTime('reviewed_at')->nullable();
            $table->dateTime('rewarded_at')->nullable();
            $table->timestamps();

            $table->index(['account_id', 'vote_site_id', 'status'], 'vote_claim_account_site_status');
            $table->index(['vote_site_id', 'ip_hash', 'status'], 'vote_claim_site_ip_status');
            $table->index(['status', 'expires_at']);
            $table->index('rewarded_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('vote_claims');
    }
};
