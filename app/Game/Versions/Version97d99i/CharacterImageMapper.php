<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Maps 97d+99i class codes to the available account portrait artwork.
 */
final class CharacterImageMapper
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
     * Returns the public portrait path for one Character.Class value.
     */
    public function pathFor(int $classCode): string
    {
        return $this->profile->classForCode($classCode)?->image
            ?? '/images/characters/blade-knight.png';
    }
}
