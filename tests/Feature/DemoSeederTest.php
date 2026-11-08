<?php

namespace Tests\Feature;

use App\Models\ShopOffer;
use App\Models\WebsiteSetting;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Verifies the demo entry point selects fixtures by game-version profile.
 */
final class DemoSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the 97d+99i profile keeps its original fixture isolated.
     */
    public function test_selects_the_97d99i_fixture_for_the_97d99i_profile(): void
    {
        config()->set('game.profile.key', 'darksteam_97d99i');
        config()->set('demo.account_ids', ['demo01']);

        $this->seed(DemoSeeder::class);

        $this->assertSame('97d + 99i', WebsiteSetting::query()->value('brand_subtitle'));
        $this->assertSame(8, ShopOffer::query()->count());
        $this->assertDatabaseHas('shop_offer_items', [
            'item_image' => '/images/items/97d_99i/12004.gif',
        ]);
    }

    /**
     * Confirms the 1.0M profile selects only its adapter-compatible fixture.
     */
    public function test_selects_the_1_0m_fixture_for_the_1_0m_profile(): void
    {
        config()->set('game.profile.key', 'darksteam_1_0m');
        config()->set('demo.account_ids', ['demo01']);

        $this->seed(DemoSeeder::class);

        $this->assertSame('1.0M', WebsiteSetting::query()->value('brand_subtitle'));
        $this->assertSame(10, ShopOffer::query()->count());
        $this->assertDatabaseHas('shop_offer_items', [
            'item_image' => '/images/items/1_0m/130030.gif',
        ]);
    }

    /**
     * Confirms unsupported adapters cannot silently receive incompatible item data.
     */
    public function test_rejects_a_profile_without_a_demo_fixture(): void
    {
        config()->set('game.profile.key', 'unsupported_profile');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('No demo seed is available');

        $this->seed(DemoSeeder::class);
    }
}
