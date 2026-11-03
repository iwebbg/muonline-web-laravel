<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreGameServerRequest;
use App\Http\Requests\Admin\UpdateGameServerRequest;
use App\Models\Administrator;
use App\Models\GameServer;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Manages public server destinations without enabling multi-server game writes.
 */
final class GameServerController extends Controller
{
    /**
     * Displays every server destination in public display order.
     */
    public function index(): View
    {
        $servers = GameServer::query()
            ->orderByDesc('is_current')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
        $serializedServers = [];

        foreach ($servers as $server) {
            $serializedServers[] = $this->serializeForManager($server);
        }

        return view('admin.game-servers', [
            'servers' => $serializedServers,
        ]);
    }

    /**
     * Creates one destination and atomically moves the current flag when needed.
     */
    public function store(StoreGameServerRequest $request): RedirectResponse
    {
        $attributes = $request->serverData();

        if (! $attributes['is_current'] && ! GameServer::query()->where('is_current', true)->exists()) {
            return back()->withErrors([
                'is_current' => __('ui.admin.server_current_required'),
            ])->withInput();
        }

        DB::transaction(function () use ($attributes): void {
            if ($attributes['is_current']) {
                $this->demoteOtherCurrentServers();
            }

            GameServer::query()->create($attributes);
        });

        return back()->with('status', __('ui.admin.server_created'));
    }

    /**
     * Updates one destination while preserving exactly one current server.
     */
    public function update(
        UpdateGameServerRequest $request,
        GameServer $gameServer,
    ): RedirectResponse {
        $attributes = $request->serverData();
        $hasReplacementCurrent = GameServer::query()
            ->where('id', '!=', $gameServer->getKey())
            ->where('is_current', true)
            ->exists();

        if ($gameServer->is_current && ! $attributes['is_current'] && ! $hasReplacementCurrent) {
            return back()->withErrors([
                'is_current' => __('ui.admin.server_current_required'),
            ])->withInput();
        }

        DB::transaction(function () use ($attributes, $gameServer): void {
            if ($attributes['is_current']) {
                $this->demoteOtherCurrentServers($gameServer);
            }

            $gameServer->update($attributes);
        });

        return back()->with('status', __('ui.admin.server_updated'));
    }

    /**
     * Deletes one external destination while protecting the current website.
     */
    public function destroy(Request $request, GameServer $gameServer): RedirectResponse
    {
        $administrator = $request->user('admin');

        abort_unless($administrator instanceof Administrator, 403);

        if ($gameServer->is_current) {
            return back()->withErrors([
                'server' => __('ui.admin.server_current_delete_blocked'),
            ]);
        }

        $gameServer->delete();

        return back()->with('status', __('ui.admin.server_deleted'));
    }

    /**
     * Produces the safe editable state consumed by the Alpine manager.
     *
     * @return array<string, mixed>
     */
    private function serializeForManager(GameServer $server): array
    {
        return [
            'id' => $server->getKey(),
            'slug' => $server->slug,
            'name' => $server->name,
            'version' => $server->version,
            'experience' => $server->experience,
            'drop' => $server->drop,
            'capacity' => $server->capacity,
            'show_online_status' => $server->show_online_status,
            'show_online_users' => $server->show_online_users,
            'is_current' => $server->is_current,
            'is_visible' => $server->is_visible,
            'website_url' => $server->website_url ?? '',
            'sort_order' => $server->sort_order,
            'update_url' => route('admin.game-servers.update', $server),
            'delete_url' => route('admin.game-servers.destroy', $server),
        ];
    }

    /**
     * Locks and demotes previous current entries before another one is promoted.
     *
     * A demoted entry without an external URL is hidden to prevent a broken
     * player-facing destination until an administrator supplies its new URL.
     */
    private function demoteOtherCurrentServers(?GameServer $replacement = null): void
    {
        $currentServers = GameServer::query()
            ->where('is_current', true)
            ->when(
                $replacement instanceof GameServer,
                fn (Builder $query): Builder => $query->where('id', '!=', $replacement?->getKey()),
            )
            ->lockForUpdate()
            ->get();

        foreach ($currentServers as $currentServer) {
            $currentServer->update([
                'is_current' => false,
                'is_visible' => $currentServer->website_url !== null
                    && $currentServer->is_visible,
            ]);
        }
    }
}
