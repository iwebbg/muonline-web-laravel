<?php

namespace Tests\Feature;

use App\Market\MarketSaleType;
use App\Models\CreditPackage;
use App\Models\MarketListing;
use App\Models\ShopOffer;
use App\Models\ShopOfferItem;
use App\Models\VoteSite;
use App\Models\WebsiteSetting;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Database\Seeders\Demo97d99iSeeder;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use LogicException;
use Tests\TestCase;

/**
 * Verifies the version-scoped commerce and identity fixtures used by 97d+99i demos.
 */
final class Demo97d99iSeederTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms the exported configuration and exact market item bytes are installed.
     */
    public function test_seeder_installs_the_complete_97d99i_demo_fixture(): void
    {
        $this->travelTo('2026-11-06 15:00:00');
        config()->set('demo.account_ids', ['demo01']);

        $this->seed(Demo97d99iSeeder::class);

        $this->assertSame(4, CreditPackage::query()->count());
        $this->assertSame(8, ShopOffer::query()->count());
        $this->assertSame(23, ShopOfferItem::query()->count());
        $this->assertSame(4, ShopOffer::query()->where('type', ShopOfferType::Item)->count());
        $this->assertSame(4, ShopOffer::query()->where('type', ShopOfferType::MysteryBox)->count());
        $this->assertSame(5, MarketListing::query()->count());
        $this->assertSame(2, VoteSite::query()->count());

        $this->assertDatabaseHas('shop_offers', [
            'public_id' => '758d7a17-51e2-4286-9c15-c6780fb50f58',
            'class_scope' => 'elf_me',
            'status' => ShopOfferStatus::Published->value,
        ]);
        $this->assertDatabaseHas('shop_offers', [
            'public_id' => '977e1e2b-9dad-4b25-aa85-f75d6f4f9bdf',
            'class_scope' => 'mg',
        ]);
        $this->assertDatabaseHas('shop_offer_items', [
            'item_code' => '12:4',
            'level' => 11,
            'excellent_mask' => 27,
        ]);

        $websiteSettings = WebsiteSetting::query()->sole();
        $this->assertSame('DreamMU', $websiteSettings->brand_title);
        $this->assertSame('97d + 99i', $websiteSettings->brand_subtitle);

        $auction = MarketListing::query()
            ->where('sale_type', MarketSaleType::Auction)
            ->sole();
        $this->assertSame('demo01', $auction->seller_account_id);
        $this->assertSame(10, strlen($auction->item_binary));
        $this->assertSame(hash('sha256', $auction->item_binary), $auction->item_fingerprint);
        $this->assertTrue($auction->expires_at->equalTo(now()->addDays(3)));
    }

    /**
     * Confirms rerunning the fixture restores configuration without duplicate records.
     */
    public function test_seeder_is_idempotent_and_restores_managed_configuration(): void
    {
        config()->set('demo.account_ids', ['demo01']);
        $this->seed(Demo97d99iSeeder::class);

        $offer = ShopOffer::query()
            ->where('public_id', 'a4a7ea61-dbc6-4e6b-bd85-1bc47b59a0a9')
            ->sole();
        $offer->update(['title_en' => 'Changed title']);
        $offer->items()->delete();
        CreditPackage::query()->where('title_en', 'Starter')->update(['credits' => 1]);
        WebsiteSetting::query()->update(['brand_title' => 'Changed brand']);
        VoteSite::query()->where('slug', 'interweb-vote')->sole()->delete();

        $this->seed(Demo97d99iSeeder::class);

        $this->assertSame(4, CreditPackage::query()->count());
        $this->assertSame(8, ShopOffer::query()->count());
        $this->assertSame(23, ShopOfferItem::query()->count());
        $this->assertSame(5, MarketListing::query()->count());
        $this->assertSame(2, VoteSite::query()->count());
        $this->assertSame(500, CreditPackage::query()->where('title_en', 'Starter')->value('credits'));
        $this->assertSame('SM Wings', $offer->refresh()->title_en);
        $this->assertSame(1, $offer->items()->count());
        $this->assertSame('DreamMU', WebsiteSetting::query()->value('brand_title'));
        $this->assertNull(VoteSite::withTrashed()->where('slug', 'interweb-vote')->value('deleted_at'));
    }

    /**
     * Confirms the item fixtures cannot be installed for another game profile.
     */
    public function test_seeder_rejects_a_non_97d99i_profile(): void
    {
        config()->set('game.profile.key', 'another_version');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('GAME_SERVER_PROFILE=darksteam_97d99i');

        $this->seed(Demo97d99iSeeder::class);
    }
}
