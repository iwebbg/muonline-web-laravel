<?php

namespace App\Http\Middleware;

use App\Themes\ThemeManager;
use App\Website\WebsiteSettingsManager;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Illuminate\View\FileViewFinder;
use LogicException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies public Blade overrides without exposing administration to themes.
 */
final readonly class ApplyTheme
{
    /**
     * Creates the middleware with theme and website settings dependencies.
     */
    public function __construct(
        private ThemeManager $themes,
        private WebsiteSettingsManager $websiteSettings,
    ) {}

    /**
     * Activates the configured theme before Laravel resolves a public view.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->restoreCoreViewPaths();

        if ($this->usesCoreViews($request)) {
            return $next($request);
        }

        $theme = $this->themes->activate($this->websiteSettings->current()->themeSlug);
        $defaultTheme = $this->themes->activate($this->themes->defaultSlug());

        if (is_dir($defaultTheme['views_path'])) {
            $this->viewFinder()->prependLocation($defaultTheme['views_path']);
        }

        if ($theme['slug'] !== $defaultTheme['slug'] && is_dir($theme['views_path'])) {
            $this->viewFinder()->prependLocation($theme['views_path']);
        }

        $this->themes->activate($theme['slug']);
        View::share('activeTheme', $theme);

        return $next($request);
    }

    /**
     * Removes theme paths left by a previous request in a long-running process.
     */
    private function restoreCoreViewPaths(): void
    {
        $themePathPrefix = $this->themes->themesPath().DIRECTORY_SEPARATOR;
        $corePaths = array_values(array_filter(
            $this->viewFinder()->getPaths(),
            fn (string $path): bool => ! str_starts_with(
                rtrim($path, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR,
                $themePathPrefix,
            ),
        ));

        $this->viewFinder()->setPaths($corePaths);
    }

    /**
     * Returns the finder instance used by the active Blade view factory.
     */
    private function viewFinder(): FileViewFinder
    {
        $viewFinder = View::getFinder();

        if (! $viewFinder instanceof FileViewFinder) {
            throw new LogicException('The configured Blade finder does not support theme locations.');
        }

        return $viewFinder;
    }

    /**
     * Identifies requests that must remain independent from public themes.
     */
    private function usesCoreViews(Request $request): bool
    {
        return $request->routeIs(
            'admin.*',
            'ready',
            'server.status',
            'payments.webhooks',
        ) || $request->is('admin', 'admin/*');
    }
}
