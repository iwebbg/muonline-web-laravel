<?php

namespace Database\Factories;

use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterPkClearOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterPkClearOperation>
 */
final class CharacterPkClearOperationFactory extends Factory
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
            'rule_snapshot' => [],
        ];
    }
}
