<?php

namespace Database\Factories;

use App\Game\Operations\CharacterOperationStatus;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ItemGrantOperation>
 */
class ItemGrantOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'idempotency_key' => (string) Str::uuid(),
            'request_hash' => hash('sha256', fake()->uuid()),
            'administrator_id' => Administrator::factory(),
            'account_id' => fake()->unique()->regexify('[A-Za-z0-9]{10}'),
            'item_code' => '0:0',
            'item_name' => 'Kris',
            'serial' => fake()->numberBetween(1, 2_000_000_000),
            'item_binary' => hex2bin('0000FF00000000000000'),
            'status' => CharacterOperationStatus::Completed,
            'reason' => fake()->sentence(),
            'context_snapshot' => ['source' => 'factory'],
            'before_snapshot' => ['delivered' => false],
            'after_snapshot' => ['delivered' => true],
            'failure_code' => null,
            'completed_at' => now(),
        ];
    }
}
