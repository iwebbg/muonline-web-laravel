<?php

namespace App\Http\Middleware;

use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hides player endpoints whose active server-profile module is disabled.
 */
final readonly class EnsureModuleEnabled
{
    /**
     * Creates the middleware with the version-aware module resolver.
     */
    public function __construct(
        private ModuleManager $moduleManager,
    ) {}

    /**
     * Returns a non-disclosing 404 response for unknown or disabled modules.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $serverModule = ServerModule::tryFrom($module);

        abort_unless(
            $serverModule instanceof ServerModule
            && $this->moduleManager->enabled($serverModule),
            404,
        );

        return $next($request);
    }
}
