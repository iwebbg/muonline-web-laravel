<?php

namespace Database\Factories;

use App\Models\OnlineTimeAccount;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OnlineTimeAccount>
 */
class OnlineTimeAccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => fake()->unique()->regexify('[A-Za-z0-9]{8}'),
            'reward_date' => today(),
            'rewarded_seconds_today' => 0,
            'rewarded_credits_today' => 0,
            'remainder_seconds' => 0,
            'claimable_credits' => 0,
            'lifetime_seconds' => 0,
        ];
    }
}
