<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Verifies the public interactive ranking explorer and navigation entry points.
 */
final class RankingsPageTest extends TestCase
{
    /**
     * Confirms guests can browse version-normalized player and guild rankings.
     */
    public function test_guest_can_open_interactive_rankings(): void
    {
        $response = $this->get('/rankings');

        $response
            ->assertOk()
            ->assertSee('Every legend has a place.')
            ->assertSee('rankingExplorer(', false)
            ->assertSee('KoPeH')
            ->assertSee('Blade Knight')
            ->assertSee('Legends')
            ->assertSee('guild.mark_colors', false)
            ->assertSee('Online only')
            ->assertViewHas('rankings', function (array $rankings): bool {
                return $rankings['players'][0]['image'] === '/images/characters/blade-knight.png'
                    && count($rankings['guilds'][0]['mark_colors']) === 64;
            });
    }

    /**
     * Confirms desktop, mobile and hero links use the dedicated ranking route.
     */
    public function test_home_navigation_links_to_rankings_page(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('href="http://localhost/rankings"', false);
    }

    /**
     * Confirms the ranking explorer is fully translated in Bulgarian.
     */
    public function test_rankings_support_bulgarian_interface(): void
    {
        $response = $this
            ->withSession(['locale' => 'bg'])
            ->get('/rankings');

        $response
            ->assertOk()
            ->assertSee('Всяка легенда има своето място.')
            ->assertSee('Само онлайн')
            ->assertSee('Гилдии');
    }
}
