<?php

namespace Tests\Feature;

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
     * Confirms unsupported boards and class values never reach repositories.
     */
    public function test_invalid_ranking_filters_are_rejected(): void
    {
        $this->getJson(route('rankings.data', [
            'board' => 'arena',
            'class' => 'summoner',
        ]))->assertUnprocessable();
    }
}
