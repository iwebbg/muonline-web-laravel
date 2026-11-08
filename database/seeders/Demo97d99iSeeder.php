<?php

namespace Database\Seeders;

use App\Market\MarketListingStatus;
use App\Market\MarketSaleType;
use App\Models\CreditPackage;
use App\Models\MarketListing;
use App\Models\ShopOffer;
use App\Models\VoteSite;
use App\Models\WebsiteSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Restores the curated commerce and identity fixtures for DarksTeam 97d+99i demos.
 */
final class Demo97d99iSeeder extends Seeder
{
    private const PROFILE_KEY = 'darksteam_97d99i';

    /**
     * Seeds deterministic 97d+99i demo configuration without touching the game database.
     */
    public function run(): void
    {
        if (config('game.profile.key') !== self::PROFILE_KEY) {
            throw new LogicException('The 97d+99i demo seed requires GAME_SERVER_PROFILE=darksteam_97d99i.');
        }

        DB::transaction(function (): void {
            $this->seedCreditPackages();
            $this->seedShopOffers();
            $this->seedWebsiteIdentity();
            $this->seedVoteSites();
            $this->seedInitialMarketListings();
        });
    }

    /**
     * Restores the four published Credits packages used by the payment demo.
     */
    private function seedCreditPackages(): void
    {
        foreach ($this->creditPackages() as $package) {
            CreditPackage::query()->updateOrCreate(
                ['public_id' => $package['public_id']],
                $package,
            );
        }
    }

    /**
     * Restores every offer and replaces its version-specific item pool atomically.
     */
    private function seedShopOffers(): void
    {
        foreach ($this->shopOffers() as $fixture) {
            $offerAttributes = [
                ...$fixture['offer'],
                'published_at' => now(),
                'available_from' => null,
                'available_until' => null,
            ];
            $offer = ShopOffer::query()->updateOrCreate(
                ['public_id' => $offerAttributes['public_id']],
                $offerAttributes,
            );

            $offer->items()->delete();
            $offer->items()->createMany($fixture['items']);
        }
    }

    /**
     * Restores the editable DreamMU website identity snapshot.
     */
    private function seedWebsiteIdentity(): void
    {
        $websiteSettings = WebsiteSetting::query()->firstOrNew();
        $websiteSettings->fill([
            'brand_title' => 'DreamMU',
            'brand_subtitle' => '97d + 99i',
            'logo_path' => null,
            'home_title_en' => 'DreamMU',
            'home_title_bg' => 'DreamMU',
            'page_title_suffix' => 'DreamMU',
            'meta_description_en' => 'DreamMU',
            'meta_description_bg' => 'DreamMU',
            'meta_keywords_en' => 'DreamMU',
            'meta_keywords_bg' => 'DreamMU',
            'og_image_path' => null,
            'copyright_holder' => 'DreamMU',
            'revision' => 1,
            'updated_by_administrator_id' => null,
        ]);
        $websiteSettings->save();
    }

    /**
     * Restores enabled vote destinations while reviving a previously soft-deleted fixture.
     */
    private function seedVoteSites(): void
    {
        foreach ($this->voteSites() as $attributes) {
            $voteSite = VoteSite::withTrashed()->firstOrNew([
                'slug' => $attributes['slug'],
            ]);
            $voteSite->fill($attributes);
            $voteSite->deleted_at = null;
            $voteSite->save();
        }
    }

