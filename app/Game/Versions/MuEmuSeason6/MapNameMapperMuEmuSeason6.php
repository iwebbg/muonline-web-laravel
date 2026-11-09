<?php

namespace App\Game\Versions\MuEmuSeason6;

/**
 * Maps native Update 32 map numbers to stable display names.
 */
final class MapNameMapperMuEmuSeason6
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
            9 => 'Devil Square 1',
            10 => 'Icarus',
            11, 12, 13, 14, 15, 16, 17 => sprintf('Blood Castle %d', $mapNumber - 10),
            18, 19, 20, 21, 22, 23 => sprintf('Chaos Castle %d', $mapNumber - 17),
            24, 25, 26, 27, 28, 29 => sprintf('Kalima %d', $mapNumber - 23),
            30 => 'Castle Siege',
            31 => 'Land of Trials',
            32 => 'Devil Square 2',
            33 => 'Aida',
            34 => 'Crywolf',
            36 => 'Kalima 7',
            37 => 'Kanturu 1',
            38 => 'Kanturu 2',
            39 => 'Kanturu 3',
            40 => 'Silent Map',
            41 => 'Barracks',
            42 => 'Refuge',
            45, 46, 47, 48, 49, 50 => sprintf('Illusion Temple %d', $mapNumber - 44),
            51 => 'Elbeland',
            52 => 'Blood Castle 8',
            53 => 'Chaos Castle 7',
            56 => 'Swamp of Calmness',
            57 => 'Raklion',
            58 => 'Raklion Boss',
            62 => 'Santa Town',
            63 => 'Vulcanus',
            64 => 'Duel Arena',
            65, 66, 67, 68 => sprintf('Double Goer %d', $mapNumber - 64),
            69, 70, 71, 72 => sprintf('Imperial Guardian %d', $mapNumber - 68),
            79 => 'Loren Market',
            80 => 'Karutan 1',
            81 => 'Karutan 2',
            default => sprintf('Map %d', $mapNumber),
        };
    }
}
