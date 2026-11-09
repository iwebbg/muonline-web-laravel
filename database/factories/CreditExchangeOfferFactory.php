<?php

namespace Database\Factories;

use App\Models\CreditExchangeOffer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CreditExchangeOffer>
 */
class CreditExchangeOfferFactory extends Factory
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
            'game_profile_key' => (string) config('game.profile.key'),
            'item_group' => 6,
            'item_index' => 0,
            'level' => 7,
            'option_level' => 0,
            'has_skill' => false,
            'has_luck' => true,
            'excellent_mask' => 10,
            'ancient_option' => 0,
            'item_name_snapshot' => 'Leather Pants',
            'item_image_snapshot' => null,
            'excellent_type' => 1,
            'option_type' => 0,
            'credits_per_item' => 50,
            'max_quantity_per_exchange' => 10,
            'daily_account_credit_limit' => 500,
            'enabled' => true,
            'sort_order' => 0,
            'revision' => 1,
        ];
    }
}