    /**
     * Creates missing market examples without rewriting listings that acquired history.
     */
    private function seedInitialMarketListings(): void
    {
        $sellerAccountId = $this->demoSellerAccountId();

        foreach ($this->marketListings() as $index => $fixture) {
            $itemBinary = base64_decode($fixture['item_binary_base64'], true);

            if (! is_string($itemBinary) || strlen($itemBinary) !== 10) {
                throw new LogicException('Every 97d+99i market fixture must contain exactly 10 item bytes.');
            }

            $itemFingerprint = hash('sha256', $itemBinary);
            $isAuction = $fixture['sale_type'] === MarketSaleType::Auction->value;

            MarketListing::query()->firstOrCreate(
                ['public_id' => $fixture['public_id']],
                [
                    'idempotency_key' => 'demo-97d99i-market-'.($index + 1),
                    'request_hash' => hash('sha256', 'demo-97d99i-market-request-'.($index + 1)),
                    'seller_account_id' => $sellerAccountId,
                    'source_character_name' => $fixture['source_character_name'],
                    'source_vault' => $fixture['source_vault'],
                    'source_slot' => $fixture['source_slot'],
                    'item_binary' => $itemBinary,
                    'item_fingerprint' => $itemFingerprint,
                    'escrow_key' => $itemFingerprint,
                    'item_snapshot' => $fixture['item_snapshot'],
                    'price_credits' => $fixture['price_credits'],
                    'sale_type' => $fixture['sale_type'],
                    'minimum_bid_credits' => $fixture['minimum_bid_credits'],
                    'bid_increment_credits' => $fixture['bid_increment_credits'],
                    'status' => MarketListingStatus::Active,
                    'sync_attempts' => 1,
                    'activated_at' => now(),
                    'expires_at' => $isAuction
                        ? now()->addDays(3)
                        : now()->addDays((int) config('game.market_listing_duration_days', 30)),
                ],
            );
        }
    }

    /**
     * Resolves the first configured demo account accepted by the legacy schema.
     */
    private function demoSellerAccountId(): string
    {
        $accountIds = config('demo.account_ids', []);
        $accountId = is_array($accountIds) ? ($accountIds[0] ?? 'test') : 'test';

        if (! is_string($accountId) || preg_match('/\A[A-Za-z0-9_-]{4,10}\z/', $accountId) !== 1) {
            throw new LogicException('The 97d+99i demo market requires a valid first DEMO_ACCOUNTS value.');
        }

        return $accountId;
    }

    /**
     * Returns the exported Credits package fixtures.
     *
     * @return list<array<string, int|string>>
     */
    private function creditPackages(): array
    {
        return [
            $this->creditPackage('256aed4f-d27b-435a-ad47-08284747d7bf', 'Starter', 'Стартер', 500, 500),
            $this->creditPackage('e0f57cfc-70f4-4a5a-bc1c-2266f22110dc', 'Deluxe', 'Делукс', 2_500, 1_200),
            $this->creditPackage('cc6cbec8-2093-41ac-8296-846c6767685e', 'Pro', 'Про', 4_000, 1_500),
            $this->creditPackage('3943ff0f-13e2-4b78-bd54-2878358e0dc1', 'Ultimate', 'Ултимейт', 10_000, 2_000),
        ];
    }

    /**
     * Builds one published EUR Credits package.
     *
     * @return array<string, int|string>
     */
    private function creditPackage(
        string $publicId,
        string $titleEn,
        string $titleBg,
        int $credits,
        int $priceMinor,
    ): array {
        return [
            'public_id' => $publicId,
            'title_en' => $titleEn,
            'title_bg' => $titleBg,
            'credits' => $credits,
            'price_minor' => $priceMinor,
            'currency' => 'EUR',
            'status' => 'published',
            'sort_order' => 0,
        ];
    }

