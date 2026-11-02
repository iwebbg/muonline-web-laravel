<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Maps 97d+99i legacy class codes to stable public class names.
 */
final class CharacterClassMapper
{
    /**
     * Returns the class name represented by one legacy Character.Class value.
     */
    public function nameFor(int $classCode): string
    {
        return match ($classCode) {
            0 => 'Dark Wizard',
            1 => 'Soul Master',
            16 => 'Dark Knight',
            17 => 'Blade Knight',
            32 => 'Fairy Elf',
            33 => 'Muse Elf',
            48 => 'Magic Gladiator',
            default => sprintf('Unknown class (%d)', $classCode),
        };
    }
}
