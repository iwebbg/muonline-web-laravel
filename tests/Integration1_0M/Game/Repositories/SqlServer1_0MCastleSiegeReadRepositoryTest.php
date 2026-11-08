<?php

namespace Tests\Integration1_0M\Game\Repositories;

use App\Game\Repositories\SqlServer1_0MCastleSiegeReadRepository;
use DateTimeZone;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies Castle Siege reads against the disposable sanitized 1.0M SQL Server.
 */
final class SqlServer1_0MCastleSiegeReadRepositoryTest extends TestCase
{
    private const MAP_SERVER_GROUP = 99;

    /**
     * Removes only the isolated Castle Siege row owned by this test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $this->gameConnection()
                ->table('MuCastle_DATA')
                ->where('MAP_SVR_GROUP', self::MAP_SERVER_GROUP)
                ->delete();
        }

        parent::tearDown();
    }

    /**
     * Confirms SQL Server datetime and bit values preserve the stock 1.0M contract.
     */
    public function test_reads_authoritative_cycle_from_sanitized_schema(): void
    {
        $this->gameConnection()->table('MuCastle_DATA')->insert([
            'MAP_SVR_GROUP' => self::MAP_SERVER_GROUP,
            'SIEGE_START_DATE' => '2026-11-01 00:00:00',
            'SIEGE_END_DATE' => '2026-11-15 00:00:00',
            'SIEGE_ENDED' => 0,
            'CASTLE_OCCUPY' => 1,
            'OWNER_GUILD' => 'Legends',
        ]);
        $repository = new SqlServer1_0MCastleSiegeReadRepository(
            database: $this->app->make(DatabaseManager::class),
        );

        $state = $repository->state(
            self::MAP_SERVER_GROUP,
            new DateTimeZone('Europe/Sofia'),
        );

        $this->assertSame(self::MAP_SERVER_GROUP, $state?->mapServerGroup);
        $this->assertSame('2026-11-01T00:00:00+02:00', $state?->cycleStartsAt->format(DATE_ATOM));
        $this->assertSame('2026-11-15T00:00:00+02:00', $state?->cycleEndsAt->format(DATE_ATOM));
        $this->assertFalse($state?->siegeEnded);
        $this->assertTrue($state?->castleOccupied);
        $this->assertSame('Legends', $state?->ownerGuild);
    }

    /**
     * Returns the disposable SQL Server game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
