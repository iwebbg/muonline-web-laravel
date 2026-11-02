<?php

namespace Database\Factories;

use App\Economy\WalletTransactionType;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\VipPurchase;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds immutable character VIP purchase records for tests.
 *
 * @extends Factory<VipPurchase>
 */
final class VipPurchaseFactory extends Factory
{
    /**
     * Defines a purchase connected to an entitlement and wallet debit.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();
        $expiresAt = $startsAt->copy()->addDays(30);

        return [
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'vip_entitlement_id' => VipEntitlement::factory(),
            'vip_plan_id' => VipPlan::factory(),
            'wallet_transaction_id' => WalletTransaction::factory()->state([
                'type' => WalletTransactionType::Purchase,
                'amount' => -1000,
            ]),
            'account_id' => 'testacc',
            'character_name' => fake()->regexify('[A-Za-z]{8}'),
            'plan_name' => 'Thirty Days',
            'duration_days' => 30,
            'price_credits' => 1000,
            'previous_expires_at' => null,
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
        ];
    }
}
