<?php

namespace App\Shop;

use InvalidArgumentException;

/**
 * Selects one reward from a positive integer weighted snapshot.
 */
final readonly class WeightedRewardSelector
{
    /**
     * Returns the item whose cumulative weight contains the supplied roll.
     *
     * @param  list<array<string, mixed>>  $items
     * @return array<string, mixed>
     */
    public function select(array $items, int $roll): array
    {
        $cumulativeWeight = 0;

        foreach ($items as $item) {
            $weight = max(0, (int) ($item['weight'] ?? 0));
            $cumulativeWeight += $weight;

            if ($weight > 0 && $roll <= $cumulativeWeight) {
                return $item;
            }
        }

        throw new InvalidArgumentException('The reward roll is outside the configured weight range.');
    }

    /**
     * Returns the total positive selection weight for a reward pool.
     *
     * @param  list<array<string, mixed>>  $items
     */
    public function totalWeight(array $items): int
    {
        return array_sum(array_map(
            static fn (array $item): int => max(0, (int) ($item['weight'] ?? 0)),
            $items,
        ));
    }
}
