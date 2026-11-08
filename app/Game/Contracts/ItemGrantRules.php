<?php

namespace App\Game\Contracts;

/**
 * Defines item-construction limits owned by the active game version.
 */
interface ItemGrantRules
{
    /**
     * Returns the highest supported item level.
     */
    public function maxItemLevel(): int;

    /**
     * Returns the highest supported additional-option level.
     */
    public function maxOptionLevel(): int;

    /**
     * Returns every supported item level.
     *
     * @return list<int>
     */
    public function itemLevels(): array;

    /**
     * Returns every supported additional-option level.
     *
     * @return list<int>
     */
    public function optionLevels(): array;
}
