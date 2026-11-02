<?php

namespace App\Http\Controllers\Admin;

use App\Admin\Authorization\AdministratorPermission;
use App\Admin\Authorization\AdministratorRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameMasterRequest;
use App\Http\Requests\Admin\UpdateGameMasterRequest;
use App\Models\Administrator;
use App\Models\AdministratorIdentityRevision;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages Game Master identities behind the full-administrator boundary.
 */
final class GameMasterController extends Controller
{
    /**
     * Displays Game Masters and the stable permission allow-list.
     */
    public function index(): View
    {
        $gameMasters = Administrator::query()
            ->where('role', AdministratorRole::GameMaster)
            ->orderBy('name')
            ->get();
        $serializedGameMasters = [];

        foreach ($gameMasters as $gameMaster) {
            $serializedGameMasters[] = $this->serializeGameMaster($gameMaster);
        }

        return view('admin.game-masters', [
            'gameMasters' => $serializedGameMasters,
            'permissions' => $this->permissionOptions(),
        ]);
    }

    /**
     * Creates a strongly hashed Game Master and password-free audit revision.
     */
    public function store(StoreGameMasterRequest $request): RedirectResponse
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator): void {
            $identityData = $request->identityData();
            $gameMaster = Administrator::query()->create([
                ...$identityData,
                'role' => AdministratorRole::GameMaster,
                'is_active' => true,
            ]);

            $this->recordRevision(
                gameMaster: $gameMaster,
                administrator: $administrator,
                action: 'created',
                beforeSnapshot: null,
            );
        });

        return back()->with('status', __('ui.admin.game_master_created'));
    }

    /**
     * Updates one Game Master without allowing full-administrator mutation.
     */
    public function update(
        UpdateGameMasterRequest $request,
        Administrator $gameMaster,
    ): RedirectResponse {
        abort_unless($gameMaster->role === AdministratorRole::GameMaster, 404);

        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        DB::transaction(function () use ($request, $administrator, $gameMaster): void {
            $beforeSnapshot = $this->identitySnapshot($gameMaster);
            $identityData = $request->identityData();
            $passwordRotated = array_key_exists('password', $identityData);

            $gameMaster->update($identityData);

            $this->recordRevision(
                gameMaster: $gameMaster->refresh(),
                administrator: $administrator,
                action: $passwordRotated ? 'updated_with_password_rotation' : 'updated',
                beforeSnapshot: $beforeSnapshot,
            );
        });

        return back()->with('status', __('ui.admin.game_master_updated'));
    }

    /**
     * Serializes one GM for the interactive editor without credential fields.
     *
     * @return array<string, mixed>
     */
    private function serializeGameMaster(Administrator $gameMaster): array
    {
        return [
            ...$this->identitySnapshot($gameMaster),
            'id' => $gameMaster->getKey(),
            'last_login_at' => $gameMaster->last_login_at?->diffForHumans() ?? '',
            'update_url' => route('admin.game-masters.update', $gameMaster),
        ];
    }

    /**
     * Returns the password-free state stored in every identity revision.
     *
     * @return array{name: string, email: string, role: string, is_active: bool, permissions: list<string>}
     */
    private function identitySnapshot(Administrator $gameMaster): array
    {
        return [
            'name' => $gameMaster->name,
            'email' => $gameMaster->email,
            'role' => $gameMaster->role->value,
            'is_active' => $gameMaster->is_active,
            'permissions' => array_values($gameMaster->permissions ?? []),
        ];
    }

    /**
     * Returns localized permission metadata for the administrator form.
     *
     * @return list<array{value: string, label: string}>
     */
    private function permissionOptions(): array
    {
        $options = [];

        foreach (AdministratorPermission::cases() as $permission) {
            $options[] = [
                'value' => $permission->value,
                'label' => __('ui.admin.permissions.'.$permission->value),
            ];
        }

        return $options;
    }

    /**
     * Persists one immutable password-free Game Master revision.
     *
     * @param  array<string, mixed>|null  $beforeSnapshot
     */
    private function recordRevision(
        Administrator $gameMaster,
        Administrator $administrator,
        string $action,
        ?array $beforeSnapshot,
    ): void {
        AdministratorIdentityRevision::query()->create([
            'target_administrator_id' => $gameMaster->getKey(),
            'changed_by_administrator_id' => $administrator->getKey(),
            'action' => $action,
            'before_snapshot' => $beforeSnapshot,
            'after_snapshot' => $this->identitySnapshot($gameMaster),
        ]);
    }
}
