<?php

namespace Tests\Unit\CastleSiege;

use App\CastleSiege\CastleSiegeEventProvider;
use App\Game\Contracts\CastleSiegeReadRepository;
use App\Game\Data\CastleSiegeState;
use App\Game\Versions\Version1_0M\Version1_0MProfile;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use DateTimeImmutable;
use DateTimeZone;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies the DarksTeam Castle Siege phase timing against authoritative cycle dates.
 */
final class CastleSiegeEventProviderTest extends TestCase
{
    /**
     * Confirms the 1.0M phase seed derives a fourteen-day battle schedule from SQL dates.
     */
    public function test_returns_previous_and_future_battles_from_cycle_anchor(): void
    {
        config()->set('game.event_profile', 'darksteam_1_0m');
        $provider = new CastleSiegeEventProvider(
            castleSiegeReadRepository: $this->repositoryWithState(new CastleSiegeState(
                mapServerGroup: 0,
                cycleStartsAt: new DateTimeImmutable('2026-11-01T00:00:00+02:00'),
                cycleEndsAt: new DateTimeImmutable('2026-11-15T00:00:00+02:00'),
                siegeEnded: false,
                castleOccupied: true,
                ownerGuild: 'Legends',
            )),
            gameVersionProfile: new Version1_0MProfile,
        );

        $event = $provider->upcoming(new DateTimeImmutable('2026-11-08T10:00:00+02:00'));

        $this->assertSame('castle-siege', $event?->key);
        $this->assertSame('2026-11-07T15:00:00+02:00', $event?->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-21T15:00:00+02:00', $event?->nextOccurrence()->format(DATE_ATOM));
        $this->assertSame('2026-12-05T15:00:00+02:00', $event?->occurrences[1]->format(DATE_ATOM));
        $this->assertSame(['15:00'], $event?->dailyTimes);
        $this->assertSame(120, $event?->durationMinutes);
        $this->assertSame(14, $event?->cycleDays);
    }

    /**
     * Confirms a time before the configured battle still receives the prior and next cycles.
     */
    public function test_wraps_to_the_previous_cycle_before_the_first_battle_anchor(): void
    {
        config()->set('game.event_profile', 'darksteam_1_0m');
        $timezone = new DateTimeZone('Europe/Sofia');
        $provider = new CastleSiegeEventProvider(
            castleSiegeReadRepository: $this->repositoryWithState(new CastleSiegeState(
                mapServerGroup: 0,
                cycleStartsAt: new DateTimeImmutable('2026-11-01 00:00:00', $timezone),
                cycleEndsAt: new DateTimeImmutable('2026-11-15 00:00:00', $timezone),
                siegeEnded: true,
                castleOccupied: false,
                ownerGuild: null,
            )),
            gameVersionProfile: new Version1_0MProfile,
        );

        $event = $provider->upcoming(new DateTimeImmutable('2026-11-05 10:00:00', $timezone));

        $this->assertSame('2026-10-24T15:00:00+03:00', $event?->previousOccurrence->format(DATE_ATOM));
        $this->assertSame('2026-11-07T15:00:00+02:00', $event?->nextOccurrence()->format(DATE_ATOM));
    }

    /**
     * Confirms 97d+99i never probes a Castle Siege table it does not own.
     */
    public function test_unsupported_profile_skips_the_repository_read(): void
    {
        config()->set('game.event_profile', 'darksteam_1_0m');
        $repository = new class implements CastleSiegeReadRepository
        {
            /**
             * Fails if the unsupported profile attempts an external read.
             */
            public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState
            {
                throw new RuntimeException('The 97d profile must not read Castle Siege state.');
            }
        };
        $provider = new CastleSiegeEventProvider(
            castleSiegeReadRepository: $repository,
            gameVersionProfile: new Version97d99iProfile,
        );

        $event = $provider->upcoming(new DateTimeImmutable('2026-11-08T10:00:00+02:00'));

        $this->assertNull($event);
    }

    /**
     * Creates a deterministic read-only repository for one cycle state.
     */
    private function repositoryWithState(CastleSiegeState $state): CastleSiegeReadRepository
    {
        return new class($state) implements CastleSiegeReadRepository
        {
            /**
             * Creates the fake with one immutable database result.
             */
            public function __construct(
                private readonly CastleSiegeState $state,
            ) {}

            /**
             * Returns the prepared cycle for the requested map server group.
             */
            public function state(int $mapServerGroup, DateTimeZone $timezone): ?CastleSiegeState
            {
                return $mapServerGroup === $this->state->mapServerGroup
                    ? $this->state
                    : null;
            }
        };
    }
}
