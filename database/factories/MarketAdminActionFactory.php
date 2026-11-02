<?php

namespace Database\Factories;

use App\Models\Administrator;
use App\Models\MarketAdminAction;
use App\Models\MarketListing;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MarketAdminAction>
 */
final class MarketAdminActionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'market_listing_id' => MarketListing::factory(),
            'administrator_id' => Administrator::factory(),
            'action' => 'retry_sync',
            'before_status' => 'delivery_pending',
            'after_status' => 'delivered',
            'outcome' => 'completed',
            'failure_code' => null,
            'context_snapshot' => [],
        ];
    }
}
