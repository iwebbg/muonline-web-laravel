<?php

namespace Tests\Unit;

use App\Game\Versions\MuEmuSeason6\ItemGeneratorOptionsMuEmuSeason6;
use PHPUnit\Framework\TestCase;

/**
 * Verifies generator compatibility against the supplied MuEMU Update 32 tables.
 */
final class ItemGeneratorOptionsMuEmuSeason6Test extends TestCase
{
    /**
     * Confirms representative Ancient, 380, Harmony and socket items expose exact capabilities.
     */
    public function test_returns_item_specific_update_32_metadata(): void
    {
        $options = new ItemGeneratorOptionsMuEmuSeason6;

        $ancient = $options->metadataFor(8, 1);
        $option380 = $options->metadataFor(0, 22);
        $harmony = $options->metadataFor(0, 0);
        $socket = $options->metadataFor(5, 30);

        $this->assertSame([5, 10], $ancient['ancientOptions']);
        $this->assertTrue($option380['allows380']);
        $this->assertSame('weapon', $harmony['harmonyType']);
        $this->assertSame(3, $socket['maxSockets']);
    }

    /**
     * Confirms invalid cross-item and shared-byte combinations are rejected.
     */
    public function test_rejects_options_not_supported_by_the_exact_item(): void
    {
        $options = new ItemGeneratorOptionsMuEmuSeason6;

        $this->assertFalse($options->optionsAreAllowed(0, 0, [
            'ancient_option' => 5,
            'harmony_option' => 0,
            'socket_count' => 0,
            'has_380_option' => false,
        ]));
        $this->assertFalse($options->optionsAreAllowed(5, 30, [
            'ancient_option' => 0,
            'harmony_option' => 0x1F,
            'socket_count' => 1,
            'has_380_option' => false,
        ]));
        $this->assertFalse($options->optionsAreAllowed(5, 30, [
            'ancient_option' => 0,
            'harmony_option' => 0,
            'socket_count' => 4,
            'has_380_option' => false,
        ]));
    }
}
