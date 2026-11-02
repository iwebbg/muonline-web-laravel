<?php

namespace Database\Factories;

use App\Models\VoteClaim;
use App\Models\VoteSite;
use App\VoteRewards\VoteClaimStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * Builds immutable vote-claim audit records for tests.
 *
 * @extends Factory<VoteClaim>
 */
class VoteClaimFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => (string) Str::uuid(),
            'vote_site_id' => VoteSite::factory(),
            'account_id' => fake()->regexify('[a-z0-9]{8}'),
            'ip_hash' => hash('sha256', fake()->ipv4()),
            'reward_credits' => 10,
            'reward_zen' => 1_000_000,
            'status' => VoteClaimStatus::Pending,
            'wallet_transaction_id' => null,
            'zen_balance_before' => null,
            'zen_balance_after' => null,
            'reviewed_by_administrator_id' => null,
            'rejection_reason' => null,
            'initiated_at' => now(),
            'expires_at' => now()->addDay(),
            'reviewed_at' => null,
            'rewarded_at' => null,
        ];
    }
}