    /**
     * Returns the exported direct-item and mystery-box fixtures.
     *
     * @return list<array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}>
     */
    private function shopOffers(): array
    {
        return [
            $this->wingOffer('a4a7ea61-dbc6-4e6b-bd85-1bc47b59a0a9', 'dw_sm', 'SM Wings', 'A full option wings for SM', '12:4', 'Heaven Wings 2', '/images/items/97d_99i/12004.gif', 5, 3, 11, 27),
            $this->wingOffer('78db6353-493e-42b2-9f54-f40d65ff6f1a', 'dk_bk', 'BK Wings', 'A full option wings for BK', '12:5', 'Satan Wings 2', '/images/items/97d_99i/12005.gif', 3, 3, 11, 31),
            $this->wingOffer('758d7a17-51e2-4286-9c15-c6780fb50f58', 'elf_me', 'ME Wings', 'A full option wings for ME', '12:3', 'Fairy Wings 2', '/images/items/97d_99i/12003.gif', 5, 3, 0, 31),
            $this->wingOffer('7c44a706-ebcc-421b-9e4f-da1e2360ada3', 'mg', 'MG Wings', 'A full option wings for MG', '12:6', 'MG Wings', '/images/items/97d_99i/12006.gif', 4, 2, 11, 31),
            $this->setBox(
                publicId: '2e5d6e9f-4587-450e-a77d-76588453d81b',
                classScope: 'dw_sm',
                title: 'Mystery Wizard Set Item',
                description: 'Get a random full option set item for DW/SM.',
                items: [
                    $this->setItem('7:3', 'Legendary Helm', '/images/items/97d_99i/7003.gif', 2, 2, 0),
                    $this->setItem('8:3', 'Legendary Armor', '/images/items/97d_99i/8003.gif', 2, 2, 1),
                    $this->setItem('9:3', 'Legendary Pants', '/images/items/97d_99i/9003.gif', 2, 2, 2),
                    $this->setItem('10:3', 'Legendary Gloves', '/images/items/97d_99i/10003.gif', 2, 2, 3),
                    $this->setItem('11:3', 'Legendary Boots', '/images/items/97d_99i/11003.gif', 2, 2, 4),
                ],
            ),
            $this->setBox(
                publicId: '522ec081-abe8-4e19-bbde-494f160842ae',
                classScope: 'dk_bk',
                title: 'Mystery Knight Set Item',
                description: 'Get a random full option set item for DK/BK.',
                items: [
                    $this->setItem('7:1', 'Dragon Helm', '/images/items/97d_99i/7001.gif', 2, 2, 0),
                    $this->setItem('8:1', 'Dragon Armor', '/images/items/97d_99i/8001.gif', 2, 3, 1),
                    $this->setItem('9:1', 'Dragon Pants', '/images/items/97d_99i/9001.gif', 2, 2, 2),
                    $this->setItem('10:1', 'Dragon Gloves', '/images/items/97d_99i/10001.gif', 2, 2, 3),
                    $this->setItem('11:1', 'Dragon Boots', '/images/items/97d_99i/11001.gif', 2, 2, 4),
                ],
            ),
            $this->setBox(
                publicId: '4189250f-422c-4af1-b03d-0ce4af7216c7',
                classScope: 'elf_me',
                title: 'Mystery Elf Set Item',
                description: 'Get a random full option set item for Elf/ME.',
                items: [
                    $this->setItem('7:19', 'Divine Helm', '/images/items/97d_99i/70019.gif', 2, 2, 0),
                    $this->setItem('8:19', 'Divine Armor', '/images/items/97d_99i/80019.gif', 2, 2, 1),
                    $this->setItem('9:19', 'Divine Pants', '/images/items/97d_99i/90019.gif', 2, 2, 2),
                    $this->setItem('10:19', 'Divine Gloves', '/images/items/97d_99i/100019.gif', 2, 2, 3),
                    $this->setItem('11:19', 'Divine Boots', '/images/items/97d_99i/110019.gif', 2, 2, 4),
                ],
            ),
            $this->setBox(
                publicId: '977e1e2b-9dad-4b25-aa85-f75d6f4f9bdf',
                classScope: 'mg',
                title: 'Mystery Gladiator Set Item',
                description: 'Get a random full option set item for MG.',
                items: [
                    $this->setItem('8:20', 'Thunder Armor', '/images/items/97d_99i/80020.gif', 2, 3, 0),
                    $this->setItem('9:20', 'Thunder Pants', '/images/items/97d_99i/90020.gif', 2, 2, 1),
                    $this->setItem('10:20', 'Thunder Gloves', '/images/items/97d_99i/100020.gif', 2, 2, 2),
                    $this->setItem('11:20', 'Thunder Boots', '/images/items/97d_99i/110020.gif', 2, 2, 3),
                ],
            ),
        ];
    }

