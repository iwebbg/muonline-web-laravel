<?php

namespace App\Game\Versions\Version97d99i;

use InvalidArgumentException;

/**
 * Maps verified 97d+99i class codes to their original level-one attributes.
 */
final class CharacterBaseStatsMapper
{
    private readonly Version97d99iProfile $profile;

    /**
     * Creates the compatibility mapper over the canonical 97d+99i profile.
     */
    public function __construct(?Version97d99iProfile $profile = null)
    {
        $this->profile = $profile ?? new Version97d99iProfile;
    }

    /**
     * Returns the base stats shared by base and evolved classes in one family.
     *
     * @throws InvalidArgumentException When the legacy class code is unsupported.
     */
    public function forClass(int $classCode): CharacterBaseStats
    {
        $stats = $this->profile->baseStatsForClass($classCode);

        return new CharacterBaseStats(
            strength: $stats->strength,
            agility: $stats->agility,
            vitality: $stats->vitality,
            energy: $stats->energy,
        );
    }
}
