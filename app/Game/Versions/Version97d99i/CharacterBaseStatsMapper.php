<?php

namespace App\Game\Versions\Version97d99i;

use InvalidArgumentException;

/**
 * Maps verified 97d+99i class codes to their original level-one attributes.
 */
final class CharacterBaseStatsMapper
{
    /**
     * Returns the base stats shared by base and evolved classes in one family.
     *
     * @throws InvalidArgumentException When the legacy class code is unsupported.
     */
    public function forClass(int $classCode): CharacterBaseStats
    {
        return match ($classCode) {
            0, 1 => new CharacterBaseStats(
                strength: 18,
                agility: 18,
                vitality: 15,
                energy: 30,
            ),
            16, 17 => new CharacterBaseStats(
                strength: 28,
                agility: 20,
                vitality: 25,
                energy: 10,
            ),
            32, 33 => new CharacterBaseStats(
                strength: 22,
                agility: 25,
                vitality: 20,
                energy: 15,
            ),
            48 => new CharacterBaseStats(
                strength: 26,
                agility: 26,
                vitality: 26,
                energy: 26,
            ),
            default => throw new InvalidArgumentException(
                sprintf('Unsupported 97d+99i character class code: %d.', $classCode),
            ),
        };
    }
}
