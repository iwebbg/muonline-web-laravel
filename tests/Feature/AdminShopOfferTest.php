<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use App\Models\ShopOffer;
use App\Shop\ShopClassScope;
use App\Shop\ShopOfferStatus;
use App\Shop\ShopOfferType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies Web Shop offer permissions and catalog-backed validation.
 */
final class AdminShopOfferTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms only economy managers can access offer configuration.
     */
    public function test_shop_offer_manager_requires_economy_permission(): void
    {
        $economyManager = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $moderator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateAccounts])
            ->create();

        $this->actingAs($economyManager, 'admin')
            ->get(route('admin.shop-offers.index'))
            ->assertOk()
            ->assertSee('Web Shop offers');
        $this->actingAs($moderator, 'admin')
            ->get(route('admin.shop-offers.index'))
            ->assertForbidden();
    }

    /**
     * Confirms reward items are selected from the verified catalog with readable options.
     */
    public function test_shop_offer_editor_uses_the_verified_item_selector(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();

        $this->actingAs($administrator, 'admin')
            ->get(route('admin.shop-offers.create'))
            ->assertOk()
            ->assertSee('Search items by name')
            ->assertSee('Kris')
            ->assertSee('Excellent options')
            ->assertDontSee('Excellent mask');
    }

    /**
     * Confirms an item offer cannot conceal multiple reward definitions.
     */
    public function test_item_offer_requires_exactly_one_reward_item(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'shop-offer-validation-token';

        $this->actingAs($administrator, 'admin')
            ->withSession(['_token' => $csrfToken])
            ->post(route('admin.shop-offers.store'), [
                '_token' => $csrfToken,
                ...$this->validOfferPayload(),
                'type' => ShopOfferType::Item->value,
                'items' => [
                    $this->itemPayload('0:0'),
                    $this->itemPayload('0:1'),
                ],
            ])
            ->assertSessionHasErrors('items');

        $this->assertDatabaseCount('shop_offers', 0);
        $this->assertDatabaseCount('shop_offer_items', 0);
    }

    /**
     * Confirms a weighted Mystery Box persists trusted catalog item metadata.
     */
    public function test_economy_manager_can_create_mystery_box_offer(): void
    {
        $administrator = Administrator::factory()
            ->gameMaster([AdministratorPermission::ManageEconomy])
            ->create();
        $csrfToken = 'shop-offer-create-token';

        $response = $this->actingAs($administrator, 'admin')
            ->withSession(['_token' => $csrfToken])
            ->post(route('admin.shop-offers.store'), [
                '_token' => $csrfToken,
                ...$this->validOfferPayload(),
                'type' => ShopOfferType::MysteryBox->value,
                'items' => [
                    $this->itemPayload('0:0', 3),
                    $this->itemPayload('0:1', 1),
                ],
            ]);

        $offer = ShopOffer::query()->firstOrFail();
        $response->assertRedirect(route('admin.shop-offers.edit', $offer));
        $this->assertSame(2, $offer->items()->count());
        $this->assertDatabaseHas('shop_offer_items', [
            'shop_offer_id' => $offer->getKey(),
            'item_code' => '0:0',
            'item_name' => 'Kris',
            'weight' => 3,
        ]);
    }

    /**
     * Returns valid shared commercial and localization fields for an offer.
     *
     * @return array<string, int|string|null>
     */
    private function validOfferPayload(): array
    {
        return [
            'class_scope' => ShopClassScope::All->value,
            'title_en' => 'Warrior Mystery Box',
            'title_bg' => 'Мистериозна кутия',
            'description_en' => 'A hidden weighted reward.',
            'description_bg' => 'Скрита награда.',
            'price_credits' => 250,
            'status' => ShopOfferStatus::Published->value,
            'sort_order' => 0,
            'available_from' => null,
            'available_until' => null,
        ];
    }

    /**
     * Returns one valid version-aware item option payload.
     *
     * @return array<string, int|string>
     */
    private function itemPayload(string $itemCode, int $weight = 1): array
    {
        return [
            'item_code' => $itemCode,
            'level' => 0,
            'durability' => 255,
            'option_level' => 0,
            'has_skill' => 0,
            'has_luck' => 0,
            'excellent_mask' => 0,
            'ancient_option' => 0,
            'weight' => $weight,
        ];
    }
}
