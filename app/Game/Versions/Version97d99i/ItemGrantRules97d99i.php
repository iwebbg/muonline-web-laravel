<?php

namespace App\Game\Versions\Version97d99i;

use App\Game\Contracts\ItemGrantRules;

/**
 * Defines administrator item options verified for the DarksTeam 97d+99i server.
 */
final readonly class ItemGrantRules97d99i implements ItemGrantRules
{
    public const MAX_ITEM_LEVEL = 11;

    public const MAX_OPTION_LEVEL = 7;

    /**
     * Returns the highest supported item level.
     */
    public function maxItemLevel(): int
    {
        return self::MAX_ITEM_LEVEL;
    }

    /**
     * Returns the highest supported additional-option level.
     */
    public function maxOptionLevel(): int
    {
        return self::MAX_OPTION_LEVEL;
    }

    /**
     * Returns every item level accepted by this game version.
     *
     * @return list<int>
     */
    public function itemLevels(): array
    {
        return range(0, self::MAX_ITEM_LEVEL);
    }

    /**
     * Returns every additional-option step encoded by this game version.
     *
     * @return list<int>
     */
    public function optionLevels(): array
    {
        return range(0, self::MAX_OPTION_LEVEL);
    }
}
