<?php

namespace Tests\Unit;

use App\Game\Versions\Version97d99i\MapNameMapper;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies map names against the supported 97d+99i server-file definitions.
 */
final class MapNameMapperTest extends TestCase
{
    /**
     * Confirms every verified server-file map number resolves to its display name.
     */
    #[DataProvider('verifiedMapNames')]
    public function test_verified_map_numbers_return_version_specific_names(
        int $mapNumber,
        string $expectedName,
    ): void {
        $name = (new MapNameMapper)->nameFor($mapNumber);

        $this->assertSame($expectedName, $name);
    }

    /**
     * Confirms unknown map numbers remain visible without borrowing newer-season names.
     */
    public function test_unknown_map_number_returns_numeric_fallback(): void
    {
        $name = (new MapNameMapper)->nameFor(99);

        $this->assertSame('Map 99', $name);
    }

    /**
     * Supplies the map numbers verified from the 97d+99i server files.
     *
     * @return array<string, array{int, string}>
     */
    public static function verifiedMapNames(): array
    {
        return [
            'Lorencia' => [0, 'Lorencia'],
            'Dungeon' => [1, 'Dungeon'],
            'Davias' => [2, 'Davias'],
            'Noria' => [3, 'Noria'],
            'Losttower' => [4, 'Losttower'],
            'A Place of Exile' => [5, 'A Place of Exile'],
            'Stadium' => [6, 'Stadium'],
            'Atlans' => [7, 'Atlans'],
            'Tarkan' => [8, 'Tarkan'],
            'Icarus' => [10, 'Icarus'],
            'Blood Castle 1' => [11, 'Blood Castle 1'],
            'Blood Castle 2' => [12, 'Blood Castle 2'],
            'Blood Castle 3' => [13, 'Blood Castle 3'],
            'Blood Castle 4' => [14, 'Blood Castle 4'],
            'Blood Castle 5' => [15, 'Blood Castle 5'],
            'Blood Castle 6' => [16, 'Blood Castle 6'],
            'Devil Square' => [30, 'Devil Square'],
        ];
    }
}