    /**
     * Builds one published full-option wings offer.
     *
     * @return array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}
     */
    private function wingOffer(
        string $publicId,
        string $classScope,
        string $title,
        string $description,
        string $itemCode,
        string $itemName,
        string $itemImage,
        int $itemWidth,
        int $itemHeight,
        int $level,
        int $excellentMask,
    ): array {
        return [
            'offer' => $this->offer($publicId, 'item', $classScope, $title, $description),
            'items' => [[
                'item_code' => $itemCode,
                'item_name' => $itemName,
                'item_image' => $itemImage,
                'item_width' => $itemWidth,
                'item_height' => $itemHeight,
                'level' => $level,
                'durability' => 255,
                'option_level' => 7,
                'has_skill' => true,
                'has_luck' => true,
                'excellent_mask' => $excellentMask,
                'ancient_option' => 0,
                'weight' => 1,
                'sort_order' => 0,
            ]],
        ];
    }

    /**
     * Builds one sealed random-item set offer.
     *
     * @param  list<array<string, int|string|bool|null>>  $items
     * @return array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}
     */
    private function setBox(
        string $publicId,
        string $classScope,
        string $title,
        string $description,
        array $items,
    ): array {
        return [
            'offer' => $this->offer($publicId, 'mystery_box', $classScope, $title, $description),
            'items' => $items,
        ];
    }

    /**
     * Builds one common published offer record.
     *
     * @return array<string, int|string>
     */
    private function offer(
        string $publicId,
        string $type,
        string $classScope,
        string $title,
        string $description,
    ): array {
        return [
            'public_id' => $publicId,
            'type' => $type,
            'mystery_box_reward_mode' => 'one_random',
            'class_scope' => $classScope,
            'title_en' => $title,
            'title_bg' => $title,
            'description_en' => $description,
            'description_bg' => $description,
            'price_credits' => 150,
            'status' => 'published',
            'sort_order' => 0,
        ];
    }

