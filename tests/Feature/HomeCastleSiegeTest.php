<?php

namespace Tests\Feature;

use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Data\CastleSiegeState;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use DateTimeImmutable;
use DateTimeZone;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies the public Castle Siege schedule for supported game versions.
 */
final class HomeCastleSiegeTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms guests see the database-anchored battle and its profile recurrence.
     */
    public function test_home_page_displays_castle_siege_for_the_1_0m_profile(): void
    {
        config()->set('game.event_profile', 'darksteam_1_0m');
        config()->set('game.profile.version', '0.99.60/62T (1.0M)');
        $this->app->instance(GameVersionProfile::class, new Version1_0MProfile);
        $this->app->instance(CastleSiegeReadRepository::class, new class implements CastleSiegeReadRepository
        {
            /**
             * Returns one deterministic 1.0M Castle Siege cycle.
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
        $this->travelTo(new DateTimeImmutable('2026-11-08T10:00:00+02:00'));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Castle Siege');
        $response->assertSee('Valley of Loren');
        $response->assertSee('Every 14 days');
        $response->assertSee('2026-11-21T15:00:00+02:00', escape: false);
    }
}
