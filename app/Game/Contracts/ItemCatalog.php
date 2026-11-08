<?php

namespace App\Game\Contracts;

use App\Game\Data\ItemDefinition;

/**
 * Exposes item metadata owned by the active game-version adapter.
 */
interface ItemCatalog
{
    /**
     * Returns one native item definition when supported.
     */
    public function definitionFor(int $group, int $index): ?ItemDefinition;

    /**
     * Returns every item definition owned by the active version.
     *
     * @return list<ItemDefinition>
     */
    public function allDefinitions(): array;
}
