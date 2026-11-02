<?php

namespace Database\Factories;

use App\Models\VoteSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds administrator-managed vote destinations for tests.
 *
 * @extends Factory<VoteSite>
 */
class VoteSiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'slug' => fake()->unique()->slug(2),
            'name_en' => ucfirst($name),
            'name_bg' => null,
            'url' => fake()->url(),
            'image_url' => null,
            'cooldown_hours' => 12,
            'reward_credits' => 10,
            'reward_zen' => 1_000_000,
            'enabled' => true,
            'sort_order' => 0,
        ];
    }
}
