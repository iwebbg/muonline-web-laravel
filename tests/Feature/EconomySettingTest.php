<?php

namespace Tests\Feature;

use App\Models\Administrator;
use App\Models\CreditExchangeOffer;
use App\Models\CreditExchangeOfferRevision;
use App\Models\EconomySetting;
use App\Models\EconomySettingRevision;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies paid-service settings are restricted, validated and audited.
 */
final class EconomySettingTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms every new administrator earning label exists in both locales.
     */
    public function test_credit_earning_administrator_copy_is_translated(): void
    {
        $translationKeys = [
            'credit_earning',
            'item_exchange',
            'item_exchange_help',
            'item_exchange_availability',
            'online_time_rewards',
            'online_time_rewards_availability',
            'online_minutes_per_unit',
            'online_credits_per_unit',
            'online_daily_credit_limit',
            'minutes',
            'reward_credits',
            'daily_limit',
            'item_exchange_offers',
            'item_exchange_offers_help',
            'catalog_item',
            'choose_item',
            'item_level',
            'additional_option',
            'excellent_mask',
            'excellent_options',
            'excellent_mask_help',
            'item_skill',
            'item_luck',
            'ancient_option',
            'credits_per_item',
            'max_exchange_quantity',
            'create_exchange_offer',
            'no_item_options',
            'enabled',
            'save',
            'archive',
            'credit_exchange_offer_created',
            'credit_exchange_offer_updated',
            'credit_exchange_offer_archived',
        ];

        foreach (['en', 'bg'] as $locale) {
            foreach ($translationKeys as $translationKey) {
                $key = 'ui.admin.'.$translationKey;

                $this->assertNotSame($key, __($key, [], $locale));
            }
        }
    }

    /**
     * Confirms unauthenticated visitors cannot read or update economy settings.
     */
    public function test_guest_cannot_access_economy_settings(): void
    {
        $this->get(route('admin.economy-settings.edit'))->assertRedirect(route('admin.login'));

        $this->put(route('admin.economy-settings.update'), [])->assertRedirect(route('admin.login'));
        $this->assertDatabaseCount('economy_settings', 0);
    }

    /**
     * Confirms an economy administrator can save one complete audited revision.
     */
    public function test_administrator_saves_paid_service_settings_with_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $csrfToken = 'economy-settings-token';
        $this->actingAs($administrator, 'admin');

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->put(route('admin.economy-settings.update'), [
                '_token' => $csrfToken,
                'itemGeneratorMarkupPercentage' => 20,
                'characterRenameEnabled' => true,
                'characterRenamePriceCredits' => 500,
                'characterClassChangeEnabled' => true,
                'characterClassChangePriceCredits' => 1_000,
                'characterResetPurchaseEnabled' => true,
                'characterResetPriceCredits' => 100,
                'characterLevelUpPointsPurchaseEnabled' => true,
                'characterLevelUpPointsPackageSize' => 1_000,
                'characterLevelUpPointsPriceCredits' => 100,
                'itemExchangeEnabled' => true,
                'onlineTimeRewardsEnabled' => true,
                'onlineMinutesPerUnit' => 10,
                'onlineCreditsPerUnit' => 5,
                'onlineDailyCreditLimit' => 150,
            ]);

        $response
            ->assertRedirect(route('admin.economy-settings.edit'))
            ->assertSessionHas('status', __('ui.admin.economy_settings_saved'));
        $settings = EconomySetting::query()->sole();
        $revision = EconomySettingRevision::query()->sole();
        $this->assertSame(20, $settings->item_generator_markup_percentage);
        $this->assertSame(500, $settings->character_rename_price_credits);
        $this->assertSame(100, $settings->character_reset_price_credits);
        $this->assertSame(1_000, $settings->character_level_up_points_package_size);
        $this->assertSame(1, $revision->revision);
        $this->assertSame($administrator->getKey(), $revision->changed_by_administrator_id);

        $historyResponse = $this->get(route('admin.economy-settings.edit'));

        $historyResponse
            ->assertOk()
            ->assertSee('aria-modal="true"', false)
            ->assertSee(__('ui.admin.revision_history'))
            ->assertSee(__('ui.admin.revision_changes'))
            ->assertSee(__('ui.admin.character_rename_availability'))
            ->assertSee(__('ui.admin.character_rename_price'));
    }

    /**
     * Confirms an administrator can publish one exact catalog-backed item signature.
     */
    public function test_administrator_creates_an_exact_item_exchange_offer_with_revision(): void
    {
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin');

        $response = $this->post(route('admin.credit-exchange-offers.store'), [
            'item_code' => '6:0',
            'level' => 7,
            'option_level' => 0,
            'has_skill' => false,
            'has_luck' => true,
            'excellent_mask' => 10,
            'ancient_option' => 0,
            'credits_per_item' => 50,
            'max_quantity_per_exchange' => 10,
            'daily_account_credit_limit' => 500,
            'enabled' => true,
            'sort_order' => 0,
        ]);

        $response->assertRedirect()->assertSessionHas('status', __('ui.admin.credit_exchange_offer_created'));
        $offer = CreditExchangeOffer::query()->sole();
        $revision = CreditExchangeOfferRevision::query()->sole();
        $this->assertSame(7, $offer->level);
        $this->assertTrue($offer->has_luck);
        $this->assertSame(10, $offer->excellent_mask);
        $this->assertSame(50, $offer->credits_per_item);
        $this->assertSame($offer->getKey(), $revision->credit_exchange_offer_id);
        $this->assertSame('created', $revision->action);
    }
}
