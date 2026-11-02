<?php

namespace Database\Factories;

use App\Models\ResetProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResetProfile>
 */
class ResetProfileFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $settings = ResetProfileSettings::defaults();

        return [
            ...$settings->toPersistenceArray(),
            'revision' => 1,
            'updated_by_administrator_id' => null,
        ];
    }
}
