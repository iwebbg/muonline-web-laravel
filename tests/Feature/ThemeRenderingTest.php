<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\WebsiteSetting;
use App\Themes\ThemeManager;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies public theme resolution, metadata and administration isolation.
 */
final class ThemeRenderingTest extends TestCase
{
    use LazilyRefreshDatabase;

    private Filesystem $filesystem;

    private string $themesPath;

    /**
     * Configures disposable default and partial themes outside the read-only source tree.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
        $this->themesPath = sys_get_temp_dir().'/muonline-rendering-themes-'.bin2hex(random_bytes(8));
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/muonline/layouts');
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/partial-theme/admin');
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/partial-theme/layouts');
        $this->filesystem->put(
            $this->themesPath.'/muonline/layouts/app.blade.php',
            '<main>Default layout marker @yield(\'content\')</main>',
        );
        $this->filesystem->put(
            $this->themesPath.'/muonline/home.blade.php',
            '@extends(\'layouts.app\')'.PHP_EOL
                .'@section(\'content\')'.PHP_EOL
                .'Default home marker'.PHP_EOL
                .'@endsection'.PHP_EOL,
        );
        $this->filesystem->put(
            $this->themesPath.'/partial-theme/home.blade.php',
            '@extends(\'layouts.app\')'.PHP_EOL
                .'@section(\'content\')'.PHP_EOL
                .'Custom home marker {{ $activeTheme[\'slug\'] }}'.PHP_EOL
                .'@endsection'.PHP_EOL,
        );
        $this->filesystem->put(
            $this->themesPath.'/partial-theme/admin/website-settings.blade.php',
            'Unsafe themed administration marker',
        );
        $this->filesystem->put(
            $this->themesPath.'/partial-theme/layouts/admin.blade.php',
            'Unsafe themed administration layout',
        );

        config()->set('themes.path', $this->themesPath);
        $this->app->forgetInstance(ThemeManager::class);
    }

    /**
     * Removes disposable themes and cached view files after each test.
     */
    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    /**
     * Uses a partial home override and the default layout while sharing active metadata.
     */
    public function test_partial_theme_uses_default_blade_fallback_and_active_metadata(): void
    {
        WebsiteSetting::query()->create(['theme_slug' => 'partial-theme']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Default layout marker')
            ->assertSee('Custom home marker partial-theme');
    }

    /**
     * Falls back to the physical default view when a saved theme no longer exists.
     */
    public function test_removed_theme_falls_back_to_default_blade_view(): void
    {
        WebsiteSetting::query()->create(['theme_slug' => 'removed-theme']);

        $this->get('/')
            ->assertOk()
            ->assertSee('Default home marker')
            ->assertDontSee('Custom home marker');
    }

    /**
     * Keeps administration on core views even when a theme supplies matching files.
     */
    public function test_administration_cannot_be_overridden_by_public_theme(): void
    {
        $administrator = Administrator::factory()->create();
        WebsiteSetting::query()->create(['theme_slug' => 'partial-theme']);

        $this->actingAs($administrator, 'admin')
            ->get(route('admin.website-settings.edit'))
            ->assertOk()
            ->assertSee('Website identity and SEO')
            ->assertDontSee('Unsafe themed administration marker')
            ->assertDontSee('Unsafe themed administration layout');
    }
}
