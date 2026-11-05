<?php

namespace Database\Factories;

use App\Models\AccountReward;
use App\Shop\AccountRewardStatus;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\RewardSource;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AccountReward>
 */
class AccountRewardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'public_id' => (string) Str::uuid(),
            'account_id' => fake()->regexify('[A-Za-z0-9]{10}'),
            'shop_purchase_id' => null,
            'shop_offer_id' => null,
            'source' => RewardSource::Administrator,
            'source_reference' => null,
            'status' => AccountRewardStatus::Sealed,
            'reward_type' => ShopOfferType::MysteryBox,
            'mystery_box_reward_mode' => MysteryBoxRewardMode::OneRandom,
            'class_scope' => ShopClassScope::All,
            'title_en' => 'Mystery reward',
            'title_bg' => 'Мистериозна награда',
            'description_en' => null,
            'description_bg' => null,
            'reward_pool_snapshot' => [[
                'item_code' => '0:0',
                'item_name' => 'Kris',
                'item_width' => 1,
                'item_height' => 2,
                'level' => 0,
                'durability' => 255,
                'option_level' => 0,
                'has_skill' => false,
                'has_luck' => false,
                'excellent_mask' => 0,
                'ancient_option' => 0,
                'weight' => 1,
            ]],
            'selected_item_snapshot' => null,
            'delivery_attempts' => 0,
            'opened_at' => null,
            'claimed_at' => null,
        ];
    }
}
