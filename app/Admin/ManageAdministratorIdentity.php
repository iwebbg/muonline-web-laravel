<?php

namespace App\Admin;

use App\Admin\Authorization\AdministratorRole;
use App\Models\Administrator;
use App\Models\AdministratorIdentityRevision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

/**
 * Changes application identities from trusted local CLI without touching game accounts.
 */
final class ManageAdministratorIdentity
{
    /**
     * Applies validated changes or deletes an identity while preserving the last active owner.
     *
     * @param  array<string, mixed>  $changes
     * @return array<string, mixed>
     */
    public function handle(int $id, array $changes = [], bool $delete = false): array
    {
        return DB::transaction(function () use ($id, $changes, $delete): array {
            $identities = Administrator::query()->orderBy('id')->lockForUpdate()->get();
            $identity = $identities->firstWhere('id', $id);

            if ($identity === null) {
                throw ValidationException::withMessages(['administrator' => 'Administrator not found.']);
            }

            $validated = Validator::make($changes, [
                'name' => ['sometimes', 'required', 'string', 'max:255', 'regex:/\A[\x{0000}-\x{FFFF}]*\z/u'],
                'email' => ['sometimes', 'required', 'email', 'max:255', Rule::unique('administrators', 'email')->ignore($identity->getKey())],
                'is_active' => ['sometimes', 'required', 'boolean'],
                'password' => ['sometimes', 'required', 'string', Password::min(12)->mixedCase()->letters()->numbers()->symbols(), new AdministratorPasswordInput],
            ])->validate();

            if (($delete || ($validated['is_active'] ?? true) == false)
                && $identity->is_active
                && $identity->role === AdministratorRole::Administrator
                && $identities->where('is_active', true)->where('role', AdministratorRole::Administrator)->count() <= 1) {
                throw ValidationException::withMessages(['administrator' => 'The last active administrator cannot be deleted or deactivated.']);
            }

            $before = $this->snapshot($identity);

            if ($delete) {
                $identity->delete();
                $after = ['id' => $id, 'deleted' => true];
            } else {
                $identity->fill($validated);

                if (array_key_exists('password', $validated) || ! $identity->is_active) {
                    $identity->setRememberToken(Str::random(60));
                }

                $identity->save();
                $after = $this->snapshot($identity);
            }

            AdministratorIdentityRevision::query()->create([
                'target_administrator_id' => $delete ? null : $id,
                'changed_by_administrator_id' => null,
                'action' => $delete ? 'cli_deleted' : (array_key_exists('password', $validated) ? 'cli_password_rotated' : 'cli_updated'),
                'before_snapshot' => $before,
                'after_snapshot' => $after,
            ]);

            return $after;
        });
    }

    /**
     * Exposes only public identity metadata to CLI output and audit history.
     *
     * @return array{id: int, name: string, email: string, role: string, is_active: bool}
     */
    public function snapshot(Administrator $identity): array
    {
        return [
            'id' => (int) $identity->getKey(),
            'name' => $identity->name,
            'email' => $identity->email,
            'role' => $identity->role->value,
            'is_active' => $identity->is_active,
        ];
    }
}
