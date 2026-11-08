<?php

namespace Tests\Unit;

use App\Game\Versions\Version1_0M\GuildMarkDecoder1_0M;
use App\Game\Versions\Version1_0M\MapNameMapper1_0M;
use PHPUnit\Framework\TestCase;

/**
 * Verifies small display helpers owned by the isolated DarksTeam 1.0M adapter.
 */
final class Version1_0MSupportTest extends TestCase
{
    /**
     * Confirms event and castle maps use the numbers present in the 1.0M gate data.
     */
    public function test_maps_legacy_event_and_castle_numbers(): void
    {
        $mapper = new MapNameMapper1_0M;

        $this->assertSame('Devil Square', $mapper->nameFor(9));
        $this->assertSame('Blood Castle 7', $mapper->nameFor(17));
        $this->assertSame('Chaos Castle 6', $mapper->nameFor(23));
        $this->assertSame('Kalima 6', $mapper->nameFor(29));
        $this->assertSame('Valley of Loren', $mapper->nameFor(30));
        $this->assertSame('Land of Trials', $mapper->nameFor(31));
        $this->assertSame('Devil Square', $mapper->nameFor(32));
        $this->assertSame('Map 99', $mapper->nameFor(99));
    }

    /**
     * Confirms packed guild pixels decode to the fixed 1.0M palette and length.
     */
    public function test_decodes_exactly_sixty_four_guild_mark_pixels(): void
    {
        $colors = (new GuildMarkDecoder1_0M)->colors("\x01\x23\x45\x67\x89\xAB\xCD\xEF");

        $this->assertCount(64, $colors);
        $this->assertSame([
            '#666666',
            '#000000',
            '#8c8a8d',
            '#ffffff',
            '#fe0000',
            '#ff8a00',
            '#ffff00',
            '#8cff01',
            '#00ff00',
            '#01ff8d',
            '#00ffff',
            '#008aff',
            '#0000fe',
            '#8c00ff',
            '#ff00fe',
            '#ff008c',
        ], array_slice($colors, 0, 16));
        $this->assertSame(array_fill(0, 48, '#666666'), array_slice($colors, 16));
    }
}
