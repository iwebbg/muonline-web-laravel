<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Maps 97d+99i class codes to the available account portrait artwork.
 */
final class CharacterImageMapper
{
    /**
     * Returns the public portrait path for one Character.Class value.
     */
    public function pathFor(int $classCode): string
    {
        return match ($classCode) {
            0, 1 => '/images/characters/soul-master.jpg',
            16, 17 => '/images/characters/blade-knight.png',
            32, 33 => '/images/characters/muse-elf.png',
            48 => '/images/characters/magic-gladiator.png',
            default => '/images/characters/blade-knight.png',
        };
    }
}
