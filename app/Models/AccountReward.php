<?php

namespace App\Models;

use App\Database\Casts\LowercaseUuid;
use App\Database\Casts\SqlServerBinary;
use App\Shop\AccountRewardStatus;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardDeliveryDestination;
use App\Shop\RewardSource;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferType;
use Database\Factories\AccountRewardFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Represents one reward owned by an account regardless of how it was earned.
 */
#[Fillable(['public_id', 'account_id', 'shop_purchase_id', 'shop_offer_id', 'source', 'source_reference', 'granted_by_administrator_id', 'source_reason', 'status', 'reward_type', 'mystery_box_reward_mode', 'class_scope', 'title_en', 'title_bg', 'description_en', 'description_bg', 'reward_pool_snapshot', 'selected_item_snapshot', 'item_serial', 'item_binary', 'item_fingerprint', 'delivery_destination', 'delivery_attempts', 'last_delivery_attempt_at', 'last_delivery_error', 'opened_at', 'claimed_at'])]
final class AccountReward extends Model
{
    /** @use HasFactory<AccountRewardFactory> */
    use HasFactory;

    /**
     * Uses the public UUID so sequential reward identifiers are never exposed.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Returns the optional purchase that created this reward.
     *
     * @return BelongsTo<ShopPurchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(ShopPurchase::class, 'shop_purchase_id');
    }

    /**
     * Returns the localized reward title with English fallback.
     */
    public function localizedTitle(string $locale): string
    {
        return $locale === 'bg' && $this->title_bg !== '' ? $this->title_bg : $this->title_en;
    }

    /**
     * Casts lifecycle, provenance and item snapshots to domain values.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'public_id' => LowercaseUuid::class,
            'source' => RewardSource::class,
            'status' => AccountRewardStatus::class,
            'reward_type' => ShopOfferType::class,
            'mystery_box_reward_mode' => MysteryBoxRewardMode::class,
            'class_scope' => ShopClassScope::class,
            'reward_pool_snapshot' => 'array',
            'selected_item_snapshot' => 'array',
            'item_serial' => 'integer',
            'item_binary' => SqlServerBinary::class,
            'delivery_destination' => RewardDeliveryDestination::class,
            'delivery_attempts' => 'integer',
            'last_delivery_attempt_at' => 'immutable_datetime',
            'opened_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime',
        ];
    }
}
