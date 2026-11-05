<?php

namespace Tests\Unit;

use App\Shop\WeightedRewardSelector;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Verifies deterministic boundaries in weighted Mystery Box selection.
 */
final class WeightedRewardSelectorTest extends TestCase
{
    /**
     * Provides rolls at the lower and upper edge of each configured weight.
     *
     * @return iterable<string, array{int, string}>
     */
    public static function weightedRolls(): iterable
    {
        yield 'first item lower edge' => [1, 'Kris'];
        yield 'first item upper edge' => [2, 'Kris'];
        yield 'second item lower edge' => [3, 'Short Sword'];
        yield 'second item upper edge' => [5, 'Short Sword'];
    }

    /**
     * Confirms each valid roll selects the item covering its cumulative range.
     */
    #[DataProvider('weightedRolls')]
    public function test_selects_item_for_cumulative_weight_range(int $roll, string $expectedName): void
    {
        $selector = new WeightedRewardSelector;
        $items = [
            ['item_name' => 'Kris', 'weight' => 2],
            ['item_name' => 'Short Sword', 'weight' => 3],
        ];

        $selected = $selector->select($items, $roll);

        $this->assertSame($expectedName, $selected['item_name']);
        $this->assertSame(5, $selector->totalWeight($items));
    }

    /**
     * Confirms malformed non-positive weights cannot create selection space.
     */
    public function test_rejects_roll_outside_positive_weight_range(): void
    {
        $selector = new WeightedRewardSelector;

        $this->expectException(InvalidArgumentException::class);

        $selector->select([
            ['item_name' => 'Invalid reward', 'weight' => 0],
        ], 1);
    }
}
