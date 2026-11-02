<?php

namespace Database\Factories;

use App\Models\ResetProfile;
use App\Models\ResetProfileRevision;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ResetProfileRevision>
 */
class ResetProfileRevisionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $settings = ResetProfileSettings::defaults()->toClientArray();

        return [
            'reset_profile_id' => ResetProfile::factory(),
            'revision' => 1,
            'before_snapshot' => $settings,
            'after_snapshot' => $settings,
            'changed_by_administrator_id' => null,
        ];
    }
}
