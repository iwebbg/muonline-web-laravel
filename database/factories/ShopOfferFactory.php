<?php

namespace Database\Factories;

use App\Models\ShopOffer;
use App\Shop\MysteryBoxRewardMode;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ShopOffer>
 */
class ShopOfferFactory extends Factory
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
            'type' => ShopOfferType::Item,
            'mystery_box_reward_mode' => MysteryBoxRewardMode::OneRandom,
            'class_scope' => ShopClassScope::All,
            'title_en' => fake()->words(3, true),
            'title_bg' => fake()->words(3, true),
            'description_en' => fake()->sentence(),
            'description_bg' => fake()->sentence(),
            'price_credits' => 100,
            'status' => ShopOfferStatus::Published,
            'sort_order' => 0,
            'published_at' => now(),
            'available_from' => null,
            'available_until' => null,
        ];
    }

    /**
     * Creates a sealed weighted mystery-box offer.
     */
    public function mysteryBox(): static
    {
        return $this->state(fn (array $attributes): array => ['type' => ShopOfferType::MysteryBox]);
    }

    /**
     * Creates a Mystery Box that releases every configured item.
     */
    public function allItems(): static
    {
        return $this->mysteryBox()->state(fn (array $attributes): array => [
            'mystery_box_reward_mode' => MysteryBoxRewardMode::AllItems,
        ]);
    }
}
