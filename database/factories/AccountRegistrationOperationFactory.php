<?php

namespace Database\Factories;

use App\AccountRegistration\AccountRegistrationStatus;
use App\Models\AccountRegistrationOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountRegistrationOperation>
 */
final class AccountRegistrationOperationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'status' => AccountRegistrationStatus::Pending,
            'settings_snapshot' => [
                'game_profile' => '97d + 99i',
                'password_scheme' => 'plaintext',
                'required_tables' => [
                    'MEMB_INFO',
                    'VI_CURR_INFO',
                ],
            ],
        ];
    }
}
