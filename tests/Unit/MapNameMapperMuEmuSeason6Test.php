<?php

namespace Tests\Unit;

use App\Game\Versions\MuEmuSeason6\MapNameMapperMuEmuSeason6;
use PHPUnit\Framework\TestCase;

/**
 * Verifies map labels against the supplied MuEMU Update 32 MapManager data.
 */
final class MapNameMapperMuEmuSeason6Test extends TestCase
{
    /**
     * Confirms classic and Season 6 maps use their native labels.
     */
    public function test_returns_verified_update_32_map_names(): void
    {
        $mapper = new MapNameMapperMuEmuSeason6;

        self::assertSame('Lorencia', $mapper->nameFor(0));
        self::assertSame('Blood Castle 8', $mapper->nameFor(52));
        self::assertSame('Swamp of Calmness', $mapper->nameFor(56));
        self::assertSame('Raklion', $mapper->nameFor(57));
        self::assertSame('Karutan 2', $mapper->nameFor(81));
    }

    /**
     * Confirms unverified custom map slots retain a safe numeric fallback.
     */
    public function test_unknown_map_number_returns_numeric_fallback(): void
    {
        $mapper = new MapNameMapperMuEmuSeason6;

        self::assertSame('Map 99', $mapper->nameFor(99));
    }
}
