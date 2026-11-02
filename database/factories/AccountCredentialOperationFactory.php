<?php

namespace Database\Factories;

use App\AccountCredentials\AccountCredentialStatus;
use App\Models\AccountCredentialOperation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AccountCredentialOperation>
 */
final class AccountCredentialOperationFactory extends Factory
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
            'status' => AccountCredentialStatus::Pending,
            'settings_snapshot' => [
                'game_profile' => '97d + 99i',
                'password_scheme' => 'plaintext',
                'table' => 'MEMB_INFO',
            ],
        ];
    }
}
