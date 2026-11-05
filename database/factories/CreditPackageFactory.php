<?php

namespace Database\Factories;

use App\Models\CreditPackage;
use App\Payments\CreditPackageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditPackage>
 */
class CreditPackageFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'title_en' => 'Starter Credits',
            'title_bg' => 'Стартови Credits',
            'credits' => 500,
            'price_minor' => 500,
            'currency' => 'EUR',
            'status' => CreditPackageStatus::Published,
            'sort_order' => 0,
        ];
    }
}
