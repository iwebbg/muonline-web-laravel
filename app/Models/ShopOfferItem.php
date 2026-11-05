<?php

namespace App\Models;

use Database\Factories\ShopOfferItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stores one immutable-ready item configuration in an offer reward pool.
 */
#[Fillable(['shop_offer_id', 'item_code', 'item_name', 'item_image', 'item_width', 'item_height', 'level', 'durability', 'option_level', 'has_skill', 'has_luck', 'excellent_mask', 'ancient_option', 'weight', 'sort_order'])]
final class ShopOfferItem extends Model
{
    /** @use HasFactory<ShopOfferItemFactory> */
    use HasFactory;

    /**
     * Returns the offer that owns this weighted item.
     *
     * @return BelongsTo<ShopOffer, $this>
     */
    public function offer(): BelongsTo
    {
        return $this->belongsTo(ShopOffer::class, 'shop_offer_id');
    }

    /**
     * Returns the complete item snapshot copied into purchased rewards.
     *
     * @return array<string, int|string|bool|null>
     */
    public function snapshot(): array
    {
        return [
            'item_code' => $this->item_code,
            'item_name' => $this->item_name,
            'item_image' => $this->item_image,
            'item_width' => $this->item_width,
            'item_height' => $this->item_height,
            'level' => $this->level,
            'durability' => $this->durability,
            'option_level' => $this->option_level,
            'has_skill' => $this->has_skill,
            'has_luck' => $this->has_luck,
            'excellent_mask' => $this->excellent_mask,
            'ancient_option' => $this->ancient_option,
            'weight' => $this->weight,
        ];
    }

    /**
     * Casts item options and selection weight to exact scalar values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'item_width' => 'integer',
            'item_height' => 'integer',
            'level' => 'integer',
            'durability' => 'integer',
            'option_level' => 'integer',
            'has_skill' => 'boolean',
            'has_luck' => 'boolean',
            'excellent_mask' => 'integer',
            'ancient_option' => 'integer',
            'weight' => 'integer',
            'sort_order' => 'integer',
        ];
    }
}
