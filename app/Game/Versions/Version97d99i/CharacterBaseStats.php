<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Carries the original 97d+99i level-one attributes for one class family.
 */
final readonly class CharacterBaseStats
{
    /**
     * Creates an immutable set of class-specific base attributes.
     */
    public function __construct(
        public int $strength,
        public int $agility,
        public int $vitality,
        public int $energy,
    ) {}
}
