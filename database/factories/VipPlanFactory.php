<?php

namespace Database\Factories;

use App\Models\VipPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds administrator-managed VIP plans for tests.
 *
 * @extends Factory<VipPlan>
 */
final class VipPlanFactory extends Factory
{
    /**
     * Defines a valid enabled character VIP plan.
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
            'description_en' => fake()->sentence(),
            'description_bg' => null,
            'duration_days' => 30,
            'price_credits' => 1000,
            'enabled' => true,
            'sort_order' => 0,
        ];
    }
}
