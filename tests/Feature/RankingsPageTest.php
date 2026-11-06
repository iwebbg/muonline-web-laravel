<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the public interactive ranking explorer and navigation entry points.
 */
final class RankingsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

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
            ->assertSee('Top PK')
            ->assertSee('Filter by class')
            ->assertSee('BK')
            ->assertSee('pageNumbers', false)
            ->assertSee('Online only')
            ->assertViewHas('rankingPage', function (array $rankingPage): bool {
                return $rankingPage['items'][0]['image'] === '/images/characters/blade-knight.png'
                    && $rankingPage['items'][0]['class_abbreviation'] === 'BK';
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
            ->assertSee('Топ PK')
            ->assertSee('Гилдии');
    }
}
