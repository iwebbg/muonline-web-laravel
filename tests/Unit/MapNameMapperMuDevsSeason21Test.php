<?php

namespace Tests\Unit;

use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies classic, renewed-event and later S21 location labels independently.
 */
final class MapNameMapperMuDevsSeason21Test extends TestCase
{
    /**
     * Confirms native map identifiers resolve to the supplied MapManager labels.
     */
    #[DataProvider('locations')]
    public function test_resolves_configured_locations(int $mapNumber, string $expectedName): void
    {
        self::assertSame($expectedName, (new MapNameMapperMuDevsSeason21)->nameFor($mapNumber));
    }

    /**
     * Confirms unconfigured IDs retain a diagnostic fallback instead of an alias.
     */
    #[DataProvider('unknownLocations')]
    public function test_preserves_unknown_location_identity(int $mapNumber, string $expectedName): void
    {
        self::assertSame($expectedName, (new MapNameMapperMuDevsSeason21)->nameFor($mapNumber));
    }

    /**
     * Supplies range boundaries and every individual late-season location.
     *
     * @return array<string, array{int, string}>
     */
    public static function locations(): array
    {
        return [
            'lorencia' => [0, 'Lorencia'],
            'first blood castle' => [11, 'Blood Castle 1'],
            'seventh blood castle' => [17, 'Blood Castle 7'],
            'eighth blood castle' => [52, 'Blood Castle 8'],
            'chaos castle' => [53, 'Chaos Castle 7'],
            'configured custom location label' => [40, 'Custom Arena'],
            'raklion' => [57, 'Raklion 1'],
            'raklion boss area' => [58, 'Raklion 2'],
            'karutan' => [81, 'Karutan 2'],
            'first renewed doppelganger' => [82, 'Double Goer Renew 1'],
            'last renewed doppelganger' => [90, 'Double Goer Renew 9'],
            'acheron' => [92, 'Acheron 2'],
            'deventer' => [96, 'Deventer 2'],
            'chaos final' => [97, 'Chaos Castle Final'],
            'illusion final' => [99, 'Illusion Temple Final 2'],
            'urk mountain' => [101, 'Urk Mountain 2'],
            'devil final' => [102, 'Devil Square Final'],
            'nars' => [110, 'Nars'],
            'ferea' => [112, 'Ferea'],
            'nixies lake' => [113, 'Nixies Lake'],
            'instance zone' => [114, 'Instance Zone'],
            'labyrinth' => [115, 'Labyrinth of Dimensions'],
            'first deep dungeon' => [116, 'Deep Dungeon 1'],
            'last deep dungeon' => [120, 'Deep Dungeon 5'],
            'trial' => [121, 'Trial Area'],
            'swamp' => [122, 'Swamp of Darkness'],
            'first mine' => [123, 'Kubera Mine 1'],
            'last mine' => [127, 'Kubera Mine 5'],
            'first abyss' => [128, 'Atlans Abyss 1'],
            'last abyss' => [130, 'Atlans Abyss 3'],
            'scorched canyon' => [131, 'Scorched Canyon'],
            'crimson icarus' => [132, 'Crimson Icarus'],
            'arenil' => [133, 'Arenil Temple'],
            'ashen aida' => [134, 'Ashen Aida'],
            'old kethotum' => [135, 'Old Kethotum'],
            'blaze kethhotum' => [136, 'Blaze Kethhotum'],
            'kanturu under area' => [137, 'Kanturu Under Area'],
            'ignis' => [138, 'Ignis Vulcanus'],
            'boss battle' => [139, 'Boss Battle Zone'],
            'blood tarkan' => [140, 'Blood Tarkan'],
            'tormenta' => [141, 'Tormenta Island'],
            'twister' => [142, 'Twister Karutan'],
            'kardamahal' => [143, 'Kardamahal UnderGround Temple'],
            'swamp of doom' => [144, 'Swamp of Doom'],
            'aquilas' => [145, 'Aquilas Temple'],
        ];
    }

    /**
     * Supplies absent gaps and unsupported future map identifiers.
     *
     * @return array<string, array{int, string}>
     */
    public static function unknownLocations(): array
    {
        return [
            'negative' => [-1, 'Map -1'],
            'classic gap' => [5, 'Map 5'],
            'renewal gap' => [93, 'Map 93'],
            'future' => [146, 'Map 146'],
        ];
    }
}
