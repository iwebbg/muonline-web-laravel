<?php

namespace App\Http\Middleware;

use App\Game\Versions\Contracts\GameVersionProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps unfinished version adapters on their reviewed website endpoints.
 */
final readonly class EnsureGameProfileAvailable
{
    /**
     * Selects the route support declared by the active game adapter.
     */
    public function __construct(private GameVersionProfile $versionProfile) {}

    /**
     * Rejects unavailable operations before resolving unimplemented repositories.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(
            $this->versionProfile->capabilities()->allowsRoute($request->route()?->getName() ?? ''),
            404,
        );

        return $next($request);
    }
}
