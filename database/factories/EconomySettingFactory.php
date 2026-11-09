<?php

namespace Database\Factories;

use App\Models\EconomySetting;
use App\Shop\EconomySettings;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EconomySetting>
 */
class EconomySettingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            ...EconomySettings::defaults()->toPersistenceArray(),
            'revision' => 1,
            'updated_by_administrator_id' => null,
        ];
    }
}
