<?php

namespace App\Http\Middleware;

use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use App\Website\WebsiteSettingsManager;
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
        private WebsiteSettingsManager $websiteSettingsManager,
    ) {}

    /**
     * Hides disabled modules and redirects paused registration submissions to their notice.
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

        if ($serverModule === ServerModule::Registration
            && ! $request->isMethod('GET')
            && ! $this->websiteSettingsManager->current()->registrationEnabled) {
            return redirect()->route('register');
        }

        return $next($request);
    }
}
