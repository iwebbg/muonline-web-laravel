<?php

namespace Database\Factories;

use App\Models\AuctionBid;
use App\Models\MarketListing;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuctionBid>
 */
final class AuctionBidFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_listing_id' => MarketListing::factory(),
            'bidder_account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'amount_credits' => fake()->numberBetween(100, 10_000),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'hold_wallet_transaction_id' => WalletTransaction::factory(),
            'release_wallet_transaction_id' => null,
            'outbid_at' => null,
        ];
    }
}
