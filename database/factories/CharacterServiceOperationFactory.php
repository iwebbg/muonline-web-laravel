<?php

namespace Database\Factories;

use App\CharacterServices\CharacterServiceType;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterServiceOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CharacterServiceOperation>
 */
final class CharacterServiceOperationFactory extends Factory
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
            'request_hash' => hash('sha256', fake()->uuid()),
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'service_type' => CharacterServiceType::Rename,
            'character_name' => fake()->regexify('[A-Za-z0-9]{10}'),
            'requested_name' => fake()->regexify('[A-Za-z0-9]{10}'),
            'requested_amount' => null,
            'status' => CharacterOperationStatus::Pending,
            'price_credits' => 100,
            'settings_snapshot' => [],
        ];
    }
}
