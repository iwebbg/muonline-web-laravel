<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Auth\GameAccount;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\Administrator;
use App\Models\GameCurrencyExchangeRate;
use App\Models\GameCurrencyExchangeRateRevision;
use App\Models\VipPlan;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies rate permissions, immutable revisions, stale edits and adapter isolation through HTTP.
 */
final class NativeCurrencyAdministrationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Selects the native profile while retaining the isolated fixture data source.
     */
    protected function setUp(): void
    {
        parent::setUp();
        $this->selectProfile('mudevs_season_21');
    }

    /**
     * Captures an authorized integer rate revision and rejects another edit of the old revision.
     */
    public function test_authorized_rate_edit_records_actor_and_rejects_stale_updates(): void
    {
        $administrator = Administrator::factory()->gameMaster([AdministratorPermission::ManageEconomy])->create();
        $rate = GameCurrencyExchangeRate::query()->where('currency', 'wcoin_c')->sole();
        $payload = ['credit_units' => 3, 'coin_units' => 10, 'minimum_units' => 2, 'maximum_units' => 100, 'enabled' => true, 'revision' => 1];

        $this->actingAs($administrator, 'admin')->get(route('admin.game-currency-rates.index'))->assertOk()->assertSee('WCoin(C)');
        $this->put(route('admin.game-currency-rates.update', $rate), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('admin.game-currency-rates.update', $rate), [...$payload, 'coin_units' => 20])->assertRedirect()->assertSessionHasErrors('revision');

        self::assertSame(10, $rate->refresh()->coin_units);
        self::assertSame(2, $rate->revision);
        $revision = GameCurrencyExchangeRateRevision::query()->sole();
        self::assertSame($administrator->getKey(), $revision->administrator_id);
        self::assertSame(1, $revision->before_snapshot['coin_units']);
        self::assertSame(10, $revision->snapshot['coin_units']);
    }

    /**
     * Rejects administrators without economy permission and invalid ratios without writing history.
     */
    public function test_permissions_and_ratio_validation_prevent_rate_changes(): void
    {
        $rate = GameCurrencyExchangeRate::query()->where('currency', 'wcoin_c')->sole();
        $moderator = Administrator::factory()->gameMaster([AdministratorPermission::ModerateAccounts])->create();
        $this->actingAs($moderator, 'admin')->get(route('admin.game-currency-rates.index'))->assertForbidden();
        $this->put(route('admin.game-currency-rates.update', $rate), [])->assertForbidden();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin')->put(route('admin.game-currency-rates.update', $rate), [
            'credit_units' => 0, 'coin_units' => '1.5', 'minimum_units' => 20, 'maximum_units' => 10, 'revision' => 1,
        ])->assertSessionHasErrors(['credit_units', 'coin_units', 'maximum_units']);
        self::assertSame(1, $rate->refresh()->revision);
        self::assertSame(0, GameCurrencyExchangeRateRevision::query()->count());
    }

    /**
     * Rejects a valid rate owned by another game profile without exposing or editing it.
     */
    public function test_rate_update_cannot_cross_profile_boundaries(): void
    {
        $rate = GameCurrencyExchangeRate::factory()->create(['game_profile_key' => 'muemu_season_6', 'coin_units' => 1]);
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin')->put(route('admin.game-currency-rates.update', $rate), [
            'credit_units' => 1, 'coin_units' => 9, 'minimum_units' => 1, 'maximum_units' => 100, 'revision' => 1,
        ])->assertNotFound();
        self::assertSame(1, $rate->refresh()->coin_units);
    }

    /**
     * Requires explicit native tiers while preserving existing character VIP offers.
     */
    public function test_native_vip_plan_requires_a_tier_and_keeps_legacy_metadata_empty(): void
    {
        $administrator = Administrator::factory()->create();
        $payload = ['slug' => 'native-month', 'name_en' => 'Native Month', 'duration_days' => 30, 'price_credits' => 100, 'sort_order' => 0, 'enabled' => true];
        $this->actingAs($administrator, 'admin')->post(route('admin.vip-plans.store'), $payload)->assertSessionHasErrors('native_account_level');
        $this->post(route('admin.vip-plans.store'), [...$payload, 'native_account_level' => 2])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vip_plans', ['slug' => 'native-month', 'native_account_level' => 2]);
        $this->selectProfile('muemu_season_6');
        $this->post(route('admin.vip-plans.store'), [...$payload, 'slug' => 'legacy-month', 'native_account_level' => 3])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('vip_plans', ['slug' => 'legacy-month', 'native_account_level' => null]);
    }

    /**
     * Keeps native service endpoints unavailable on older adapters even when modules are enabled.
     */
    public function test_legacy_adapter_does_not_resolve_native_currency_or_vip_services(): void
    {
        $this->selectProfile('muemu_season_6');
        $this->actingAs(new GameAccount(1, 'owner', 'Owner', null, false, false, ''));
        $this->get(route('account.native-services.index'))->assertNotFound();
        $this->post(route('account.native-services.exchange'), [])->assertNotFound();
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin')->get(route('admin.game-currency-rates.index'))->assertNotFound();
    }

    /**
     * Keeps native and legacy VIP catalogs separate and refuses edits or deletion across that boundary.
     */
    public function test_native_and_legacy_vip_plans_cannot_replace_each_other(): void
    {
        $administrator = Administrator::factory()->create();
        $legacy = VipPlan::factory()->create(['name_en' => 'Legacy Character Offer']);
        $native = VipPlan::factory()->create(['name_en' => 'Native Account Offer', 'native_account_level' => 2]);
        $this->actingAs($administrator, 'admin')->get(route('admin.vip-plans.index'))
            ->assertOk()->assertSee('Native Account Offer')->assertDontSee('Legacy Character Offer');
        $this->delete(route('admin.vip-plans.destroy', $legacy))->assertNotFound();
        $this->selectProfile('muemu_season_6');
        $this->get(route('admin.vip-plans.index'))->assertOk()
            ->assertSee('Legacy Character Offer')->assertDontSee('Native Account Offer');
        $this->delete(route('admin.vip-plans.destroy', $native))->assertNotFound();
        self::assertSame(2, VipPlan::query()->count());
    }

    /**
     * Refreshes version-owned bindings when testing a different adapter within the same application.
     */
    private function selectProfile(string $key): void
    {
        config()->set('game.profile.key', $key);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        (new AppServiceProvider($this->app))->register();
    }
}
