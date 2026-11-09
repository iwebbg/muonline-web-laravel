<?php

namespace Database\Factories;

use App\CreditEarning\CreditExchangeStatus;
use App\Models\CreditExchangeOffer;
use App\Models\CreditItemExchange;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditItemExchange>
 */
class CreditItemExchangeFactory extends Factory
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
            'account_id' => fake()->regexify('[A-Za-z0-9]{8}'),
            'character_name' => fake()->regexify('[A-Za-z]{8}'),
            'credit_exchange_offer_id' => CreditExchangeOffer::factory(),
            'quantity' => 1,
            'credits_awarded' => 50,
            'offer_snapshot' => ['item_name' => 'Leather Pants'],
            'items_snapshot' => [['slot' => 12, 'binary_hex' => str_repeat('00', 10)]],
            'status' => CreditExchangeStatus::Pending,
            'wallet_transaction_id' => null,
            'failure_code' => null,
            'completed_at' => null,
        ];
    }
}
