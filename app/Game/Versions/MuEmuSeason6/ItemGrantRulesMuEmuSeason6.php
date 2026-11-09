<?php

namespace App\Game\Versions\MuEmuSeason6;

use App\Game\Contracts\ItemGrantRules;

/**
 * Defines verified item option limits for MuEMU Season 6 Update 32.
 */
final readonly class ItemGrantRulesMuEmuSeason6 implements ItemGrantRules
{
    public const MAX_ITEM_LEVEL = 15;

    public const MAX_OPTION_LEVEL = 7;

    /**
     * Returns the highest item level supported by the native item format.
     */
    public function maxItemLevel(): int
    {
        return self::MAX_ITEM_LEVEL;
    }

    /**
     * Returns the highest additional-option level encoded by the native format.
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
