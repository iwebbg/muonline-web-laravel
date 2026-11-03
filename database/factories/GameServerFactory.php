<?php

namespace Database\Factories;

use App\Models\GameServer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Generates public server-directory records for tests and local fixtures.
 *
 * @extends Factory<GameServer>
 */
class GameServerFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->company().' MuOnline',
            'version' => '97d + 99i',
            'experience' => '9999x',
            'drop' => '60%',
            'capacity' => 500,
            'show_online_status' => true,
            'show_online_users' => true,
            'is_current' => false,
            'is_visible' => true,
            'website_url' => 'https://'.fake()->unique()->domainName(),
            'sort_order' => 10,
        ];
    }

    /**
     * Marks the generated destination as the server powered by this website.
     */
    public function current(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_current' => true,
            'website_url' => null,
            'sort_order' => 0,
        ]);
    }
}
