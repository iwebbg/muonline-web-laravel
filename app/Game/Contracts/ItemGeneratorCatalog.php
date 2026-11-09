<?php

namespace App\Game\Contracts;

use App\Shop\ItemGeneratorQuote;

/**
 * Provides version-owned generated-item availability and trusted pricing.
 */
interface ItemGeneratorCatalog
{
    /**
     * Returns every item with an explicit price for the active version.
     *
     * @return list<array<string, int|string|bool|null>>
     */
    public function items(): array;

    /**
     * Returns option prices used only for client-side previews.
     *
     * @return array<string, int>
     */
    public function priceModifiers(): array;

    /**
     * Returns a trusted quote for one supported configuration.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int, harmony_option?: int, socket_count?: int, has_380_option?: bool}  $options
     */
    public function quote(array $options): ?ItemGeneratorQuote;
}
