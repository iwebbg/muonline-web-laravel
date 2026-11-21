<?php

namespace Tests\Feature;

use App\Game\Versions\Contracts\GameVersionProfile;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies full schedules live on a dedicated page while home previews stay short.
 */
final class EventsPageTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Exposes every active profile's events without expanding the home page.
     */
    #[DataProvider('eventProfiles')]
    public function test_home_links_to_the_complete_profile_schedule(string $profile): void
    {
        $this->travelTo('2026-11-16 12:00:00 Europe/Sofia');
        config()->set('game.event_profile', $profile);
        config()->set('game.profile.key', $profile);
        config()->set('game.event_home_limit', 2);
        $profileClass = config('game.version_profiles.'.$profile);
        $this->app->instance(GameVersionProfile::class, new $profileClass);

        $home = $this->get('/');
        $page = $this->get('/events');

        $home->assertOk()->assertSee(route('events.index'), false)
            ->assertDontSee('full-event-schedule', false)
            ->assertDontSee('Dungeons and seasonal activities')
            ->assertDontSee('toggleSchedule', false)
            ->assertViewMissing('allEvents')
            ->assertViewMissing('eventActivities');
        $page->assertOk()->assertSee('Events and activities')
            ->assertSee('Server event schedule')
            ->assertSee('Europe/Sofia')
            ->assertSee('eventSchedule(', false);
        $homeEvents = $home->viewData('events');
        $allEvents = $page->viewData('events');

        $this->assertCount(2, $homeEvents);
        $this->assertGreaterThan(count($homeEvents), count($allEvents));
        $this->assertSame(array_slice($allEvents, 0, 2), $homeEvents);
        $this->assertSame(count($allEvents) - 2, $home->viewData('additionalEventCount'));
        $home->assertSee('View '.(count($allEvents) - 2).' more');

        foreach ($allEvents as $event) {
            $page->assertSee($event['name']);
        }

        foreach ($page->viewData('eventActivities') as $activity) {
            $page->assertSee($activity->name);
            $home->assertDontSee($activity->description);
        }
    }

    /**
     * Keeps the public events page useful when the profile has no scheduled entries.
     */
    public function test_empty_schedule_is_explained_in_bulgarian(): void
    {
        config()->set('game_events.profiles.darksteam_97d99i.events', []);

        $this->withSession(['locale' => 'bg'])->get('/events')
            ->assertOk()
            ->assertSee('Събития и активности')
            ->assertSee('В момента няма събития с обявен график.')
            ->assertViewHas('events', []);
    }

    /**
     * Supplies every adapter whose event profile uses the shared public views.
     *
     * @return array<string, array{string}>
     */
    public static function eventProfiles(): array
    {
        return [
            '97d+99i' => ['darksteam_97d99i'],
            '1.0M' => ['darksteam_1_0m'],
            'Season 6' => ['muemu_season_6'],
            'Season 21' => ['mudevs_season_21'],
        ];
    }
}
