<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Adds auction policy, leading bid and settlement state to escrow listings.
     */
    public function up(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->string('sale_type', 16)->default('fixed')->index()->after('price_credits');
            $table->unsignedBigInteger('minimum_bid_credits')->nullable()->after('sale_type');
            $table->unsignedBigInteger('bid_increment_credits')->nullable()->after('minimum_bid_credits');
            $table->unsignedBigInteger('highest_bid_credits')->nullable()->after('bid_increment_credits');
            $table->string('highest_bidder_account_id', 10)->nullable()->index()->after('highest_bid_credits');
            $table->foreignId('highest_bid_id')->nullable()->after('highest_bidder_account_id')
                ->constrained('auction_bids')->nullOnDelete();
            $table->dateTime('settled_at')->nullable()->after('delivered_at');
        });
    }

    /**
     * Removes auction state from escrow listings.
     */
    public function down(): void
    {
        Schema::table('market_listings', function (Blueprint $table) {
            $table->dropConstrainedForeignId('highest_bid_id');
            $table->dropColumn([
                'sale_type',
                'minimum_bid_credits',
                'bid_increment_credits',
                'highest_bid_credits',
                'highest_bidder_account_id',
                'settled_at',
            ]);
        });
    }
};
