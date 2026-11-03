<?php

namespace Tests\Unit;

use App\Game\Versions\Version97d99i\GuildMarkDecoder;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the legacy 97d+99i guild-mark palette and bounds.
 */
final class GuildMarkDecoderTest extends TestCase
{
    /**
     * Confirms each four-bit mark pixel maps to the original game palette.
     */
    public function test_decoder_maps_packed_nibbles_to_safe_colors(): void
    {
        $binaryMark = hex2bin('0123456789abcdef'.str_repeat('1', 48));
        $decoder = new GuildMarkDecoder;
        $colors = $decoder->colors(is_string($binaryMark) ? $binaryMark : '');

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
    }

    /**
     * Confirms truncated legacy values remain a fixed-size neutral emblem.
     */
    public function test_decoder_pads_truncated_marks_to_eight_by_eight(): void
    {
        $colors = (new GuildMarkDecoder)->colors('');

        $this->assertCount(64, $colors);
        $this->assertSame(array_fill(0, 64, '#666666'), $colors);
    }
}
