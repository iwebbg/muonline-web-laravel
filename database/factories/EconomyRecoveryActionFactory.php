<?php

namespace Database\Factories;

use App\Models\Administrator;
use App\Models\EconomyRecoveryAction;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EconomyRecoveryAction>
 */
final class EconomyRecoveryActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'administrator_id' => Administrator::factory(),
            'subject_type' => 'item_custody',
            'subject_public_id' => (string) Str::uuid(),
            'account_id' => 'recover01',
            'action' => 'retry_sync',
            'before_status' => 'storing',
            'after_status' => 'available',
            'outcome' => 'completed',
            'failure_code' => null,
            'context_snapshot' => ['sync_attempts' => 2],
        ];
    }
}
