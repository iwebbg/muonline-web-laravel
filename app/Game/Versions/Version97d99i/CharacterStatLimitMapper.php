<?php

namespace App\Game\Versions\Version97d99i;

use InvalidArgumentException;

/**
 * Resolves the DarksTeam 97d+99i Character.ini maximum attribute value.
 */
final class CharacterStatLimitMapper
{
    /**
     * Returns the configured maximum for every supported 97d+99i class code.
     *
     * @throws InvalidArgumentException When the legacy class code is unsupported.
     */
    public function maximumForClass(int $classCode): int
    {
        return match ($classCode) {
            0, 1, 16, 17, 32, 33, 48 => 32_767,
            default => throw new InvalidArgumentException(
                sprintf('Unsupported 97d+99i character class code: %d.', $classCode),
            ),
        };
    }
}
