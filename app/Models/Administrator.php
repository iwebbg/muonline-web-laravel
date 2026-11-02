<?php

namespace App\Models;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use Database\Factories\AdministratorFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Represents an application-owned administrator identity separate from game accounts.
 */
#[Fillable(['name', 'email', 'password', 'is_active', 'last_login_at', 'role', 'permissions'])]
#[Hidden(['password', 'remember_token'])]
final class Administrator extends Authenticatable
{
    /** @use HasFactory<AdministratorFactory> */
    use HasFactory;

    /**
     * Grants full access to administrators and allow-listed access to Game Masters.
     */
    public function hasPermission(AdministratorPermission $permission): bool
    {
        if ($this->role === AdministratorRole::Administrator) {
            return true;
        }

        return in_array($permission->value, $this->permissions ?? [], true);
    }

    /**
     * Defines secure type conversion for administrator state and credentials.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'role' => AdministratorRole::class,
            'permissions' => 'array',
        ];
    }
}
