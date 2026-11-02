<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds atomic purchase ownership, ledger and delivery state to market escrow.
     */
    public function up(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->string('buyer_account_id', 10)->nullable()->index()->after('seller_account_id');
            $table->uuid('purchase_idempotency_key')->nullable()->unique()->after('idempotency_key');
            $table->char('purchase_request_hash', 64)->nullable()->after('request_hash');
            $table->unsignedBigInteger('buyer_wallet_transaction_id')->nullable()->after('price_credits');
            $table->unsignedBigInteger('seller_wallet_transaction_id')->nullable()->after('buyer_wallet_transaction_id');
            $table->dateTime('purchased_at')->nullable()->index()->after('activated_at');
            $table->dateTime('delivered_at')->nullable()->after('purchased_at');
        });
    }

    /**
     * Removes market purchase and delivery state.
     */
    public function down(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->dropColumn([
                'buyer_account_id',
                'purchase_idempotency_key',
                'purchase_request_hash',
                'buyer_wallet_transaction_id',
                'seller_wallet_transaction_id',
                'purchased_at',
                'delivered_at',
            ]);
        });
    }
};
