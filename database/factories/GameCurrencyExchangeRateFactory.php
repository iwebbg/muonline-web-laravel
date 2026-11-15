<?php

namespace Database\Factories;

use App\Economy\NativeCurrency;
use App\Models\GameCurrencyExchangeRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GameCurrencyExchangeRate> */
final class GameCurrencyExchangeRateFactory extends Factory
{
    /**
     * Creates an isolated, valid integer exchange policy without colliding with seeded rates.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_profile_key' => 'fixture-'.fake()->unique()->uuid(), 'currency' => NativeCurrency::WCoinC,
            'credit_units' => 10, 'coin_units' => 5, 'minimum_units' => 1, 'maximum_units' => 1000,
            'enabled' => true, 'revision' => 1,
        ];
    }
}
