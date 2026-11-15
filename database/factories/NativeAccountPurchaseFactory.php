<?php

namespace Database\Factories;

use App\Economy\NativeAccountMutation;
use App\Economy\NativeCurrency;
use App\Economy\NativePurchaseKind;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\NativeAccountPurchase;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<NativeAccountPurchase> */
final class NativeAccountPurchaseFactory extends Factory
{
    /**
     * Creates one pending native coin purchase with complete immutable grant terms.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => fake()->uuid(), 'request_hash' => hash('sha256', fake()->uuid()),
            'game_profile_key' => 'mudevs_season_21', 'account_id' => 'owner',
            'kind' => NativePurchaseKind::CurrencyExchange, 'product_id' => 1,
            'character_name' => null, 'units' => 10, 'credit_cost' => 10,
            'status' => CharacterOperationStatus::Pending,
            'mutation_snapshot' => (new NativeAccountMutation(NativePurchaseKind::CurrencyExchange, NativeCurrency::WCoinC, 10))->toArray(),
            'settings_snapshot' => ['credit_units' => 1, 'coin_units' => 1, 'revision' => 1],
        ];
    }
}
