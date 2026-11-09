<?php

namespace App\Models;

use Database\Factories\CreditExchangeOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines one exact catalog item signature that players may exchange for Credits.
 */
#[Fillable([
    'public_id', 'game_profile_key', 'item_group', 'item_index', 'level',
    'option_level', 'has_skill', 'has_luck', 'excellent_mask', 'ancient_option',
    'item_name_snapshot', 'item_image_snapshot', 'credits_per_item',
    'excellent_type', 'option_type',
    'max_quantity_per_exchange', 'daily_account_credit_limit', 'enabled',
    'sort_order', 'revision', 'updated_by_administrator_id', 'archived_at',
])]
final class CreditExchangeOffer extends Model
{
    /** @use HasFactory<CreditExchangeOfferFactory> */
    use HasFactory;

    /**
     * Casts item options and exchange limits to exact domain types.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_group' => 'integer', 'item_index' => 'integer', 'level' => 'integer',
            'option_level' => 'integer', 'has_skill' => 'boolean', 'has_luck' => 'boolean',
            'excellent_mask' => 'integer', 'ancient_option' => 'integer',
            'excellent_type' => 'integer', 'option_type' => 'integer',
            'credits_per_item' => 'integer', 'max_quantity_per_exchange' => 'integer',
            'daily_account_credit_limit' => 'integer', 'enabled' => 'boolean',
            'sort_order' => 'integer', 'revision' => 'integer',
            'updated_by_administrator_id' => 'integer', 'archived_at' => 'immutable_datetime',
        ];
    }

    /**
     * Uses the non-sequential public identifier for administrator routes.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Returns immutable revisions for this offer.
     *
     * @return HasMany<CreditExchangeOfferRevision, $this>
     */
    public function revisions(): HasMany
    {
        return $this->hasMany(CreditExchangeOfferRevision::class);
    }

    /**
     * Returns the player operation history for this offer.
     *
     * @return HasMany<CreditItemExchange, $this>
     */
    public function exchanges(): HasMany
    {
        return $this->hasMany(CreditItemExchange::class);
    }

    /**
     * Returns the complete immutable state stored with revisions and exchanges.
     *
     * @return array<string, bool|int|string|null>
     */
    public function snapshot(): array
    {
        return [
            'public_id' => $this->public_id,
            'game_profile_key' => $this->game_profile_key,
            'item_group' => $this->item_group,
            'item_index' => $this->item_index,
            'level' => $this->level,
            'option_level' => $this->option_level,
            'has_skill' => $this->has_skill,
            'has_luck' => $this->has_luck,
            'excellent_mask' => $this->excellent_mask,
            'ancient_option' => $this->ancient_option,
            'item_name' => $this->item_name_snapshot,
            'item_image' => $this->item_image_snapshot,
            'excellent_type' => $this->excellent_type,
            'option_type' => $this->option_type,
            'credits_per_item' => $this->credits_per_item,
            'max_quantity_per_exchange' => $this->max_quantity_per_exchange,
            'daily_account_credit_limit' => $this->daily_account_credit_limit,
            'enabled' => $this->enabled,
            'sort_order' => $this->sort_order,
        ];
    }
}
