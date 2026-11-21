<?php

namespace Tests\Feature;

use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\CharacterRankingQuery;
use App\Game\Data\GuildRankingQuery;
use App\Game\Rankings\RankingBoard;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\GuildMarkDecoderMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies S21 public profiles, filtered rankings, guilds and realm aggregates.
 */
final class MuDevsSeason21RealmReadRepositoryTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms new class portraits and native reset columns flow through public rankings.
     */
    public function test_reads_filtered_and_paginated_rankings_for_new_families(): void
    {
        $this->createGameSchema();
        $repository = $this->repository();
        $page = $repository->characterRankings(new CharacterRankingQuery(
            RankingBoard::Overall, null, 1, 2, '', false,
        ));

        self::assertSame(3, $page->total);
        self::assertSame(2, $page->lastPage);
        self::assertSame(['Crusader', 'RuneHero'], array_column($page->entries, 'name'));
        self::assertArrayNotHasKey('imagePosition', $page->entries[0]->toArray());
        self::assertSame('center top', $page->entries[0]->imagePosition);

        $filtered = $repository->characterRankings(new CharacterRankingQuery(
            RankingBoard::Overall, (new MuDevsSeason21Profile)->classForCode(144), 1, 10, '', false,
        ));

        self::assertSame(1, $filtered->total);
        self::assertSame('Slayer', $filtered->entries[0]->className);
        self::assertSame('center top', $filtered->entries[0]->imagePosition);

        $killers = $repository->characterRankings(new CharacterRankingQuery(
            RankingBoard::PlayerKillers, null, 1, 10, '', true,
        ));

        self::assertSame(['RuneHero', 'Crusader'], array_column($killers->entries, 'name'));
        self::assertCount(1, $repository->topCharacters(1));
        self::assertSame(0, $repository->characterRankings(new CharacterRankingQuery(
            RankingBoard::Overall, null, 1, 10, "' OR 1=1--", false,
        ))->total);
    }

    /**
     * Confirms public characters and guilds never expose account identifiers or balances.
     */
    public function test_reads_public_character_and_guild_with_native_map_and_mark(): void
    {
        $this->createGameSchema();
        $this->travelTo('2026-11-14 12:00:00');
        $repository = $this->repository();

        $character = $repository->publicCharacter('Crusader');
        $guild = $repository->publicGuild('S21Guild');
        $guilds = $repository->guildRankings(new GuildRankingQuery(1, 10, 'S21'));

        self::assertSame('Crusader', $character?->className);
        self::assertSame('Aquilas Temple', $character?->map);
        self::assertSame('center top', $character?->imagePosition);
        self::assertTrue($character?->vip);
        self::assertSame('S21Guild', $character?->guildName);
        self::assertArrayNotHasKey('accountId', get_object_vars($character));
        self::assertArrayNotHasKey('zen', get_object_vars($character));
        self::assertCount(64, $guild->markColors);
        self::assertSame('#000000', $guild->markColors[0]);
        self::assertSame('Crusader', $guild->members[0]->name);
        self::assertTrue($guild->members[0]->master);
        self::assertSame(1, $guilds->total);
        self::assertSame('S21Guild', $repository->topGuilds(1)[0]->name);
        self::assertNull($repository->publicCharacter('missing'));
        self::assertNull($repository->publicGuild('missing'));
    }

    /**
     * Confirms SQL wall-clock expiry is compared using the game content timezone.
     */
    public function test_resolves_vip_expiration_in_the_game_timezone(): void
    {
        $connection = $this->createGameSchema();
        $this->travelTo('2026-11-14 12:00:00 UTC');
        config()->set('game.content_timezone', 'Europe/Sofia');
        $connection->table('MEMB_INFO')
            ->where('memb___id', 'owner')
            ->update(['AccountExpireDate' => '2026-11-14 13:30:00']);

        self::assertFalse($this->repository()->publicCharacter('Crusader')?->vip);
    }

    /**
     * Confirms class and map distributions use S21 labels instead of legacy aliases.
     */
    public function test_reads_realm_counts_and_s21_distributions(): void
    {
        $this->createGameSchema();
        $repository = $this->repository();
        $overview = $repository->overview();
        $analytics = $repository->analytics();

        self::assertSame(2, $overview->accounts);
        self::assertSame(3, $overview->characters);
        self::assertSame(1, $overview->guilds);
        self::assertSame(1, $overview->online);
        self::assertContains('Crusader', array_column($analytics->classes, 'label'));
        self::assertContains('Aquilas Temple', array_column($analytics->maps, 'label'));
    }

    /**
     * Confirms the existing ranking endpoint renders source-backed classes and crop metadata.
     */
    public function test_ranking_endpoint_renders_s21_class_filters_and_safe_portrait_metadata(): void
    {
        $this->createGameSchema();
        $this->app->instance(GameVersionProfile::class, new MuDevsSeason21Profile);
        $this->app->instance(RealmReadRepository::class, $this->repository());

        $this->get('/rankings')
            ->assertOk()
            ->assertSee('Crusader')
            ->assertSee('Illusion Knight')
            ->assertSee('imagePosition')
            ->assertSee('muonline-web-crusader.png');
    }

    /**
     * Ranks and displays S21 totals without replacing the normal gameplay level.
     */
    public function test_combines_master_levels_for_rankings_profiles_and_guild_members(): void
    {
        $connection = $this->createGameSchema();
        $connection->table('Character')->where('Name', 'RuneHero')->update(['cLevel' => 3]);
        $connection->table('Character')->where('Name', 'Crusader')->update(['cLevel' => 400]);
        $connection->table('MasterSkillTree')->insert([
            ['Name' => 'StaleName', 'CharacterIndex' => 2, 'MasterLevel' => 400],
            ['Name' => 'RuneHero', 'CharacterIndex' => 3, 'MasterLevel' => -10],
        ]);
        $connection->table('GuildMember')->insert(['Name' => 'RuneHero', 'G_Name' => 'S21Guild']);
        $repository = $this->repository();

        $entries = $repository->topCharacters(3);

        self::assertSame(['RuneHero', 'Crusader', 'Slayer'], array_column($entries, 'name'));
        self::assertSame(3, $entries[0]->level);
        self::assertSame(403, $entries[0]->displayLevel());
        self::assertSame(400, $entries[0]->toArray()['masterLevel']);
        self::assertSame(403, $entries[0]->toArray()['displayLevel']);
        self::assertSame(400, $entries[1]->displayLevel());
        self::assertSame(0, $entries[2]->masterLevel);
        self::assertSame(403, $repository->publicCharacter('RuneHero')->displayLevel());
        self::assertSame(403, $repository->publicGuild('S21Guild')->members[1]->displayLevel());

        $this->app->instance(RealmReadRepository::class, $repository);
        $this->get('/characters/RuneHero')->assertOk()->assertSee('Normal: 3 · Master: 400')->assertSee('403');
        $this->get('/guilds/S21Guild')->assertOk()
            ->assertSee('rankingLevelDetails()', false)
            ->assertSee('Level breakdown')
            ->assertDontSee('Normal: 3 · Master: 400');
        $home = $this->get('/');
        $home->assertOk()
            ->assertSee('rankingLevelDetails()', false)
            ->assertSee('Normal level')
            ->assertDontSee('Normal: 3 · Master: 400');
        $document = new \DOMDocument;
        @$document->loadHTML($home->getContent());
        $xpath = new \DOMXPath($document);

        self::assertSame(0, $xpath->query('//a//button[@x-ref="levelTrigger"]')->length);
        self::assertGreaterThan(0, $xpath->query('//a[contains(@href, "/characters/RuneHero")]')->length);
    }

    /**
     * Creates deterministic public read fixtures in the isolated game connection.
     */
    private function createGameSchema(): ConnectionInterface
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $connection->table('MEMB_INFO')->insert([
            ['memb_guid' => 1, 'memb___id' => 'owner', 'AccountLevel' => 2, 'AccountExpireDate' => '2030-01-01 00:00:00'],
            ['memb_guid' => 2, 'memb___id' => 'other', 'AccountLevel' => 0, 'AccountExpireDate' => '2020-01-01 00:00:00'],
        ]);
        $connection->table('MEMB_STAT')->insert([
            ['memb___id' => 'owner', 'ConnectStat' => 1],
            ['memb___id' => 'other', 'ConnectStat' => 0],
        ]);
        $connection->table('Character')->insert([
            ['AccountID' => 'owner', 'Name' => 'Crusader', 'CharacterIndex' => 1, 'Class' => 240, 'cLevel' => 1000, 'ResetCount' => 5, 'MasterResetCount' => 0, 'MapNumber' => 145, 'PkCount' => 4, 'PkLevel' => 3],
            ['AccountID' => 'owner', 'Name' => 'RuneHero', 'CharacterIndex' => 2, 'Class' => 128, 'cLevel' => 900, 'ResetCount' => 5, 'MasterResetCount' => 0, 'MapNumber' => 141, 'PkCount' => 9, 'PkLevel' => 4],
            ['AccountID' => 'other', 'Name' => 'Slayer', 'CharacterIndex' => 3, 'Class' => 144, 'cLevel' => 800, 'ResetCount' => 4, 'MasterResetCount' => 0, 'MapNumber' => 144, 'PkCount' => 1, 'PkLevel' => 1],
        ]);
        $connection->table('Guild')->insert([
            'G_Name' => 'S21Guild', 'G_Master' => 'Crusader', 'G_Score' => 10, 'G_Count' => 1,
            'G_Notice' => 'S21', 'G_Mark' => hex2bin(str_repeat('11', 32)),
        ]);
        $connection->table('GuildMember')->insert(['Name' => 'Crusader', 'G_Name' => 'S21Guild']);

        return $connection;
    }

    /**
     * Builds the isolated S21 public reader with its own class, map and mark adapters.
     */
    private function repository(): SqlServerMuDevsSeason21RealmReadRepository
    {
        return new SqlServerMuDevsSeason21RealmReadRepository(
            $this->app->make(DatabaseManager::class),
            new MuDevsSeason21Profile,
            new GuildMarkDecoderMuDevsSeason21,
            new MapNameMapperMuDevsSeason21,
        );
    }
}
