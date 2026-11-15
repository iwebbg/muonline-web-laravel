<?php

namespace App\Http\Middleware;

use App\Game\Versions\Contracts\GameVersionProfile;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides native account purchase routes before resolving their adapter dependencies.
 */
final readonly class EnsureNativeAccountServicesAvailable
{
    /**
     * Reads explicit native account purchase support from the active version.
     */
    public function __construct(private GameVersionProfile $profile) {}

    /**
     * Preserves legacy routes while rejecting native-only endpoints on older adapters.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $capabilities = $this->profile->capabilities();
        abort_unless($capabilities->gameCurrencyExchange || $capabilities->accountVip, 404);

        return $next($request);
    }
}
