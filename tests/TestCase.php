<?php

namespace Tests;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\FixtureRealmReadRepository;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

/**
 * Provides project-wide isolation from the live MuOnline database.
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Forces public realm reads to fixtures unless a test explicitly opts in.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.data_source', 'fixture');
        $this->app->bind(RealmReadRepository::class, FixtureRealmReadRepository::class);
    }
}
