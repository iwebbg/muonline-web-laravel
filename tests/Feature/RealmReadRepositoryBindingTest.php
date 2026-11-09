<?php

namespace Tests\Feature;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Repositories\SqlServer1_0MCastleSiegeReadRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6CastleSiegeReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6RealmReadRepository;
use App\Game\Repositories\UnavailableCastleSiegeReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Providers\AppServiceProvider;
use Tests\TestCase;

/**
 * Verifies selection of the configured public realm data source.
 */
final class RealmReadRepositoryBindingTest extends TestCase
{
    /**
     * Confirms that local development remains fixture-backed by default.
     */
    public function test_fixture_repository_is_selected_by_default(): void
    {
        $repository = $this->app->make(RealmReadRepository::class);

        $this->assertInstanceOf(FixtureRealmReadRepository::class, $repository);
    }

    /**
     * Confirms that the database source selects the 97d+99i SQL repository.
     */
    public function test_database_repository_is_selected_when_enabled(): void
    {
        config()->set('game.data_source', 'database');

        $provider = new AppServiceProvider($this->app);

        $provider->register();

        $repository = $this->app->make(RealmReadRepository::class);

        $this->assertInstanceOf(SqlServer97d99iRealmReadRepository::class, $repository);
    }

    /**
     * Confirms database-backed 1.0M processes receive the authoritative Castle Siege reader.
     */
    public function test_database_1_0m_profile_selects_castle_siege_repository(): void
    {
        config()->set('game.data_source', 'database');
        config()->set('game.profile.key', 'darksteam_1_0m');
        $this->app->forgetInstance(GameVersionProfile::class);

        (new AppServiceProvider($this->app))->register();

        $repository = $this->app->make(CastleSiegeReadRepository::class);

        $this->assertInstanceOf(SqlServer1_0MCastleSiegeReadRepository::class, $repository);
    }

    /**
     * Confirms fixture-backed 1.0M processes cannot reach the external Castle Siege table.
     */
    public function test_fixture_1_0m_profile_disables_castle_siege_repository(): void
    {
        config()->set('game.data_source', 'fixture');
        config()->set('game.profile.key', 'darksteam_1_0m');
        $this->app->forgetInstance(GameVersionProfile::class);

        (new AppServiceProvider($this->app))->register();

        $repository = $this->app->make(CastleSiegeReadRepository::class);

        $this->assertInstanceOf(UnavailableCastleSiegeReadRepository::class, $repository);
    }

    /**
     * Confirms database-backed MuEMU processes receive only their realm adapters.
     */
    public function test_database_muemu_profile_selects_its_realm_repositories(): void
    {
        config()->set('game.data_source', 'database');
        config()->set('game.profile.key', 'muemu_season_6');
        $this->app->forgetInstance(GameVersionProfile::class);

        (new AppServiceProvider($this->app))->register();

        $this->assertInstanceOf(
            SqlServerMuEmuSeason6RealmReadRepository::class,
            $this->app->make(RealmReadRepository::class),
        );
        $this->assertInstanceOf(
            SqlServerMuEmuSeason6CastleSiegeReadRepository::class,
            $this->app->make(CastleSiegeReadRepository::class),
        );
    }
}
