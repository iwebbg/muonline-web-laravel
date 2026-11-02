<?php

namespace Database\Factories;

use App\Models\AccountZenBalance;
use App\Models\ZenBankOperation;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ZenBankOperation>
 */
final class ZenBankOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = 1_000_000;

        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'account_id' => 'zenowner',
            'character_name' => 'ZenChar',
            'type' => ZenBankOperationType::Deposit,
            'amount' => $amount,
            'before_game_zen' => 10_000_000,
            'after_game_zen' => 9_000_000,
            'before_bank_balance' => 0,
            'after_bank_balance' => $amount,
            'status' => ZenBankOperationStatus::Completed,
            'sync_attempts' => 1,
            'last_sync_attempt_at' => now(),
            'last_sync_error' => null,
            'completed_at' => now(),
            'account_zen_balance_id' => AccountZenBalance::factory(),
        ];
    }
}
