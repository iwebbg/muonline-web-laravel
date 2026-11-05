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
            2 => 'Davias',
            3 => 'Noria',
            4 => 'Losttower',
            5 => 'A Place of Exile',
            6 => 'Stadium',
            7 => 'Atlans',
            8 => 'Tarkan',
            10 => 'Icarus',
            11 => 'Blood Castle 1',
            12 => 'Blood Castle 2',
            13 => 'Blood Castle 3',
            14 => 'Blood Castle 4',
            15 => 'Blood Castle 5',
            16 => 'Blood Castle 6',
            30 => 'Devil Square',
            default => sprintf('Map %d', $mapNumber),
        };
    }
}