    /**
     * Builds one full-option mystery-box item.
     *
     * @return array<string, int|string|bool|null>
     */
    private function setItem(
        string $itemCode,
        string $itemName,
        string $itemImage,
        int $itemWidth,
        int $itemHeight,
        int $sortOrder,
    ): array {
        return [
            'item_code' => $itemCode,
            'item_name' => $itemName,
            'item_image' => $itemImage,
            'item_width' => $itemWidth,
            'item_height' => $itemHeight,
            'level' => 11,
            'durability' => 255,
            'option_level' => 7,
            'has_skill' => true,
            'has_luck' => true,
            'excellent_mask' => 63,
            'ancient_option' => 0,
            'weight' => 1,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Returns the exported vote destination fixtures.
     *
     * @return list<array<string, int|string|bool|null>>
     */
    private function voteSites(): array
    {
        return [
            $this->voteSite('interweb-vote', 'InterWeb Vote 1', 10),
            $this->voteSite('iweb-vote-2', 'InterWeb Vote 2', 15),
        ];
    }

    /**
     * Builds one enabled demo vote destination.
     *
     * @return array<string, int|string|bool|null>
     */
    private function voteSite(string $slug, string $name, int $rewardCredits): array
    {
        return [
            'slug' => $slug,
            'name_en' => $name,
            'name_bg' => $name,
            'url' => 'https://interweb.bg',
            'image_url' => null,
            'cooldown_hours' => 12,
            'reward_credits' => $rewardCredits,
            'reward_zen' => 1_000_000,
            'auto_reward_on_click' => true,
            'enabled' => true,
            'sort_order' => 0,
        ];
    }

    /**
     * Returns the exported active market examples with exact 97d+99i item bytes.
     *
     * @return list<array<string, mixed>>
     */
    private function marketListings(): array
    {
        return [
            $this->marketListing(
                publicId: '6d460545-7d3f-4a02-8ce7-449bb3f913e4',
                itemBinaryBase64: 'Ad//AAAEwv8AAA==',
                sourceSlot: 6,
                priceCredits: 100,
                itemSnapshot: $this->marketItemSnapshot(6, '8:1', 'Dragon Armor', 2, 3, '8001'),
            ),
            $this->marketListing(
                publicId: '16901f98-f7b5-47ab-8a53-320f7fe6a1d6',
                itemBinaryBase64: 'hN//AAAEwdsAAA==',
                sourceSlot: 16,
                priceCredits: 100,
                saleType: MarketSaleType::Auction->value,
                minimumBidCredits: 100,
                bidIncrementCredits: 10,
                itemSnapshot: $this->marketItemSnapshot(
                    slot: 16,
                    code: '12:4',
                    name: 'Heaven Wings 2',
                    width: 5,
                    height: 3,
                    imageCode: '12004',
                    excellentType: 4,
                    optionType: 1,
                    excellentMask: 27,
                ),
            ),
            $this->marketListing(
                publicId: 'a756637a-3a2a-43c0-9e11-3f410d298d7f',
                itemBinaryBase64: 'I9//AAAEwP8AAA==',
                sourceSlot: 4,
                priceCredits: 60,
                itemSnapshot: $this->marketItemSnapshot(4, '9:3', 'Legendary Pants', 2, 2, '9003'),
            ),
            $this->marketListing(
                publicId: 'e3b05759-d528-46f3-ac73-2a5fe2df2925',
                itemBinaryBase64: 'M9//AAAEw/8AAA==',
                sourceSlot: 29,
                priceCredits: 105,
                itemSnapshot: $this->marketItemSnapshot(29, '9:19', 'Divine Pants', 2, 2, '90019'),
            ),
            $this->marketListing(
                publicId: 'c449c302-a59c-402e-9f97-6d7003bed6de',
                itemBinaryBase64: 'Y9//AAAEv/8AAA==',
                sourceSlot: 2,
                priceCredits: 56,
                itemSnapshot: $this->marketItemSnapshot(2, '11:3', 'Legendary Boots', 2, 2, '11003'),
            ),
        ];
    }

    /**
     * Builds the display snapshot decoded from one exported 97d+99i item.
     *
     * @return array<string, int|string|bool>
     */
    private function marketItemSnapshot(
        int $slot,
        string $code,
        string $name,
        int $width,
        int $height,
        string $imageCode,
        int $excellentType = 1,
        int $optionType = 0,
        int $excellentMask = 63,
    ): array {
        return [
            'slot' => $slot,
            'code' => $code,
            'name' => $name,
            'width' => $width,
            'height' => $height,
            'image' => "/images/items/97d_99i/{$imageCode}.gif",
            'excellentType' => $excellentType,
            'optionType' => $optionType,
            'rarity' => 'excellent',
            'level' => 11,
            'durability' => 255,
            'optionLevel' => 7,
            'hasSkill' => true,
            'hasLuck' => true,
            'excellentMask' => $excellentMask,
            'ancientOption' => 0,
        ];
    }

    /**
     * Builds one initial market listing fixture.
     *
     * @param  array<string, mixed>  $itemSnapshot
     * @return array<string, mixed>
     */
    private function marketListing(
        string $publicId,
        string $itemBinaryBase64,
        int $sourceSlot,
        int $priceCredits,
        array $itemSnapshot,
        string $saleType = MarketSaleType::Fixed->value,
        ?int $minimumBidCredits = null,
        ?int $bidIncrementCredits = null,
    ): array {
        return [
            'public_id' => $publicId,
            'item_binary_base64' => $itemBinaryBase64,
            'source_character_name' => 'Gladiatora',
            'source_vault' => 1,
            'source_slot' => $sourceSlot,
            'item_snapshot' => $itemSnapshot,
            'price_credits' => $priceCredits,
            'sale_type' => $saleType,
            'minimum_bid_credits' => $minimumBidCredits,
            'bid_increment_credits' => $bidIncrementCredits,
        ];
    }
}
