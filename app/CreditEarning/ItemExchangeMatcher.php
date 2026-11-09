<?php

namespace App\CreditEarning;

use App\Game\Data\ItemSlot;
use App\Models\CreditExchangeOffer;

/**
 * Matches decoded inventory items against one administrator-defined exact signature.
 */
final class ItemExchangeMatcher
{
    /**
     * Returns whether every exchangeable item property exactly matches the offer.
     */
    public function matches(ItemSlot $item, CreditExchangeOffer $offer): bool
    {
        return $item->group === $offer->item_group
            && $item->index === $offer->item_index
            && $item->level === $offer->level
            && $item->optionLevel === $offer->option_level
            && $item->hasSkill === $offer->has_skill
            && $item->hasLuck === $offer->has_luck
            && $item->excellentMask === $offer->excellent_mask
            && $item->ancientOption === $offer->ancient_option;
    }
}
