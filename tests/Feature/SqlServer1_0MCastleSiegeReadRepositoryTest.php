<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServer1_0MCastleSiegeReadRepository;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies portable Castle Siege reads against an isolated legacy-shaped table.
 */
final class SqlServer1_0MCastleSiegeReadRepositoryTest extends TestCase
{
    /**
     * Creates the minimal game table consumed by the read-only repository.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->gameConnection()->statement(
            'CREATE TABLE MuCastle_DATA ('
            .'MAP_SVR_GROUP INTEGER PRIMARY KEY, '
            .'SIEGE_START_DATE TEXT NOT NULL, '
            .'SIEGE_END_DATE TEXT NOT NULL, '
            .'SIEGE_ENDED INTEGER NOT NULL, '
            .'CASTLE_OCCUPY INTEGER NOT NULL, '
            .'OWNER_GUILD TEXT NOT NULL)'
        );
    }

    /**
     * Confirms legacy fields become one typed cycle through a single SELECT query.
     */
    public function test_reads_normalized_castle_siege_state(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MuCastle_DATA')->insert([
            'MAP_SVR_GROUP' => 0,
            'SIEGE_START_DATE' => '2026-11-01 00:00:00',
            'SIEGE_END_DATE' => '2026-11-15 00:00:00',
            'SIEGE_ENDED' => 0,
            'CASTLE_OCCUPY' => 1,
            'OWNER_GUILD' => 'Legends ',
        ]);
        $connection->flushQueryLog();
        $connection->enableQueryLog();
        $repository = new SqlServer1_0MCastleSiegeReadRepository(
            database: $this->app->make(DatabaseManager::class),
        );

        $state = $repository->state(0, new DateTimeZone('Europe/Sofia'));

        $this->assertSame(0, $state?->mapServerGroup);
        $this->assertSame('2026-11-01T00:00:00+02:00', $state?->cycleStartsAt->format(DATE_ATOM));
        $this->assertSame('2026-11-15T00:00:00+02:00', $state?->cycleEndsAt->format(DATE_ATOM));
        $this->assertFalse($state?->siegeEnded);
        $this->assertTrue($state?->castleOccupied);
        $this->assertSame('Legends', $state?->ownerGuild);
        $this->assertCount(1, $connection->getQueryLog());
        $this->assertStringStartsWith(
            'select',
            strtolower(ltrim($connection->getQueryLog()[0]['query'])),
        );
    }

    /**
     * Confirms an uninitialized map server group produces no synthetic state.
     */
    public function test_returns_null_for_an_uninitialized_map_server_group(): void
    {
        $repository = new SqlServer1_0MCastleSiegeReadRepository(
            database: $this->app->make(DatabaseManager::class),
        );

        $state = $repository->state(7, new DateTimeZone('Europe/Sofia'));

        $this->assertNull($state);
    }

    /**
     * Returns the isolated SQLite game connection installed by the base test case.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
