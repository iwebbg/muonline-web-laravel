<?php

namespace Database\Factories;

use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Vip\VipSyncStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds current character VIP entitlements for tests.
 *
 * @extends Factory<VipEntitlement>
 */
final class VipEntitlementFactory extends Factory
{
    /**
     * Defines an active synchronized entitlement.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsAt = now();
        $expiresAt = $startsAt->copy()->addDays(30);

        return [
            'account_id' => 'testacc',
            'character_name' => fake()->unique()->regexify('[A-Za-z]{8}'),
            'vip_plan_id' => VipPlan::factory(),
            'starts_at' => $startsAt,
            'expires_at' => $expiresAt,
            'sync_status' => VipSyncStatus::Synced,
            'game_synced_expires_at' => $expiresAt,
            'last_sync_attempt_at' => $startsAt,
            'sync_attempts' => 1,
            'last_sync_error' => null,
        ];
    }
}
