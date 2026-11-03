<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Maps 97d+99i legacy class codes to stable public class names.
 */
final class CharacterClassMapper
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
     * Returns the class name represented by one legacy Character.Class value.
     */
    public function nameFor(int $classCode): string
    {
        return $this->profile->classForCode($classCode)?->name
            ?? sprintf('Unknown class (%d)', $classCode);
    }
}
