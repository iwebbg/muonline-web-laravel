<?php

namespace Database\Factories;

use App\Models\GameCurrencyExchangeRate;
use App\Models\GameCurrencyExchangeRateRevision;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameCurrencyExchangeRateRevision> */
final class GameCurrencyExchangeRateRevisionFactory extends Factory
{
    /**
     * Creates an attributed rate revision with explicit before/after integer terms.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_currency_exchange_rate_id' => GameCurrencyExchangeRate::factory(),
            'administrator_id' => null, 'revision' => 2,
            'before_snapshot' => ['credit_units' => 10, 'coin_units' => 5],
            'snapshot' => ['credit_units' => 20, 'coin_units' => 5], 'created_at' => now(),
        ];
    }
}
