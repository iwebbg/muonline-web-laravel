<?php

namespace Database\Seeders;

use App\Game\Versions\Version1_0M\ItemCatalog1_0M;
use App\Game\Versions\Version1_0M\ItemCodec1_0M;
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
 * Restores curated commerce and identity fixtures for DarksTeam 1.0M demos.
 */
final class Demo1_0MSeeder extends Seeder
{
    private const PROFILE_KEY = 'darksteam_1_0m';

    /**
     * Creates the seed with the audited 1.0M item catalog and binary codec.
     */
    public function __construct(
        private readonly ItemCatalog1_0M $itemCatalog,
        private readonly ItemCodec1_0M $itemCodec,
    ) {}

    /**
     * Seeds deterministic 1.0M demo configuration without touching the game database.
     */
    public function run(): void
    {
        if (config('game.profile.key') !== self::PROFILE_KEY) {
            throw new LogicException('The 1.0M demo seed requires GAME_SERVER_PROFILE=darksteam_1_0m.');
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
     * Restores every offer and replaces its 1.0M item pool atomically.
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
     * Restores the editable 1.0M website identity snapshot.
     */
    private function seedWebsiteIdentity(): void
    {
        $websiteSettings = WebsiteSetting::query()->firstOrNew();
        $websiteSettings->fill([
            'brand_title' => 'DreamMU',
            'brand_subtitle' => '1.0M',
            'logo_path' => null,
            'home_title_en' => 'DreamMU',
            'home_title_bg' => 'DreamMU',
            'page_title_suffix' => 'DreamMU',
            'meta_description_en' => 'DreamMU 1.0M',
            'meta_description_bg' => 'DreamMU 1.0M',
            'meta_keywords_en' => 'DreamMU, MuOnline, 1.0M',
            'meta_keywords_bg' => 'DreamMU, MuOnline, 1.0M',
            'og_image_path' => null,
            'copyright_holder' => 'DreamMU',
            'revision' => 1,
            'updated_by_administrator_id' => null,
        ]);
        $websiteSettings->save();
    }

    /**
     * Restores enabled vote destinations while reviving soft-deleted fixtures.
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
     * Creates missing market examples from verified 1.0M item bytes.
     */
    private function seedInitialMarketListings(): void
    {
        $sellerAccountId = $this->demoSellerAccountId();

        foreach ($this->marketListings() as $index => $fixture) {
            $itemBinary = $this->itemCodec->create(
                group: $fixture['group'],
                index: $fixture['index'],
                level: $fixture['level'],
                durability: 255,
                optionLevel: 7,
                hasSkill: true,
                hasLuck: true,
                excellentMask: $fixture['excellent_mask'],
                ancientOption: 0,
                serial: 1_000_001 + $index,
            );
            $itemFingerprint = hash('sha256', $itemBinary);
            $isAuction = $fixture['sale_type'] === MarketSaleType::Auction->value;

            MarketListing::query()->firstOrCreate(
                ['public_id' => $fixture['public_id']],
                [
                    'idempotency_key' => 'demo-1-0m-market-'.($index + 1),
                    'request_hash' => hash('sha256', 'demo-1-0m-market-request-'.($index + 1)),
                    'seller_account_id' => $sellerAccountId,
                    'source_character_name' => 'DarkLord',
                    'source_vault' => 1,
                    'source_slot' => $fixture['source_slot'],
                    'item_binary' => $itemBinary,
                    'item_fingerprint' => $itemFingerprint,
                    'escrow_key' => $itemFingerprint,
                    'item_snapshot' => $this->marketItemSnapshot($fixture),
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
            throw new LogicException('The 1.0M demo market requires a valid first DEMO_ACCOUNTS value.');
        }

        return $accountId;
    }

    /**
     * Returns the shared Credits package fixtures.
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
     * Returns direct-item and mystery-box fixtures covering every 1.0M class family.
     *
     * @return list<array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}>
     */
    private function shopOffers(): array
    {
        return [
            $this->itemOffer('10a00001-0000-4000-8000-000000000001', 'dw_sm', 'SM Wings', 'Full-option Soul Master wings.', '12:4', 27),
            $this->itemOffer('10a00001-0000-4000-8000-000000000002', 'dk_bk', 'BK Wings', 'Full-option Blade Knight wings.', '12:5', 31),
            $this->itemOffer('10a00001-0000-4000-8000-000000000003', 'elf_me', 'ME Wings', 'Full-option Muse Elf wings.', '12:3', 31),
            $this->itemOffer('10a00001-0000-4000-8000-000000000004', 'mg', 'MG Wings', 'Full-option Magic Gladiator wings.', '12:6', 31),
            $this->itemOffer('10a00001-0000-4000-8000-000000000005', 'dl_le', 'Dark Lord Cape', 'A full-option Cape of Lord.', '13:30', 31),
            $this->setBox('10a00001-0000-4000-8000-000000000006', 'dw_sm', 'Mystery Wizard Set Item', 'Get a random full-option Legendary set item.', ['7:3', '8:3', '9:3', '10:3', '11:3']),
            $this->setBox('10a00001-0000-4000-8000-000000000007', 'dk_bk', 'Mystery Knight Set Item', 'Get a random full-option Dragon set item.', ['7:1', '8:1', '9:1', '10:1', '11:1']),
            $this->setBox('10a00001-0000-4000-8000-000000000008', 'elf_me', 'Mystery Elf Set Item', 'Get a random full-option Holy Spirit set item.', ['7:19', '8:19', '9:19', '10:19', '11:19']),
            $this->setBox('10a00001-0000-4000-8000-000000000009', 'mg', 'Mystery Gladiator Set Item', 'Get a random full-option Thunder set item.', ['8:20', '9:20', '10:20', '11:20']),
            $this->setBox('10a00001-0000-4000-8000-000000000010', 'dl_le', 'Mystery Dark Lord Set Item', 'Get a random full-option Dark Master set item.', ['7:28', '8:28', '9:28', '10:28', '11:28']),
        ];
    }

    /**
     * Builds one published direct-item offer from authoritative 1.0M metadata.
     *
     * @return array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}
     */
    private function itemOffer(
        string $publicId,
        string $classScope,
        string $title,
        string $description,
        string $itemCode,
        int $excellentMask,
    ): array {
        return [
            'offer' => $this->offer($publicId, 'item', $classScope, $title, $description),
            'items' => [$this->shopItem($itemCode, 0, $excellentMask)],
        ];
    }

    /**
     * Builds one published random-item set offer from authoritative 1.0M metadata.
     *
     * @param  list<string>  $itemCodes
     * @return array{offer: array<string, int|string>, items: list<array<string, int|string|bool|null>>}
     */
    private function setBox(
        string $publicId,
        string $classScope,
        string $title,
        string $description,
        array $itemCodes,
    ): array {
        $items = [];

        foreach ($itemCodes as $sortOrder => $itemCode) {
            $items[] = $this->shopItem($itemCode, $sortOrder, 63);
        }

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
     * Builds one full-option item using the 1.0M catalog as the metadata authority.
     *
     * @return array<string, int|string|bool|null>
     */
    private function shopItem(string $itemCode, int $sortOrder, int $excellentMask): array
    {
        [$group, $index] = $this->parseItemCode($itemCode);
        $definition = $this->itemCatalog->definitionFor($group, $index);

        if ($definition === null) {
            throw new LogicException("The 1.0M demo item {$itemCode} is missing from its catalog.");
        }

        return [
            'item_code' => $itemCode,
            'item_name' => $definition->name,
            'item_image' => $definition->image,
            'item_width' => $definition->width,
            'item_height' => $definition->height,
            'level' => 13,
            'durability' => 255,
            'option_level' => 7,
            'has_skill' => true,
            'has_luck' => true,
            'excellent_mask' => $excellentMask,
            'ancient_option' => 0,
            'weight' => 1,
            'sort_order' => $sortOrder,
        ];
    }

    /**
     * Parses an audited group-and-index item code.
     *
     * @return array{0: int, 1: int}
     */
    private function parseItemCode(string $itemCode): array
    {
        $parts = explode(':', $itemCode, 2);

        if (count($parts) !== 2 || ! ctype_digit($parts[0]) || ! ctype_digit($parts[1])) {
            throw new LogicException("The 1.0M demo item code {$itemCode} is invalid.");
        }

        return [(int) $parts[0], (int) $parts[1]];
    }

    /**
     * Returns the enabled demo vote destinations.
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
     * Returns active market examples for verified 1.0M items.
     *
     * @return list<array{public_id: string, group: int, index: int, level: int, excellent_mask: int, source_slot: int, price_credits: int, sale_type: string, minimum_bid_credits: int|null, bid_increment_credits: int|null}>
     */
    private function marketListings(): array
    {
        return [
            $this->marketListing('10b00001-0000-4000-8000-000000000001', 8, 1, 6, 100),
            $this->marketListing('10b00001-0000-4000-8000-000000000002', 12, 4, 16, 150, 27, MarketSaleType::Auction->value, 100, 10),
            $this->marketListing('10b00001-0000-4000-8000-000000000003', 9, 19, 4, 80),
            $this->marketListing('10b00001-0000-4000-8000-000000000004', 11, 28, 29, 125),
            $this->marketListing('10b00001-0000-4000-8000-000000000005', 13, 30, 2, 175, 31),
        ];
    }

    /**
     * Builds one market listing specification.
     *
     * @return array{public_id: string, group: int, index: int, level: int, excellent_mask: int, source_slot: int, price_credits: int, sale_type: string, minimum_bid_credits: int|null, bid_increment_credits: int|null}
     */
    private function marketListing(
        string $publicId,
        int $group,
        int $index,
        int $sourceSlot,
        int $priceCredits,
        int $excellentMask = 63,
        string $saleType = MarketSaleType::Fixed->value,
        ?int $minimumBidCredits = null,
        ?int $bidIncrementCredits = null,
    ): array {
        return [
            'public_id' => $publicId,
            'group' => $group,
            'index' => $index,
            'level' => 13,
            'excellent_mask' => $excellentMask,
            'source_slot' => $sourceSlot,
            'price_credits' => $priceCredits,
            'sale_type' => $saleType,
            'minimum_bid_credits' => $minimumBidCredits,
            'bid_increment_credits' => $bidIncrementCredits,
        ];
    }

    /**
     * Builds display metadata from the same 1.0M definition used for the binary item.
     *
     * @param  array{group: int, index: int, level: int, excellent_mask: int, source_slot: int}  $fixture
     * @return array<string, int|string|bool|null>
     */
    private function marketItemSnapshot(array $fixture): array
    {
        $definition = $this->itemCatalog->definitionFor($fixture['group'], $fixture['index']);

        if ($definition === null) {
            $code = $fixture['group'].':'.$fixture['index'];

            throw new LogicException("The 1.0M demo market item {$code} is missing from its catalog.");
        }

        return [
            'slot' => $fixture['source_slot'],
            'code' => $definition->code(),
            'name' => $definition->name,
            'width' => $definition->width,
            'height' => $definition->height,
            'image' => $definition->image,
            'excellentType' => $definition->excellentType,
            'optionType' => $definition->optionType,
            'rarity' => 'excellent',
            'level' => $fixture['level'],
            'durability' => 255,
            'optionLevel' => 7,
            'hasSkill' => true,
            'hasLuck' => true,
            'excellentMask' => $fixture['excellent_mask'],
            'ancientOption' => 0,
        ];
    }
}
