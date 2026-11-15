<?php

namespace App\Game\Versions\MuDevsSeason21;

use App\Game\Data\ItemSlot;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketListingBlockReason;
use InvalidArgumentException;

/**
 * Restricts native custody to ordinary equipment and the five standard physical jewels.
 */
final readonly class ItemTransferRulesMuDevsSeason21
{
    /** @var list<string> */
    private const STANDARD_JEWELS = ['12:15', '14:13', '14:14', '14:16', '14:22'];

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

        if ($item->isEmpty()) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        $metadata = $this->catalog->definitionFor($item->group, $item->index);
        $flags = $metadata?->sourceAttributes ?? [];
        $isEquipment = $metadata !== null && $item->group <= 11
            && $metadata->equipmentSlot >= 0 && $metadata->equipmentSlot <= 11;
        $isStandardJewel = in_array($item->code(), self::STANDARD_JEWELS, true)
            && $item->level === 0 && $item->durability >= 1
            && $item->durability <= (int) ($flags['ItemOverlap'] ?? 0)
            && ! $item->hasSkill && ! $item->hasLuck
            && $item->optionLevel === 0 && $item->excellentMask === 0 && $item->ancientOption === 0;

        if (($flags['Transaction'] ?? '0') !== '1'
            || ($flags['StoreWarehouse'] ?? '0') !== '1'
            || ($flags['Serial'] ?? '0') !== '1'
            || (! $isEquipment && ! $isStandardJewel)
            || substr($binary, 16) !== str_repeat("\xFF", 9)
            || in_array($item->serial, [null, '00000000', 'FFFFFFFF'], true)
            || $item->ancientOption > 15) {
            throw new MarketListingRejected(MarketListingBlockReason::UnsupportedItem);
        }

        return $item;
    }
}
