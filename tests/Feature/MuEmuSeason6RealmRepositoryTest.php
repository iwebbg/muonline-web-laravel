<?php

namespace Tests\Feature;

use App\Game\Repositories\SqlServerMuEmuSeason6CastleSiegeReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6RealmReadRepository;
use App\Game\Versions\MuEmuSeason6\GuildMarkDecoderMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\MapNameMapperMuEmuSeason6;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use DateTimeZone;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies public MuEMU realm reads against the exported Update 32 columns.
 */
final class MuEmuSeason6RealmRepositoryTest extends TestCase
{
    /**
     * Creates the portable subset consumed by public realm and Castle Siege reads.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY, AccountLevel INTEGER NOT NULL, AccountExpireDate TEXT NOT NULL)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, Class INTEGER NULL, ResetCount INTEGER NOT NULL, MasterResetCount INTEGER NOT NULL, MapNumber INTEGER NULL, PkCount INTEGER NULL, PkLevel INTEGER NULL)');
        $connection->statement('CREATE TABLE Guild (G_Name TEXT NOT NULL, G_Master TEXT NULL, G_Score INTEGER NULL, G_Count INTEGER NULL, G_Notice TEXT NULL, G_Mark BLOB NULL)');
        $connection->statement('CREATE TABLE GuildMember (Name TEXT NOT NULL, G_Name TEXT NOT NULL, G_Level INTEGER NULL)');
        $connection->statement('CREATE TABLE MuCastle_DATA (MAP_SVR_GROUP INTEGER PRIMARY KEY, SIEGE_START_DATE TEXT NOT NULL, SIEGE_END_DATE TEXT NOT NULL, SIEGE_ENDED INTEGER NOT NULL, CASTLE_OCCUPY INTEGER NOT NULL, OWNER_GUILD TEXT NOT NULL)');
    }

    /**
     * Confirms RF rankings, native reset columns and account-wide VIP normalize safely.
     */
    public function test_reads_muemu_rankings_and_public_profiles(): void
    {
        $this->travelTo('2026-11-09 12:00:00');
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('MEMB_INFO')->insert([
            'memb___id' => 'fighter',
            'AccountLevel' => 2,
            'AccountExpireDate' => now()->addMonth()->format('Y-m-d H:i:s'),
        ]);
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => 'fighter',
            'ConnectStat' => 1,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => 'fighter',
            'Name' => 'RageHero',
            'cLevel' => 400,
            'Class' => 98,
            'ResetCount' => 35,
            'MasterResetCount' => 4,
            'MapNumber' => 81,
            'PkCount' => 7,
            'PkLevel' => 3,
        ]);
        $connection->table('Guild')->insert([
            'G_Name' => 'MuEMU',
            'G_Master' => 'RageHero',
            'G_Score' => 900,
            'G_Count' => 1,
            'G_Notice' => 'Season 6',
            'G_Mark' => hex2bin('1111111113133331133311111313111113133331111111111333333111111111'),
        ]);
        $connection->table('GuildMember')->insert([
            'Name' => 'RageHero',
            'G_Name' => 'MuEMU',
            'G_Level' => 128,
        ]);

        $repository = $this->realmRepository();
        $ranking = $repository->topCharacters(10);
        $character = $repository->publicCharacter('RageHero');
        $guild = $repository->publicGuild('MuEMU');

        self::assertCount(1, $ranking);
        self::assertSame('Fist Master', $ranking[0]->className);
        self::assertSame(35, $ranking[0]->resets);
        self::assertSame(4, $ranking[0]->grandResets);
        self::assertNotNull($character);
        self::assertSame('Karutan 2', $character->map);
        self::assertTrue($character->online);
        self::assertTrue($character->vip);
        self::assertNotNull($guild);
        self::assertSame('Season 6', $guild->notice);
        self::assertCount(64, $guild->markColors);
        self::assertTrue($guild->members[0]->master);
    }

    /**
     * Confirms the stock MuCastle_DATA cycle is exposed in the realm timezone.
     */
    public function test_reads_muemu_castle_siege_cycle(): void
    {
        $this->app->make(DatabaseManager::class)
            ->connection('game')
            ->table('MuCastle_DATA')
            ->insert([
                'MAP_SVR_GROUP' => 0,
                'SIEGE_START_DATE' => '2026-11-09 18:00:00',
                'SIEGE_END_DATE' => '2026-11-09 20:00:00',
                'SIEGE_ENDED' => 0,
                'CASTLE_OCCUPY' => 1,
                'OWNER_GUILD' => 'MuEMU',
            ]);
        $repository = new SqlServerMuEmuSeason6CastleSiegeReadRepository(
            $this->app->make(DatabaseManager::class),
        );

        $state = $repository->state(0, new DateTimeZone('Europe/Sofia'));

        self::assertNotNull($state);
        self::assertSame('2026-11-09 18:00:00', $state->cycleStartsAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-09 20:00:00', $state->cycleEndsAt->format('Y-m-d H:i:s'));
        self::assertTrue($state->castleOccupied);
        self::assertSame('MuEMU', $state->ownerGuild);
    }

    /**
     * Builds the isolated MuEMU public realm adapter.
     */
    private function realmRepository(): SqlServerMuEmuSeason6RealmReadRepository
    {
        return new SqlServerMuEmuSeason6RealmReadRepository(
            database: $this->app->make(DatabaseManager::class),
            versionProfile: new MuEmuSeason6Profile,
            guildMarkDecoder: new GuildMarkDecoderMuEmuSeason6,
            mapNameMapper: new MapNameMapperMuEmuSeason6,
        );
    }
}
