<?php

namespace App\Http\Controllers;

use App\Game\Contracts\GameServerStatus;
use App\Game\Contracts\RealmReadRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;

/**
 * Publishes the minimal cached status contract consumed by related websites.
 */
final class ServerStatusController extends Controller
{
    /**
     * Returns online state and live user count without exposing game data.
     */
    public function __invoke(
        GameServerStatus $gameServerStatus,
        RealmReadRepository $realmReadRepository,
    ): JsonResponse {
        $cacheSeconds = (int) config('game.server_directory.status_cache_seconds', 600);
        $status = Cache::remember(
            'server-directory:public-status:v1',
            $cacheSeconds,
            static fn (): array => [
                'online' => $gameServerStatus->isOnline(),
                'online_users' => $realmReadRepository->overview()->online,
                'generated_at' => now()->toAtomString(),
            ],
        );

        return response()
            ->json($status)
            ->setPublic()
            ->setMaxAge($cacheSeconds);
    }
}
