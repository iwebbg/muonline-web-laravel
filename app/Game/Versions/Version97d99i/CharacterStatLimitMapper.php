<?php

namespace App\Game\Versions\Version97d99i;

use InvalidArgumentException;

/**
 * Resolves the DarksTeam 97d+99i Character.ini maximum attribute value.
 */
final class CharacterStatLimitMapper
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
     * Returns the configured maximum for every supported 97d+99i class code.
     *
     * @throws InvalidArgumentException When the legacy class code is unsupported.
     */
    public function maximumForClass(int $classCode): int
    {
        $statDefinitions = $this->profile->statsForClass($classCode);
        $maximumValues = array_map(
            static fn ($statDefinition): int => $statDefinition->maximum,
            $statDefinitions,
        );

        if ($maximumValues === []) {
            throw new InvalidArgumentException(
                sprintf('Unsupported 97d+99i character class code: %d.', $classCode),
            );
        }

        return min($maximumValues);
    }
}
