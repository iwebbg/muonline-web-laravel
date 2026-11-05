<?php

namespace Database\Factories;

use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ShopOfferItem>
 */
class ShopOfferItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'shop_offer_id' => ShopOffer::factory(),
            'item_code' => '0:0',
            'item_name' => 'Kris',
            'item_image' => 'images/items/0/0.gif',
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
            'sort_order' => 0,
        ];
    }
}
