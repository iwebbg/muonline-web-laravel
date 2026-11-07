<?php

namespace App\Game\Versions\Version1_0M;

use App\Game\Contracts\ItemGrantRules;

/**
 * Defines verified item option limits for DarksTeam 1.0M.
 */
final readonly class ItemGrantRules1_0M implements ItemGrantRules
{
    public const MAX_ITEM_LEVEL = 11;

    public const MAX_OPTION_LEVEL = 7;

    /**
     * Returns the highest item level encoded by this item format.
     */
    public function maxItemLevel(): int
    {
        return self::MAX_ITEM_LEVEL;
    }

    /**
     * Returns the highest additional-option level encoded by this item format.
     */
    public function maxOptionLevel(): int
    {
        return self::MAX_OPTION_LEVEL;
    }

    /**
     * Returns every accepted item level.
     *
     * @return list<int>
     */
    public function itemLevels(): array
    {
        return range(0, self::MAX_ITEM_LEVEL);
    }

    /**
     * Returns every accepted additional-option level.
     *
     * @return list<int>
     */
    public function optionLevels(): array
    {
        return range(0, self::MAX_OPTION_LEVEL);
    }
}
