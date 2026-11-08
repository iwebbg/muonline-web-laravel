<?php

namespace Tests\Feature;

use App\Game\Versions\Version1_0M\ItemCodec1_0M;
use App\Market\MarketSaleType;
use App\Models\CreditPackage;
use App\Models\MarketListing;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Models\VoteSite;
use App\Models\WebsiteSetting;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Database\Seeders\Demo1_0MSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Verifies the version-scoped commerce and identity fixtures used by 1.0M demos.
 */
final class Demo1_0MSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the fixture installs 1.0M offers, Dark Lord content and valid item bytes.
     */
    public function test_seeder_installs_the_complete_1_0m_demo_fixture(): void
    {
        $this->travelTo('2026-11-08 15:00:00');
        config()->set('game.profile.key', 'darksteam_1_0m');
        config()->set('demo.account_ids', ['demo01']);
        $websiteSettingsIdBeforeInsert = null;
        WebsiteSetting::creating(function (WebsiteSetting $websiteSettings) use (&$websiteSettingsIdBeforeInsert): void {
            $websiteSettingsIdBeforeInsert = $websiteSettings->getAttribute('id');
        });

        $this->seed(Demo1_0MSeeder::class);

        $this->assertSame(4, CreditPackage::query()->count());
        $this->assertSame(10, ShopOffer::query()->count());
        $this->assertSame(29, ShopOfferItem::query()->count());
        $this->assertSame(5, ShopOffer::query()->where('type', ShopOfferType::Item)->count());
        $this->assertSame(5, ShopOffer::query()->where('type', ShopOfferType::MysteryBox)->count());
        $this->assertSame(5, MarketListing::query()->count());
        $this->assertSame(2, VoteSite::query()->count());

        $this->assertDatabaseHas('shop_offers', [
            'public_id' => '10a00001-0000-4000-8000-000000000005',
            'class_scope' => 'dl_le',
            'status' => ShopOfferStatus::Published->value,
        ]);
        $this->assertDatabaseHas('shop_offer_items', [
            'item_code' => '13:30',
            'item_name' => 'Cape of Lord',
            'item_image' => '/images/items/1_0m/130030.gif',
            'level' => 13,
        ]);
        $this->assertDatabaseMissing('shop_offer_items', [
            'item_image' => '/images/items/97d_99i/12004.gif',
        ]);

        $websiteSettings = WebsiteSetting::query()->sole();
        $this->assertNull($websiteSettingsIdBeforeInsert);
        $this->assertSame('DreamMU', $websiteSettings->brand_title);
        $this->assertSame('1.0M', $websiteSettings->brand_subtitle);

        $auction = MarketListing::query()
            ->where('sale_type', MarketSaleType::Auction)
            ->sole();
        $decodedItem = (new ItemCodec1_0M)->decode($auction->item_binary, 1)[0];
        $this->assertSame('demo01', $auction->seller_account_id);
        $this->assertSame(10, strlen($auction->item_binary));
        $this->assertSame(12, $decodedItem->group);
        $this->assertSame(4, $decodedItem->index);
        $this->assertSame(13, $decodedItem->level);
        $this->assertSame(hash('sha256', $auction->item_binary), $auction->item_fingerprint);
        $this->assertTrue($auction->expires_at->equalTo(now()->addDays(3)));
    }

    /**
     * Confirms rerunning the fixture restores managed records without duplicates.
     */
    public function test_seeder_is_idempotent_and_restores_managed_configuration(): void
    {
        config()->set('game.profile.key', 'darksteam_1_0m');
        config()->set('demo.account_ids', ['demo01']);
        $this->seed(Demo1_0MSeeder::class);

        $offer = ShopOffer::query()
            ->where('public_id', '10a00001-0000-4000-8000-000000000005')
            ->sole();
        $offer->update(['title_en' => 'Changed title']);
        $offer->items()->delete();
        CreditPackage::query()->where('title_en', 'Starter')->update(['credits' => 1]);
        WebsiteSetting::query()->update(['brand_title' => 'Changed brand']);
        VoteSite::query()->where('slug', 'interweb-vote')->sole()->delete();

        $this->seed(Demo1_0MSeeder::class);

        $this->assertSame(4, CreditPackage::query()->count());
        $this->assertSame(10, ShopOffer::query()->count());
        $this->assertSame(29, ShopOfferItem::query()->count());
        $this->assertSame(5, MarketListing::query()->count());
        $this->assertSame(2, VoteSite::query()->count());
        $this->assertSame(500, CreditPackage::query()->where('title_en', 'Starter')->value('credits'));
        $this->assertSame('Dark Lord Cape', $offer->refresh()->title_en);
        $this->assertSame(1, $offer->items()->count());
        $this->assertSame('DreamMU', WebsiteSetting::query()->value('brand_title'));
        $this->assertNull(VoteSite::withTrashed()->where('slug', 'interweb-vote')->value('deleted_at'));
    }

    /**
     * Confirms 1.0M item fixtures cannot be installed for another profile.
     */
    public function test_seeder_rejects_a_non_1_0m_profile(): void
    {
        config()->set('game.profile.key', 'darksteam_97d99i');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GAME_SERVER_PROFILE=darksteam_1_0m');

        $this->seed(Demo1_0MSeeder::class);
    }
}
