<?php

namespace Tests\Feature;

use App\Game\Data\GensRankingQuery;
use App\Game\Repositories\SqlServerMuDevsSeason21GensMembershipRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Matches native Gens views while preserving stable character ownership and private account scope.
 */
final class MuDevsSeason21GensMembershipRepositoryTest extends TestCase
{
    /**
     * Installs only the sanitized native read schema and profile class definitions.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(GameVersionProfile::class, MuDevsSeason21Profile::class);
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
    }

    /**
     * Preserves distinct tie positions, low contribution ranks and separate family standings.
     */
    public function test_memberships_match_native_sequential_rank_without_a_contribution_threshold(): void
    {
        $this->seedStandings();
        $repository = $this->app->make(SqlServerMuDevsSeason21GensMembershipRepository::class);

        $owned = $repository->forAccount('owner');

        self::assertSame(['Current', 'Low', 'Varnert'], array_keys($owned));
        self::assertSame(2, $owned['Current']->rank);
        self::assertSame(3, $owned['Low']->rank);
        self::assertSame(9999, $owned['Low']->contribution);
        self::assertSame(1, $owned['Varnert']->rank);
        self::assertSame(2, $repository->forCharacter('Current')->rank);
        self::assertNull($repository->forCharacter('missing'));
    }

    /**
     * Joins renamed characters by index instead of leaking a foreign cached-name match.
     */
    public function test_rankings_search_keeps_global_rank_and_uses_current_owned_names(): void
    {
        $this->seedStandings();
        $repository = $this->app->make(SqlServerMuDevsSeason21GensMembershipRepository::class);

        $page = $repository->rankings(new GensRankingQuery(1, 10, 'Current'));

        self::assertSame(1, $page->total);
        self::assertCount(1, $page->entries);
        self::assertSame('Current', $page->entries[0]->name);
        self::assertSame(2, $page->entries[0]->familyRank);
        self::assertSame('Templar Commander', $page->entries[0]->className);
        self::assertTrue($page->entries[0]->online);
    }

    /**
     * Treats SQL-shaped account identifiers literally and rejects invalid families.
     */
    public function test_private_scope_does_not_expand_to_foreign_or_nonstandard_memberships(): void
    {
        $this->seedStandings();
        $repository = $this->app->make(SqlServerMuDevsSeason21GensMembershipRepository::class);

        self::assertSame([], $repository->forAccount("' OR 1=1--"));
        self::assertNull($repository->forCharacter('Invalid'));
    }

    /**
     * Seeds duplicate contribution, stale-name, low-contribution and invalid-family boundaries.
     */
    private function seedStandings(): void
    {
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 1]);
        $this->game()->table('Character')->insert([
            ['AccountID' => 'other', 'Name' => 'Former', 'CharacterIndex' => 1, 'Class' => 16],
            ['AccountID' => 'owner', 'Name' => 'Current', 'CharacterIndex' => 2, 'Class' => 244],
            ['AccountID' => 'owner', 'Name' => 'Low', 'CharacterIndex' => 3, 'Class' => 0],
            ['AccountID' => 'owner', 'Name' => 'Varnert', 'CharacterIndex' => 4, 'Class' => 224],
            ['AccountID' => 'owner', 'Name' => 'Invalid', 'CharacterIndex' => 5, 'Class' => 16],
        ]);
        $this->game()->table('Gens_Rank')->insert([
            ['Name' => 'Alpha', 'CharacterIndex' => 1, 'Family' => 1, 'Contribution' => 10000],
            ['Name' => 'Former', 'CharacterIndex' => 2, 'Family' => 1, 'Contribution' => 10000],
            ['Name' => 'Low', 'CharacterIndex' => 3, 'Family' => 1, 'Contribution' => 9999],
            ['Name' => 'Varnert', 'CharacterIndex' => 4, 'Family' => 2, 'Contribution' => 1],
            ['Name' => 'Invalid', 'CharacterIndex' => 5, 'Family' => 9, 'Contribution' => 20000],
        ]);
    }

    /**
     * Returns the disposable game database without connecting to the live realm.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
