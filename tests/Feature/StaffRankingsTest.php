<?php

namespace Tests\Feature;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingQuery;
use App\Game\Rankings\RankingBoard;
use App\Game\Repositories\SqlServer1_0MRealmReadRepository;
use App\Game\Repositories\SqlServer97d99iRealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Repositories\SqlServerMuEmuSeason6RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** Exercises the Staff board against every version's actual SQL query adapter. */
final class StaffRankingsTest extends TestCase
{
    /**
     * Returns every public SQL reader and its corresponding version catalog.
     *
     * @return array<string, array{class-string, class-string}>
     */
    public static function profiles(): array
    {
        return [
            '97d99i' => [SqlServer97d99iRealmReadRepository::class, Version97d99iProfile::class],
            '1.0M' => [SqlServer1_0MRealmReadRepository::class, Version1_0MProfile::class],
            'S6' => [SqlServerMuEmuSeason6RealmReadRepository::class, MuEmuSeason6Profile::class],
            'S21' => [SqlServerMuDevsSeason21RealmReadRepository::class, MuDevsSeason21Profile::class],
        ];
    }

    /** Verifies exact control codes, offline state, filters and privacy across all adapters. */
    #[DataProvider('profiles')]
    public function test_staff_returns_only_game_masters_without_private_location(string $readerClass, string $profileClass): void
    {
        $profile = new $profileClass;
        $this->app->instance(GameVersionProfile::class, $profile);
        config()->set('game.database_connections.rankings', 'game');
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->statement('CREATE TABLE Character (Name TEXT PRIMARY KEY, AccountID TEXT, CharacterIndex INTEGER, Class INTEGER, cLevel INTEGER, CtlCode INTEGER, Resets INTEGER DEFAULT 0, GrandResets INTEGER DEFAULT 0, ResetCount INTEGER DEFAULT 0, MasterResetCount INTEGER DEFAULT 0, PkCount INTEGER DEFAULT 0, PkLevel INTEGER DEFAULT 0)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER)');
        $connection->statement('CREATE TABLE MasterSkillTree (CharacterIndex INTEGER PRIMARY KEY, MasterLevel INTEGER)');
        $connection->table('Character')->insert([
            ['Name' => 'Admin8', 'AccountID' => 'owner', 'CharacterIndex' => 1, 'Class' => 1, 'cLevel' => 400, 'CtlCode' => 8],
            ['Name' => 'Admin32', 'AccountID' => 'offline', 'CharacterIndex' => 2, 'Class' => 17, 'cLevel' => 350, 'CtlCode' => 32],
            ['Name' => 'Player', 'AccountID' => 'owner', 'CharacterIndex' => 3, 'Class' => 1, 'cLevel' => 400, 'CtlCode' => 0],
            ['Name' => 'OtherFlag', 'AccountID' => 'owner', 'CharacterIndex' => 4, 'Class' => 1, 'cLevel' => 400, 'CtlCode' => 40],
        ]);
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 1]);
        $connection->table('MasterSkillTree')->insert(['CharacterIndex' => 1, 'MasterLevel' => 80]);
        $reader = $this->app->make($readerClass);
        $this->app->instance(RealmReadRepository::class, $reader);

        $response = $this->getJson(route('rankings.data', ['board' => 'staff']));
        $response->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('items.0.name', 'Admin32')
            ->assertJsonPath('items.0.online', false)
            ->assertJsonPath('items.1.name', 'Admin8')
            ->assertJsonPath('items.1.online', true);
        self::assertSame(
            ['rank', 'name', 'class', 'class_abbreviation', 'image', 'level', 'displayLevel', 'online'],
            array_keys($response->json('items.0')),
        );

        $this->getJson(route('rankings.data', ['board' => 'staff', 'online' => 1, 'search' => 'Admin8']))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Admin8');
        $this->getJson(route('rankings.data', ['board' => 'staff', 'class' => $profile->classForCode(17)->key]))
            ->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('items.0.name', 'Admin32');
        $this->getJson(route('rankings.data', ['board' => 'staff', 'search' => "' OR 1=1--"]))
            ->assertOk()->assertJsonCount(0, 'items');
        $page = $reader->characterRankings(new CharacterRankingQuery(RankingBoard::Staff, null, 2, 1, '', false));
        self::assertSame('Admin8', $page->entries[0]->name);
        self::assertSame(2, $page->lastPage);
    }

    /** Confirms the fixture board is also reachable without a live game database. */
    public function test_staff_tab_is_available_in_fixture_mode(): void
    {
        $this->get(route('rankings', ['board' => 'staff']))->assertOk()->assertSee('Staff');
        $this->getJson(route('rankings.data', ['board' => 'staff']))
            ->assertOk()->assertJsonCount(2, 'items');
    }
}
