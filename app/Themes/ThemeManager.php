<?php

namespace App\Themes;

use Illuminate\Support\Str;

/**
 * Discovers directory-based public themes and resolves the active safe theme.
 */
final class ThemeManager
{
    /**
     * Stores the theme selected for the current public request.
     *
     * @var array{slug: string, name: string, views_path: string}|null
     */
    private ?array $activeTheme = null;

    /**
     * Creates the manager for the configured theme directory and default slug.
     *
     * @param  array<string, string>  $labels
     */
    public function __construct(
        private readonly string $themesPath,
        private readonly string $defaultTheme,
        private readonly array $labels = [],
    ) {}

    /**
     * Returns valid installed themes keyed by their directory slug.
     *
     * @return array<string, array{slug: string, name: string, views_path: string}>
     */
    public function available(): array
    {
        $themes = [];
        $themeDirectories = glob($this->normalizedThemesPath().'/*', GLOB_ONLYDIR) ?: [];

        foreach ($themeDirectories as $themeDirectory) {
            $slug = basename($themeDirectory);

            if (! $this->isValidSlug($slug)) {
                continue;
            }

            $themes[$slug] = [
                'slug' => $slug,
                'name' => $this->labels[$slug] ?? Str::headline($slug),
                'views_path' => $themeDirectory,
            ];
        }

        ksort($themes);

        return $themes;
    }

    /**
     * Reports whether a slug matches the public theme directory contract.
     */
    public function isValidSlug(string $slug): bool
    {
        return preg_match('/^[a-z0-9][a-z0-9-]*$/', $slug) === 1;
    }

    /**
     * Reports whether a valid theme directory is currently installed.
     */
    public function isInstalled(string $slug): bool
    {
        return array_key_exists($slug, $this->available());
    }

    /**
     * Activates an installed theme or safely falls back to the physical default.
     *
     * @return array{slug: string, name: string, views_path: string}
     */
    public function activate(?string $slug): array
    {
        $themes = $this->available();
        $selectedTheme = is_string($slug) ? ($themes[$slug] ?? null) : null;
        $defaultTheme = $themes[$this->defaultTheme] ?? $this->defaultMetadata();

        return $this->activeTheme = $selectedTheme ?? $defaultTheme;
    }

    /**
     * Returns the active theme and activates the default when none was selected.
     *
     * @return array{slug: string, name: string, views_path: string}
     */
    public function active(): array
    {
        return $this->activeTheme ?? $this->activate($this->defaultTheme);
    }

    /**
     * Returns the configured default theme slug.
     */
    public function defaultSlug(): string
    {
        return $this->defaultTheme;
    }

    /**
     * Returns the normalized root used to remove stale finder locations.
     */
    public function themesPath(): string
    {
        return $this->normalizedThemesPath();
    }

    /**
     * Builds fallback metadata even when deployment temporarily lacks the default directory.
     *
     * @return array{slug: string, name: string, views_path: string}
     */
    private function defaultMetadata(): array
    {
        return [
            'slug' => $this->defaultTheme,
            'name' => $this->labels[$this->defaultTheme] ?? Str::headline($this->defaultTheme),
            'views_path' => $this->normalizedThemesPath().'/'.$this->defaultTheme,
        ];
    }

    /**
     * Removes the trailing directory separator from the configured theme root.
     */
    private function normalizedThemesPath(): string
    {
        $themesPath = realpath($this->themesPath) ?: $this->themesPath;

        return rtrim($themesPath, DIRECTORY_SEPARATOR);
    }
}
