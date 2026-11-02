<?php

namespace App\Game\Versions\Version97d99i;

/**
 * Maps 97d+99i legacy map numbers to stable display names.
 */
final class MapNameMapper
{
    /**
     * Returns the map name represented by one Character.MapNumber value.
     */
    public function nameFor(int $mapNumber): string
    {
        return match ($mapNumber) {
            0 => 'Lorencia',
            1 => 'Dungeon',
            2 => 'Devias',
            3 => 'Noria',
            4 => 'Lost Tower',
            6 => 'Arena',
            7 => 'Atlans',
            8 => 'Tarkan',
            9 => 'Devil Square',
            10 => 'Icarus',
            11 => 'Blood Castle',
            default => sprintf('Map %d', $mapNumber),
        };
    }
}
