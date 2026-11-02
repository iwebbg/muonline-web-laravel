<?php

namespace Database\Factories;

use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Models\AccountWallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WalletTransaction>
 */
class WalletTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $balanceBefore = fake()->numberBetween(0, 1000);
        $amount = fake()->numberBetween(1, 100);
        $accountId = fake()->regexify('[A-Za-z0-9]{10}');

        return [
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'account_wallet_id' => AccountWallet::factory()->state([
                'account_id' => $accountId,
                'currency' => WalletCurrency::Credits,
                'balance' => $balanceBefore + $amount,
            ]),
            'account_id' => $accountId,
            'currency' => WalletCurrency::Credits,
            'type' => WalletTransactionType::AdminCredit,
            'amount' => $amount,
            'balance_before' => $balanceBefore,
            'balance_after' => $balanceBefore + $amount,
            'reason' => fake()->sentence(),
            'metadata' => null,
            'performed_by_administrator_id' => null,
        ];
    }
}
