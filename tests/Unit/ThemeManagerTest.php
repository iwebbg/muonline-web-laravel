<?php

namespace Tests\Unit;

use App\Themes\ThemeManager;
use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;

/**
 * Verifies directory-based public theme discovery and safe activation fallback.
 */
final class ThemeManagerTest extends TestCase
{
    private Filesystem $filesystem;

    private string $themesPath;

    /**
     * Creates isolated valid and invalid theme directories for each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->filesystem = new Filesystem;
        $this->themesPath = sys_get_temp_dir().'/muonline-themes-'.bin2hex(random_bytes(8));
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/muonline');
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/dark-fantasy');
        $this->filesystem->ensureDirectoryExists($this->themesPath.'/Invalid_Theme');
    }

    /**
     * Removes all isolated theme directories after each test.
     */
    protected function tearDown(): void
    {
        $this->filesystem->deleteDirectory($this->themesPath);

        parent::tearDown();
    }

    /**
     * Discovers only direct directories whose slugs satisfy the theme contract.
     */
    public function test_discovers_only_valid_theme_directories(): void
    {
        $manager = $this->manager();

        $this->assertSame(['dark-fantasy', 'muonline'], array_keys($manager->available()));
        $this->assertSame('Dark Fantasy', $manager->available()['dark-fantasy']['name']);
        $this->assertSame('MuOnline Core', $manager->available()['muonline']['name']);
    }

    /**
     * Activates an installed theme and exposes its resolved metadata.
     */
    public function test_activates_an_installed_theme(): void
    {
        $manager = $this->manager();

        $activeTheme = $manager->activate('dark-fantasy');

        $this->assertSame('dark-fantasy', $activeTheme['slug']);
        $this->assertSame($this->themesPath.'/dark-fantasy', $activeTheme['views_path']);
        $this->assertSame($activeTheme, $manager->active());
    }

    /**
     * Falls back to MuOnline when the requested theme is unknown or removed.
     */
    public function test_falls_back_to_muonline_for_unknown_or_removed_theme(): void
    {
        $this->assertSame('muonline', $this->manager()->activate('unknown-theme')['slug']);

        $this->filesystem->deleteDirectory($this->themesPath.'/dark-fantasy');

        $this->assertSame('muonline', $this->manager()->activate('dark-fantasy')['slug']);
    }

    /**
     * Creates a manager configured with the isolated theme root.
     */
    private function manager(): ThemeManager
    {
        return new ThemeManager(
            themesPath: $this->themesPath,
            defaultTheme: 'muonline',
            labels: ['muonline' => 'MuOnline Core'],
        );
    }
}
