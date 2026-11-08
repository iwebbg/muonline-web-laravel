<?php

namespace App\Game\Versions\Version1_0M;

/**
 * Maps 1.0M legacy map numbers to stable display names.
 */
final class MapNameMapper1_0M
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
            9 => 'Devil Square',
            10 => 'Icarus',
            11 => 'Blood Castle 1',
            12 => 'Blood Castle 2',
            13 => 'Blood Castle 3',
            14 => 'Blood Castle 4',
            15 => 'Blood Castle 5',
            16 => 'Blood Castle 6',
            17 => 'Blood Castle 7',
            18 => 'Chaos Castle 1',
            19 => 'Chaos Castle 2',
            20 => 'Chaos Castle 3',
            21 => 'Chaos Castle 4',
            22 => 'Chaos Castle 5',
            23 => 'Chaos Castle 6',
            24 => 'Kalima 1',
            25 => 'Kalima 2',
            26 => 'Kalima 3',
            27 => 'Kalima 4',
            28 => 'Kalima 5',
            29 => 'Kalima 6',
            30 => 'Valley of Loren',
            31 => 'Land of Trials',
            32 => 'Devil Square',
            default => sprintf('Map %d', $mapNumber),
        };
    }
}
