<?php

namespace Database\Factories;

use App\Models\SocialLink;
use App\Website\SocialPlatform;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SocialLink>
 */
class SocialLinkFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'type' => SocialPlatform::Youtube,
            'label' => fake()->words(2, true),
            'url' => 'https://www.youtube.com/@'.fake()->unique()->userName(),
            'sort_order' => 0,
        ];
    }
}
