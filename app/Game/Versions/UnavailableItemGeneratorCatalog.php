<?php

namespace App\Game\Versions;

use App\Game\Contracts\ItemGeneratorCatalog;
use App\Shop\ItemGeneratorQuote;

/**
 * Disables generated-item sales until a version has explicit audited pricing.
 */
final readonly class UnavailableItemGeneratorCatalog implements ItemGeneratorCatalog
{
    /**
     * Returns no generated items for this version.
     *
     * @return list<array<string, int|string|bool|null>>
     */
    public function items(): array
    {
        return [];
    }

    /**
     * Returns zeroed preview prices because no item is purchasable.
     *
     * @return array{level: int, additionalOption: int, skill: int, luck: int, excellentOption: int}
     */
    public function priceModifiers(): array
    {
        return [
            'level' => 0,
            'additionalOption' => 0,
            'skill' => 0,
            'luck' => 0,
            'excellentOption' => 0,
        ];
    }

    /**
     * Rejects every configuration until audited pricing exists.
     *
     * @param  array{group: int, index: int, level: int, durability: int, option_level: int, has_skill: bool, has_luck: bool, excellent_mask: int, ancient_option: int}  $options
     */
    public function quote(array $options): ?ItemGeneratorQuote
    {
        return null;
    }
}
