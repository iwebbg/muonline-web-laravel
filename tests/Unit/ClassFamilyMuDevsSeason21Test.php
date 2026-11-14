<?php

namespace Tests\Unit;

use App\Game\Versions\MuDevsSeason21\ClassFamilyMuDevsSeason21;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies family stats against the supplied S21 DefaultClassInfo and Common files.
 */
final class ClassFamilyMuDevsSeason21Test extends TestCase
{
    /**
     * Confirms every base family resolves to its source key and level-one stats.
     *
     * @param  array<string, int>  $expectedStats
     */
    #[DataProvider('families')]
    public function test_resolves_native_family_stats_and_level_up_points(
        int $familyId,
        string $sourceKey,
        array $expectedStats,
        int $pointsPerLevel,
    ): void {
        $family = ClassFamilyMuDevsSeason21::from($familyId);

        self::assertSame($sourceKey, $family->sourceKey());
        self::assertSame($expectedStats, $family->baseStats()->toArray());
        self::assertSame($pointsPerLevel, $family->pointsPerLevel());
    }

    /**
     * Confirms persisted advancement values are not silently accepted as base IDs.
     */
    public function test_rejects_identifiers_outside_the_sixteen_base_families(): void
    {
        self::assertNull(ClassFamilyMuDevsSeason21::tryFrom(-1));
        self::assertNull(ClassFamilyMuDevsSeason21::tryFrom(16));
        self::assertNull(ClassFamilyMuDevsSeason21::tryFrom(240));
    }

    /**
     * Supplies independently transcribed base stats and configured normal points.
     *
     * @return array<string, array{int, string, array<string, int>, int}>
     */
    public static function families(): array
    {
        return [
            'dark wizard' => [0, 'DW', ['strength' => 18, 'agility' => 18, 'vitality' => 15, 'energy' => 30], 5],
            'dark knight' => [1, 'DK', ['strength' => 28, 'agility' => 20, 'vitality' => 25, 'energy' => 10], 5],
            'fairy elf' => [2, 'FE', ['strength' => 22, 'agility' => 25, 'vitality' => 20, 'energy' => 15], 5],
            'magic gladiator' => [3, 'MG', ['strength' => 26, 'agility' => 26, 'vitality' => 26, 'energy' => 26], 7],
            'dark lord' => [4, 'DL', ['strength' => 26, 'agility' => 20, 'vitality' => 20, 'energy' => 15, 'command' => 25], 7],
            'summoner' => [5, 'SU', ['strength' => 21, 'agility' => 21, 'vitality' => 18, 'energy' => 23], 5],
            'rage fighter' => [6, 'RF', ['strength' => 32, 'agility' => 27, 'vitality' => 25, 'energy' => 20], 7],
            'grow lancer' => [7, 'GL', ['strength' => 30, 'agility' => 30, 'vitality' => 25, 'energy' => 24], 7],
            'rune wizard' => [8, 'RW', ['strength' => 13, 'agility' => 18, 'vitality' => 14, 'energy' => 40], 5],
            'slayer' => [9, 'SL', ['strength' => 28, 'agility' => 30, 'vitality' => 15, 'energy' => 10], 5],
            'gun crusher' => [10, 'GC', ['strength' => 20, 'agility' => 18, 'vitality' => 20, 'energy' => 25], 5],
            'kundun mephis' => [11, 'KM', ['strength' => 19, 'agility' => 19, 'vitality' => 15, 'energy' => 30], 5],
            'lemuria' => [12, 'LM', ['strength' => 18, 'agility' => 18, 'vitality' => 19, 'energy' => 30], 5],
            'illusion knight' => [13, 'IK', ['strength' => 25, 'agility' => 28, 'vitality' => 15, 'energy' => 10], 5],
            'alchemist' => [14, 'AL', ['strength' => 15, 'agility' => 20, 'vitality' => 23, 'energy' => 25], 5],
            'crusader' => [15, 'CS', ['strength' => 26, 'agility' => 18, 'vitality' => 19, 'energy' => 20], 5],
        ];
    }
}
