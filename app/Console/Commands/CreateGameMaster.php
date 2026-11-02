<?php

namespace App\Console\Commands;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/**
 * Creates a separate Game Master identity with only allow-listed permissions.
 */
#[Signature('admin:create-game-master {--permission=* : Allow-listed permission value granted to the Game Master}')]
#[Description('Create a strongly hashed Game Master with granular permissions')]
final class CreateGameMaster extends Command
{
    /**
     * Prompts for identity details while keeping the password out of shell history.
     */
    public function handle(): int
    {
        $name = (string) $this->ask('Game Master name');
        $email = (string) $this->ask('Game Master email');
        $password = (string) $this->secret('Password');
        $passwordConfirmation = (string) $this->secret('Confirm password');
        $permissions = array_values(array_unique(array_map(
            static fn (mixed $permission): string => (string) $permission,
            (array) $this->option('permission'),
        )));
        $input = [
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'password_confirmation' => $passwordConfirmation,
            'permissions' => $permissions,
        ];
        $validator = Validator::make($input, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:administrators,email'],
            'password' => [
                'required',
                'confirmed',
                Password::min(12)
                    ->mixedCase()
                    ->letters()
                    ->numbers()
                    ->symbols(),
            ],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['required', Rule::enum(AdministratorPermission::class)],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $message) {
                $this->error($message);
            }

            $this->line('Available permissions: '.implode(', ', $this->permissionValues()));

            return self::FAILURE;
        }

        Administrator::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
            'is_active' => true,
            'role' => AdministratorRole::GameMaster,
            'permissions' => $permissions,
        ]);

        $this->info('Game Master created successfully.');

        return self::SUCCESS;
    }

    /**
     * Returns every stable permission value accepted by the command option.
     *
     * @return list<string>
     */
    private function permissionValues(): array
    {
        return array_map(
            static fn (AdministratorPermission $permission): string => $permission->value,
            AdministratorPermission::cases(),
        );
    }
}
