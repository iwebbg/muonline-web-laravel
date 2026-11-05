<?php

namespace Database\Factories;

use App\Models\CreditPackage;
use App\Models\CreditPayment;
use App\Payments\CreditPaymentStatus;
use App\Payments\PaymentProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditPayment>
 */
class CreditPaymentFactory extends Factory
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
            'idempotency_key' => (string) Str::uuid(),
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'credit_package_id' => CreditPackage::factory(),
            'package_snapshot' => ['title_en' => 'Starter Credits', 'title_bg' => 'Стартови Credits', 'credits' => 500, 'price_minor' => 500, 'currency' => 'EUR'],
            'provider' => PaymentProvider::Stripe,
            'status' => CreditPaymentStatus::Pending,
            'credits' => 500,
            'amount_minor' => 500,
            'currency' => 'EUR',
        ];
    }
}
