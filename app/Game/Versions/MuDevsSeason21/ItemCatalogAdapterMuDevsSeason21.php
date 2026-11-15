<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Contracts\ItemCatalog;
use App\Game\Data\ItemDefinition;

/**
 * Supplies native S21 item geometry and names to the shared storage system.
 */
final readonly class ItemCatalogAdapterMuDevsSeason21 implements ItemCatalog
{
    /**
     * Retains the complete native catalog as the source of item metadata.
     */
    public function __construct(private ItemCatalogMuDevsSeason21 $catalog) {}

    /**
     * Looks up a native identity without narrowing its nine-bit index.
     */
    public function definitionFor(int $group, int $index): ?ItemDefinition
    {
        $metadata = $this->catalog->definitionFor($group, $index);

        return $metadata === null ? null : $this->definition($metadata);
    }

    /**
     * Returns every native item for version-aware item selectors.
     *
     * @return list<ItemDefinition>
     */
    public function allDefinitions(): array
    {
        return array_map($this->definition(...), $this->catalog->allDefinitions());
    }

    /**
     * Preserves source geometry without assigning unverified legacy option categories.
     */
    private function definition(ItemMetadataMuDevsSeason21 $metadata): ItemDefinition
    {
        return new ItemDefinition(
            group: $metadata->group,
            index: $metadata->index,
            name: $metadata->name,
            width: $metadata->width,
            height: $metadata->height,
            image: $metadata->image,
            excellentType: 0,
            optionType: 0,
        );
    }
}
