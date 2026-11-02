<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Creates immutable auction bid and hold-release history.
     */
    public function up(): void
    {
        Schema::create('auction_bids', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_listing_id')->constrained()->restrictOnDelete();
            $table->string('bidder_account_id', 10)->index();
            $table->unsignedBigInteger('amount_credits');
            $table->uuid('idempotency_key')->unique();
            $table->char('request_hash', 64);
            $table->unsignedBigInteger('hold_wallet_transaction_id');
            $table->unsignedBigInteger('release_wallet_transaction_id')->nullable();
            $table->dateTime('outbid_at')->nullable();
            $table->timestamps();

            $table->index(['market_listing_id', 'amount_credits']);
        });
    }

    /**
     * Removes auction bid and hold-release history.
     */
    public function down(): void
    {
        Schema::dropIfExists('auction_bids');
    }
};
