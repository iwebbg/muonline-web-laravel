<?php

namespace Database\Factories;

use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds recoverable market escrow listings for tests.
 *
 * @extends Factory<MarketListing>
 */
final class MarketListingFactory extends Factory
{
    /**
     * Defines one active market listing with a valid 10-byte item blob.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $serial = pack('N', fake()->unique()->numberBetween(1, 2_000_000_000));
        $binary = "\x00\x00\x20".$serial."\x00\x00\x00";

        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'purchase_idempotency_key' => null,
            'request_hash' => hash('sha256', fake()->uuid()),
            'purchase_request_hash' => null,
            'seller_account_id' => 'seller01',
            'buyer_account_id' => null,
            'source_character_name' => 'SellerChar',
            'source_vault' => 0,
            'source_slot' => 0,
            'item_binary' => $binary,
            'item_fingerprint' => hash('sha256', $binary),
            'escrow_key' => hash('sha256', $binary),
            'item_snapshot' => [
                'slot' => 0,
                'code' => '0:0',
                'name' => 'Kris',
                'width' => 1,
                'height' => 2,
                'image' => '/images/items/97d_99i/0000.gif',
                'level' => 0,
                'rarity' => 'normal',
            ],
            'price_credits' => 100,
            'sale_type' => MarketSaleType::Fixed,
            'minimum_bid_credits' => null,
            'bid_increment_credits' => null,
            'highest_bid_credits' => null,
            'highest_bidder_account_id' => null,
            'highest_bid_id' => null,
            'buyer_wallet_transaction_id' => null,
            'seller_wallet_transaction_id' => null,
            'status' => MarketListingStatus::Active,
            'sync_attempts' => 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => null,
            'activated_at' => now(),
            'purchased_at' => null,
            'delivered_at' => null,
            'settled_at' => null,
            'returned_at' => null,
            'expires_at' => now()->addDays(30),
        ];
    }
}
