<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Data\ItemSlot;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use InvalidArgumentException;

/**
 * Restricts initial native custody to ordinary, tradable, self-contained equipment.
 */
final readonly class ItemTransferRulesMuDevsSeason21
{
    /**
     * Uses native source flags and complete records before permitting custody changes.
     */
    public function __construct(
        private ItemCodecAdapterMuDevsSeason21 $codec,
        private ItemCatalogMuDevsSeason21 $catalog,
    ) {}

    /**
     * Rejects special-item dependencies, unknown extensions and non-tradable records.
     *
     * Pentagrams, earrings, wings, Muun and artifacts require separate persistence
     * adapters. Retaining their main item bytes alone cannot transfer their state.
     *
     * @throws MarketListingRejected When the item is not a commissioned ordinary item.
     */
    public function assertTransferable(string $binary): ItemSlot
    {
        try {
            $item = $this->codec->decode($binary, 1)[0];
        } catch (InvalidArgumentException) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        if ($item->isEmpty() || $item->group > 11) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        $metadata = $this->catalog->definitionFor($item->group, $item->index);
        $flags = $metadata?->sourceAttributes ?? [];

        if (($flags['Transaction'] ?? '0') !== '1'
            || ($flags['StoreWarehouse'] ?? '0') !== '1'
            || ($flags['Serial'] ?? '0') !== '1'
            || $metadata->equipmentSlot < 0 || $metadata->equipmentSlot > 11
            || substr($binary, 16) !== str_repeat("\xFF", 9)
            || in_array($item->serial, [null, '00000000', 'FFFFFFFF'], true)
            || $item->ancientOption > 15) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        return $item;
    }
}
