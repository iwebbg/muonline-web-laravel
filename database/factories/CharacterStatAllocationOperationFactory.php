<?php

namespace Database\Factories;

use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterStatAllocationOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterStatAllocationOperation>
 */
final class CharacterStatAllocationOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => fake()->uuid(),
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'character_name' => fake()->regexify('[A-Za-z0-9]{10}'),
            'status' => CharacterOperationStatus::Pending,
            'allocation_snapshot' => [
                'strength' => 10,
                'agility' => 0,
                'vitality' => 0,
                'energy' => 0,
            ],
            'rule_snapshot' => [
                'maximum_stat' => 32_767,
            ],
        ];
    }
}
