<?php

namespace Tests\Feature;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameDatabaseProfile;
use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\Group;
use Tests\TestCase;

/**
 * Verifies that each supported profile boots as one coherent adapter bundle.
 */
#[Group('profile-matrix')]
final class ActiveGameProfileTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms both profile contracts and event configuration select one key.
     */
    public function test_active_profile_contracts_match_the_requested_matrix_entry(): void
    {
        $expectedProfile = $this->expectedProfile();
        $versionProfile = $this->app->make(GameVersionProfile::class);
        $databaseProfile = $this->app->make(GameDatabaseProfile::class);

        $this->assertSame($expectedProfile, $versionProfile->key());
        $this->assertSame($versionProfile::class, $databaseProfile::class);
        $this->assertSame($expectedProfile, config('game.profile.key'));
        $this->assertSame($expectedProfile, config('game.event_profile'));
        $this->assertIsArray(config('game_events.profiles.'.$expectedProfile));
    }

    /**
     * Confirms the local matrix cannot silently omit a configured game profile.
     */
    public function test_profile_matrix_lists_every_configured_game_profile(): void
    {
        $matrixProfiles = array_values(array_filter(
            array_map('trim', file(base_path('tests/profile-matrix.txt'), FILE_IGNORE_NEW_LINES) ?: []),
            static fn (string $profile): bool => $profile !== '',
        ));
        $configuredProfiles = array_keys((array) config('game.version_profiles', []));

        sort($matrixProfiles);
        sort($configuredProfiles);

        $this->assertSame($configuredProfiles, $matrixProfiles);
    }

    /**
     * Confirms profile-selected item and game adapters can be constructed.
     */
    public function test_active_profile_adapter_bundle_resolves(): void
    {
        $profile = $this->app->make(GameVersionProfile::class);

        foreach ($profile->repositoryBindings() as $contract => $adapter) {
            if (in_array($contract, [RealmReadRepository::class, CastleSiegeReadRepository::class], true)) {
                continue;
            }

            $this->assertInstanceOf($adapter, $this->app->make($contract));
        }

        foreach ($profile->itemServiceBindings() as $contract => $adapter) {
            $this->assertInstanceOf($adapter, $this->app->make($contract));
        }
    }

    /**
     * Confirms public fixture pages render under the active profile catalog.
     */
    public function test_active_profile_renders_public_fixture_pages(): void
    {
        $this->get(route('home'))->assertOk();
        $this->get(route('rankings'))->assertOk();
    }

    /**
     * Returns the test-only profile selected by the Docker matrix.
     */
    private function expectedProfile(): string
    {
        $profile = getenv('MUONLINE_TEST_PROFILE');

        return is_string($profile) && $profile !== ''
            ? $profile
            : 'darksteam_97d99i';
    }
}
