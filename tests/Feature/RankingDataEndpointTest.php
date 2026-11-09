<?php

namespace Tests\Feature;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Contracts\GensMembershipRepository;
use App\Game\Data\CastleSiegeState;
use App\Game\Data\GensFamily;
use App\Game\Data\GensRankingEntry;
use App\Game\Data\GensRankingPage;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuEmuSeason6\MuEmuSeason6Profile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use DateTimeImmutable;
use DateTimeZone;
use Tests\TestCase;

/**
 * Verifies server-side filtering, ordering and pagination for public rankings.
 */
final class RankingDataEndpointTest extends TestCase
{
    /**
     * Confirms character pages use absolute ranks across page boundaries.
     */
    public function test_overall_ranking_uses_real_server_side_pagination(): void
    {
        $response = $this->getJson(route('rankings.data', [
            'board' => 'overall',
            'per_page' => 2,
            'page' => 2,
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('board', 'overall')
            ->assertJsonPath('meta.current_page', 2)
            ->assertJsonPath('meta.last_page', 2)
            ->assertJsonPath('meta.total', 4)
            ->assertJsonPath('items.0.rank', 3)
            ->assertJsonPath('items.0.name', 'EvilDeviL');
    }

    /**
     * Confirms exact class codes are filtered before pagination.
     */
    public function test_character_ranking_filters_by_version_class_badge(): void
    {
        $response = $this->getJson(route('rankings.data', [
            'board' => 'overall',
            'class' => 'bk',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'KoPeH')
            ->assertJsonPath('items.0.class_abbreviation', 'BK');
    }

    /**
     * Confirms the PK board uses kill count rather than reset progression.
     */
    public function test_pk_board_has_independent_competitive_ordering(): void
    {
        $response = $this->getJson(route('rankings.data', ['board' => 'pk']));

        $response
            ->assertOk()
            ->assertJsonPath('board', 'pk')
            ->assertJsonPath('items.0.name', 'EvilDeviL')
            ->assertJsonPath('items.0.pk_count', 21);
    }

    /**
     * Confirms guild searches include both guild names and masters.
     */
    public function test_guild_board_searches_master_names(): void
    {
        $response = $this->getJson(route('rankings.data', [
            'board' => 'guilds',
            'search' => 'iwebbg',
        ]));

        $response
            ->assertOk()
            ->assertJsonCount(1, 'items')
            ->assertJsonPath('items.0.name', 'Immortal')
            ->assertJsonPath('items.0.master', 'iwebbg');
    }

    /**
     * Confirms the 1.0M board exposes the authoritative current castle owner.
     */
    public function test_castle_siege_board_returns_current_owner_for_supported_profile(): void
    {
        $this->app->instance(GameVersionProfile::class, new Version1_0MProfile);
        $this->app->instance(CastleSiegeReadRepository::class, new class implements CastleSiegeReadRepository
        {
            /**
             * Returns one occupied cycle for the public ranking response.
             */
            public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState
            {
                return new CastleSiegeState(
                    mapServerGroup: $mapServerGroup,
                    cycleStartsAt: new DateTimeImmutable('2026-11-01 00:00:00', $timezone),
                    cycleEndsAt: new DateTimeImmutable('2026-11-15 00:00:00', $timezone),
                    siegeEnded: false,
                    castleOccupied: true,
                    ownerGuild: 'Legends',
                );
            }
        });

        $response = $this->getJson(route('rankings.data', [
            'board' => 'castle_siege',
        ]));

        $response
            ->assertOk()
            ->assertJsonPath('board', 'castle_siege')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('items.0.name', 'Legends')
            ->assertJsonPath('items.0.master', 'KoPeH');
    }

    /**
     * Confirms the Season 6 profile exposes normalized public Gens standings.
     */
    public function test_gens_board_returns_family_contribution_and_rank(): void
    {
        $this->app->instance(GameVersionProfile::class, new MuEmuSeason6Profile);
        $gensRepository = $this->mock(GensMembershipRepository::class);
        $gensRepository
            ->shouldReceive('rankings')
            ->once()
            ->andReturn(new GensRankingPage(
                entries: [new GensRankingEntry(
                    name: 'GensHero',
                    classCode: 17,
                    className: 'Blade Knight',
                    classAbbreviation: 'BK',
                    image: '/images/characters/blade-knight.png',
                    family: GensFamily::Duprian,
                    contribution: 25_000,
                    familyRank: 3,
                    online: true,
                )],
                total: 1,
                page: 1,
                perPage: 12,
                lastPage: 1,
            ));

        $response = $this->getJson(route('rankings.data', ['board' => 'gens']));

        $response
            ->assertOk()
            ->assertJsonPath('board', 'gens')
            ->assertJsonPath('items.0.name', 'GensHero')
            ->assertJsonPath('items.0.family', 'duprian')
            ->assertJsonPath('items.0.family_name', 'Duprian')
            ->assertJsonPath('items.0.contribution', 25_000)
            ->assertJsonPath('items.0.family_rank', 3);
    }

    /**
     * Confirms unsupported boards and class values never reach repositories.
     */
    public function test_invalid_ranking_filters_are_rejected(): void
    {
        $this->getJson(route('rankings.data', [
            'board' => 'arena',
            'class' => 'summoner',
        ]))->assertUnprocessable();
    }

    /**
     * Confirms Castle Siege cannot be queried through profiles without that subsystem.
     */
    public function test_castle_siege_board_is_rejected_for_unsupported_profile(): void
    {
        $this->getJson(route('rankings.data', [
            'board' => 'castle_siege',
        ]))->assertUnprocessable();

        $this->getJson(route('rankings.data', [
            'board' => 'gens',
        ]))->assertUnprocessable();
    }
}
