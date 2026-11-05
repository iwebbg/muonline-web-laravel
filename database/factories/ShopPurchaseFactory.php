<?php

namespace Database\Factories;

use App\Models\ShopOffer;
use App\Models\ShopPurchase;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopPurchase>
 */
class ShopPurchaseFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'shop_offer_id' => ShopOffer::factory(),
            'offer_snapshot' => ['title_en' => 'Test offer', 'items' => []],
            'price_credits' => 100,
            'wallet_transaction_id' => WalletTransaction::factory(),
        ];
    }
}
