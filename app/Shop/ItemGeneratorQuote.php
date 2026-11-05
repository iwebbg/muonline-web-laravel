<?php

namespace App\Shop;

/**
 * Carries one server-authoritative generated-item price and immutable snapshot.
 */
final readonly class ItemGeneratorQuote
{
    /**
     * Creates a quote that can be persisted without recalculating old purchases.
     *
     * @param  array<string, int|string|bool|null>  $itemSnapshot
     * @param  array<string, int>  $priceBreakdown
     */
    public function __construct(
        public int $priceCredits,
        public array $itemSnapshot,
        public array $priceBreakdown,
    ) {}
}
