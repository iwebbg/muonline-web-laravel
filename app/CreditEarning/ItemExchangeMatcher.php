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
     *
     * Native S21 advanced options are excluded until offers can express them;
     * a basic signature must not consume valuable Harmony or socket equipment.
     */
    public function matches(ItemSlot $item, CreditExchangeOffer $offer): bool
    {
        if ($item->transferable === false) {
            return false;
        }

        if (strlen($item->binary()) === 25
            && ($item->harmonyOption !== 0
                || array_filter($item->socketOptions, static fn (int $option): bool => $option !== 255) !== []
                || substr($item->binary(), 16) !== str_repeat("\xFF", 9))) {
            return false;
        }

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
