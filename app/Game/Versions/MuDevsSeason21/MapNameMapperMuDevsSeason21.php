<?php

namespace App\Game\Versions\MuDevsSeason21;

/**
 * Resolves location labels from the supplied MUDevs S21.1-2 MapManager records.
 */
final class MapNameMapperMuDevsSeason21
{
    /**
     * Returns a configured location name without granting movement eligibility.
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
            37, 38, 39 => sprintf('Kanturu %d', $mapNumber - 36),
            40 => 'Custom Arena',
            41 => 'Barracks',
            42 => 'Refuge',
            45, 46, 47, 48, 49, 50 => sprintf('Illusion Temple %d', $mapNumber - 44),
            51 => 'Elbeland',
            52 => 'Blood Castle 8',
            53 => 'Chaos Castle 7',
            56 => 'Swamp of Calmness',
            57, 58 => sprintf('Raklion %d', $mapNumber - 56),
            62 => 'Santa Town',
            63 => 'Vulcanus',
            64 => 'Duel Arena',
            65, 66, 67, 68 => sprintf('Double Goer %d', $mapNumber - 64),
            69, 70, 71, 72 => sprintf('Imperial Guardian %d', $mapNumber - 68),
            79 => 'Loren Market',
            80, 81 => sprintf('Karutan %d', $mapNumber - 79),
            82, 83, 84, 85, 86, 87, 88, 89, 90 => sprintf('Double Goer Renew %d', $mapNumber - 81),
            91, 92 => sprintf('Acheron %d', $mapNumber - 90),
            95, 96 => sprintf('Deventer %d', $mapNumber - 94),
            97 => 'Chaos Castle Final',
            98, 99 => sprintf('Illusion Temple Final %d', $mapNumber - 97),
            100, 101 => sprintf('Urk Mountain %d', $mapNumber - 99),
            102 => 'Devil Square Final',
            110 => 'Nars',
            112 => 'Ferea',
            113 => 'Nixies Lake',
            114 => 'Instance Zone',
            115 => 'Labyrinth of Dimensions',
            116, 117, 118, 119, 120 => sprintf('Deep Dungeon %d', $mapNumber - 115),
            121 => 'Trial Area',
            122 => 'Swamp of Darkness',
            123, 124, 125, 126, 127 => sprintf('Kubera Mine %d', $mapNumber - 122),
            128, 129, 130 => sprintf('Atlans Abyss %d', $mapNumber - 127),
            131 => 'Scorched Canyon',
            132 => 'Crimson Icarus',
            133 => 'Arenil Temple',
            134 => 'Ashen Aida',
            135 => 'Old Kethotum',
            136 => 'Blaze Kethhotum',
            137 => 'Kanturu Under Area',
            138 => 'Ignis Vulcanus',
            139 => 'Boss Battle Zone',
            140 => 'Blood Tarkan',
            141 => 'Tormenta Island',
            142 => 'Twister Karutan',
            143 => 'Kardamahal UnderGround Temple',
            144 => 'Swamp of Doom',
            145 => 'Aquilas Temple',
            default => sprintf('Map %d', $mapNumber),
        };
    }
}
