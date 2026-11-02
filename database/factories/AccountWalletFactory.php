<?php

namespace Database\Factories;

use App\Economy\WalletCurrency;
use App\Models\AccountWallet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountWallet>
 */
class AccountWalletFactory extends Factory
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
            'currency' => WalletCurrency::Credits,
            'balance' => 0,
        ];
    }
}
