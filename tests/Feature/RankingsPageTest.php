<?php

namespace Tests\Feature;

use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
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
                return $rankingPage['items'][0]['image'] === '/images/characters/muonline-web-dark-knight.png'
                    && $rankingPage['items'][0]['class_abbreviation'] === 'BK';
            });
    }

    /**
     * Confirms the 1.0M explorer excludes the Season 3 Lord Emperor evolution.
     */
    public function test_1_0m_rankings_expose_dark_lord_without_lord_emperor(): void
    {
        $this->app->instance(GameVersionProfile::class, new Version1_0MProfile);

        $response = $this->get('/rankings');

        $response
            ->assertOk()
            ->assertSee('Dark Lord')
            ->assertSee('DL')
            ->assertSee('Castle Siege')
            ->assertDontSee('Lord Emperor')
            ->assertDontSee('>LE<', escape: false);
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

    /**
     * Keeps native S21 reset terminology separate from legacy adapter labels.
     */
    #[DataProvider('resetTerminologyProfiles')]
    public function test_reset_labels_follow_the_profile_without_changing_internal_keys(
        string $profile,
        string $locale,
        string $resetLabel,
        string $rankingLabel,
        string $adminHeading,
        string $completedMessage,
    ): void {
        config()->set('game.profile.key', $profile);

        $this->withSession(['locale' => $locale])
            ->get('/rankings')
            ->assertOk()
            ->assertSee('>'.$rankingLabel.'</span>', false)
            ->assertSee('entry.grand_resets', false);

        $this->assertSame($resetLabel, __('ui.account.grand_reset'));
        $this->assertSame($adminHeading, __('ui.admin.grand_reset_profile'));
        $this->assertSame($completedMessage, __('ui.account.grand_reset_completed'));
    }

    /**
     * Keeps level details accessible without nesting controls in profile links.
     */
    public function test_level_breakdown_uses_an_accessible_panel_instead_of_inline_text(): void
    {
        $response = $this->get('/rankings');

        $response->assertOk()
            ->assertSee('rankingLevelDetails()', false)
            ->assertSee('role="tooltip"', false)
            ->assertSee('aria-describedby', false)
            ->assertSee('Level breakdown')
            ->assertSee('Normal level')
            ->assertDontSee("replace(':normal', entry.level)", false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $xpath = new \DOMXPath($document);

        $this->assertSame(0, $xpath->query('//a//button[@x-ref="levelTrigger"]')->length);
    }

    /**
     * Supplies both locales for every currently supported game adapter.
     *
     * @return array<string, array{string, string, string, string, string, string}>
     */
    public static function resetTerminologyProfiles(): array
    {
        $profiles = [];

        foreach (['darksteam_97d99i', 'darksteam_1_0m', 'muemu_season_6'] as $profile) {
            $profiles[$profile.' English'] = [$profile, 'en', 'Grand reset', 'grand', 'Grand reset profile', 'Character grand reset completed successfully.'];
            $profiles[$profile.' Bulgarian'] = [$profile, 'bg', 'Гранд ресет', 'гранд', 'Grand Reset профил', 'Гранд ресетът на героя завърши успешно.'];
        }

        $profiles['S21 English'] = ['mudevs_season_21', 'en', 'Master reset', 'master', 'Master reset profile', 'Character master reset completed successfully.'];
        $profiles['S21 Bulgarian'] = ['mudevs_season_21', 'bg', 'Мастър ресет', 'мастър', 'Master Reset профил', 'Мастър ресетът на героя завърши успешно.'];

        return $profiles;
    }
}
