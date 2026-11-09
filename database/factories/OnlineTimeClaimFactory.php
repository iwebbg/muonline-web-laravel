<?php

namespace Database\Factories;

use App\Models\OnlineTimeAccount;
use App\Models\OnlineTimeClaim;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<OnlineTimeClaim>
 */
class OnlineTimeClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountId = fake()->unique()->regexify('[A-Za-z0-9]{8}');

        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', $accountId),
            'account_id' => OnlineTimeAccount::factory()->state(['account_id' => $accountId]),
            'credits_claimed' => 5,
            'wallet_transaction_id' => WalletTransaction::factory(),
            'completed_at' => now(),
        ];
    }
}
