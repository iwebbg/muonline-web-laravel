<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Verifies the public event profile mirrors only stock MuEMU schedules.
 */
final class MuEmuSeason6EventProfileTest extends TestCase
{
    /**
     * Confirms exact server-file anchors and the exclusion of custom events.
     */
    public function test_exposes_only_standard_muemu_season_6_events(): void
    {
        $profile = config('game_events.profiles.muemu_season_6');

        self::assertIsArray($profile);

        $events = collect($profile['events'])->keyBy('key');

        self::assertCount(12, $events);
        self::assertSame(120, $events['blood-castle']['interval_minutes']);
        self::assertSame('00:30', $events['devil-square']['interval_anchor']);
        self::assertSame(240, $events['illusion-temple']['interval_minutes']);
        self::assertSame([3, 6], $events['crywolf-fortress']['days_of_week']);
        self::assertSame(
            ['02:00', '08:00', '14:00', '20:00'],
            $events['moss-merchant']['times'],
        );

        foreach ([
            'battle-royale',
            'guild-vs-guild',
            'team-vs-team',
            'marathon',
            'king-of-mu',
            'quickly-event',
            'hide-and-seek',
            'russian-roulette',
            'kill-all',
            'pvp-championship',
            'auction-event',
        ] as $customEvent) {
            self::assertArrayNotHasKey($customEvent, $events);
        }
    }

    /**
     * Confirms Castle Siege follows the seven-day Update 32 state table.
     */
    public function test_exposes_muemu_castle_siege_cycle(): void
    {
        $castleSiege = config('game_events.profiles.muemu_season_6.castle_siege');

        self::assertSame(7, $castleSiege['cycle_days']);
        self::assertSame(6, $castleSiege['battle_day']);
        self::assertSame('20:00', $castleSiege['battle_time']);
        self::assertSame(120, $castleSiege['duration_minutes']);
    }
}
