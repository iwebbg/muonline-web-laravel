<?php

namespace App\Market;

use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Builder;

/**
 * Groups stable MuOnline item sections into player-facing market categories.
 */
enum MarketItemCategory: string
{
    case Weapons = 'weapons';
    case Shields = 'shields';
    case Armor = 'armor';
    case WingsAccessories = 'wings_accessories';
    case JewelsConsumables = 'jewels_consumables';
    case SkillsScrolls = 'skills_scrolls';

    /**
     * Returns the item groups represented by this market category.
     *
     * @return list<int>
     */
    public function groups(): array
    {
        return match ($this) {
            self::Weapons => [0, 1, 2, 3, 4, 5],
            self::Shields => [6],
            self::Armor => [7, 8, 9, 10, 11],
            self::WingsAccessories => [12, 13],
            self::JewelsConsumables => [14],
            self::SkillsScrolls => [15],
        };
    }

    /**
     * Applies this category's stable group prefixes to a market listing query.
     *
     * @param  Builder<MarketListing>  $query
     */
    public function applyTo(Builder $query): void
    {
        $query->where(function (Builder $categoryQuery): void {
            foreach ($this->groups() as $group) {
                $categoryQuery->orWhere(
                    'item_snapshot->code',
                    'like',
                    $group.':%',
                );
            }
        });
    }
}
