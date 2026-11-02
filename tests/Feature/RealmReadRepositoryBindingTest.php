<?php

namespace Tests\Feature;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
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
}
