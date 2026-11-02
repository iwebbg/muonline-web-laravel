<?php

namespace Database\Factories;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<Administrator>
 */
class AdministratorFactory extends Factory
{
    /**
     * Stores one shared test password hash for efficient factory creation.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('Strong!Password123'),
            'is_active' => true,
            'last_login_at' => null,
            'role' => AdministratorRole::Administrator,
            'permissions' => null,
        ];
    }

    /**
     * Marks the generated administrator as unable to authenticate.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_active' => false,
        ]);
    }

    /**
     * Creates a Game Master with only the explicitly supplied capabilities.
     *
     * @param  list<AdministratorPermission>  $permissions
     */
    public function gameMaster(array $permissions = []): static
    {
        $permissionValues = [];

        foreach ($permissions as $permission) {
            $permissionValues[] = $permission->value;
        }

        return $this->state(fn (array $attributes): array => [
            'role' => AdministratorRole::GameMaster,
            'permissions' => $permissionValues,
        ]);
    }
}
