<?php

namespace App\Models;

use App\Shop\ShopClassScope;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Database\Factories\ShopOfferFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Defines one administrator-curated item or mystery-box offer.
 */
#[Fillable(['public_id', 'type', 'class_scope', 'title_en', 'title_bg', 'description_en', 'description_bg', 'price_credits', 'status', 'sort_order', 'published_at', 'available_from', 'available_until'])]
final class ShopOffer extends Model
{
    /** @use HasFactory<ShopOfferFactory> */
    use HasFactory;

    /**
     * Uses the stable public UUID in player and administrator URLs.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Returns every weighted item configured for this offer.
     *
     * @return HasMany<ShopOfferItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(ShopOfferItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Restricts a query to offers currently purchasable by players.
     *
     * @param  Builder<ShopOffer>  $query
     * @return Builder<ShopOffer>
     */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query
            ->where('status', ShopOfferStatus::Published)
            ->where(function (Builder $availability): void {
                $availability->whereNull('available_from')->orWhere('available_from', '<=', now());
            })
            ->where(function (Builder $availability): void {
                $availability->whereNull('available_until')->orWhere('available_until', '>', now());
            });
    }

    /**
     * Returns the localized title with English fallback.
     */
    public function localizedTitle(string $locale): string
    {
        return $locale === 'bg' && $this->title_bg !== '' ? $this->title_bg : $this->title_en;
    }

    /**
     * Returns the optional localized description with English fallback.
     */
    public function localizedDescription(string $locale): ?string
    {
        $description = $locale === 'bg' ? $this->description_bg : $this->description_en;

        return $description ?: $this->description_en;
    }

    /**
     * Casts finite offer state and availability dates to domain values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => ShopOfferType::class,
            'class_scope' => ShopClassScope::class,
            'status' => ShopOfferStatus::class,
            'price_credits' => 'integer',
            'sort_order' => 'integer',
            'published_at' => 'immutable_datetime',
            'available_from' => 'immutable_datetime',
            'available_until' => 'immutable_datetime',
        ];
    }
}
