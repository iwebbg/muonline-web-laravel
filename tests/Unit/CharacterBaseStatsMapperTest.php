<?php

namespace Tests\Unit;

use App\Game\Versions\Version97d99i\CharacterBaseStatsMapper;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/**
 * Verifies the original level-one attributes for every supported 97d+99i class.
 */
final class CharacterBaseStatsMapperTest extends TestCase
{
    /**
     * Confirms base and evolved classes resolve to their shared family defaults.
     */
    public function test_supported_classes_map_to_level_one_stats(): void
    {
        $mapper = new CharacterBaseStatsMapper;

        $this->assertSame([18, 18, 15, 30], $this->values($mapper, 0));
        $this->assertSame([18, 18, 15, 30], $this->values($mapper, 1));
        $this->assertSame([28, 20, 25, 10], $this->values($mapper, 16));
        $this->assertSame([28, 20, 25, 10], $this->values($mapper, 17));
        $this->assertSame([22, 25, 20, 15], $this->values($mapper, 32));
        $this->assertSame([22, 25, 20, 15], $this->values($mapper, 33));
        $this->assertSame([26, 26, 26, 26], $this->values($mapper, 48));
    }

    /**
     * Confirms unknown class codes cannot silently receive incorrect attributes.
     */
    public function test_unknown_class_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        (new CharacterBaseStatsMapper)->forClass(64);
    }

    /**
     * Converts one mapped value object to a compact assertion tuple.
     *
     * @return list<int>
     */
    private function values(
        CharacterBaseStatsMapper $mapper,
        int $classCode,
    ): array {
        $stats = $mapper->forClass($classCode);

        return [
            $stats->strength,
            $stats->agility,
            $stats->vitality,
            $stats->energy,
        ];
    }
}
