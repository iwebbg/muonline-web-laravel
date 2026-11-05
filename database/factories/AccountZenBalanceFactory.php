<?php

namespace Database\Factories;

use App\Models\AccountZenBalance;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountZenBalance>
 */
final class AccountZenBalanceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => fake()->unique()->regexify('[A-Za-z0-9]{10}'),
            'balance' => 0,
        ];
    }
}
