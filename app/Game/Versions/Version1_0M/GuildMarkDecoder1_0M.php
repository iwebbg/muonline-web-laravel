<?php

namespace App\Game\Versions\Version1_0M;

/**
 * Decodes the native 1.0M 8×8 guild emblem into safe display colors.
 */
final class GuildMarkDecoder1_0M
{
    /** @var array<int, string> */
    private const COLORS = [
        0x0 => '#666666',
        0x1 => '#000000',
        0x2 => '#8c8a8d',
        0x3 => '#ffffff',
        0x4 => '#fe0000',
        0x5 => '#ff8a00',
        0x6 => '#ffff00',
        0x7 => '#8cff01',
        0x8 => '#00ff00',
        0x9 => '#01ff8d',
        0xA => '#00ffff',
        0xB => '#008aff',
        0xC => '#0000fe',
        0xD => '#8c00ff',
        0xE => '#ff00fe',
        0xF => '#ff008c',
    ];

    /**
     * Returns exactly 64 colors from the packed 32-byte guild mark.
     *
     * Missing bytes are treated as palette index zero so malformed legacy
     * records still produce a bounded, safe emblem.
     *
     * @return list<string>
     */
    public function colors(string $binaryMark): array
    {
        $hexadecimalPixels = str_pad(
            substr(bin2hex($binaryMark), 0, 64),
            64,
            '0',
        );
        $colors = [];

        foreach (str_split($hexadecimalPixels) as $hexadecimalPixel) {
            $paletteIndex = hexdec($hexadecimalPixel);
            $colors[] = self::COLORS[$paletteIndex];
        }

        return $colors;
    }
}
