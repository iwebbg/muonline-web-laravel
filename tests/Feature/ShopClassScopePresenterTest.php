<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\Administrator;
use App\Models\ShopOffer;
use App\Shop\ShopClassScope;
use App\Shop\ShopClassScopePresenter;
use App\Shop\ShopOfferType;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies shop audiences show only the evolutions available in their season.
 */
final class ShopClassScopePresenterTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Displays matching evolution labels in admin selectors, player filters and offer badges.
     */
    #[DataProvider('seasonLabels')]
    public function test_shop_surfaces_use_the_active_season_evolutions(string $profileKey, string $wizardLabel, string $lordLabel): void
    {
        config()->set('game.profile.key', $profileKey);
        $profileClass = config('game.version_profiles.'.$profileKey);
        $this->app->instance(GameVersionProfile::class, new $profileClass);
        $presenter = $this->app->make(ShopClassScopePresenter::class);
        $this->assertSame($wizardLabel, $presenter->label('dw_sm'));

        if ($lordLabel !== '') {
            $this->assertSame($lordLabel, $presenter->label('dl_le'));
        }

        ShopOffer::factory()->create([
            'type' => ShopOfferType::MysteryBox,
            'class_scope' => ShopClassScope::DarkWizard,
        ]);
        $administrator = Administrator::factory()->create();
        $this->actingAs($administrator, 'admin')
            ->get(route('admin.shop-offers.create'))
            ->assertOk()
            ->assertSee($wizardLabel)
            ->assertDontSee('ui.shop.class_scopes.');

        $account = new GameAccount(1, 'shoplabels', 'Shop labels', null, false, false, 'unused');
        $response = $this->actingAs($account, 'web')->get(route('shop.mystery-boxes.index'));
        $response->assertOk()->assertDontSee('ui.shop.class_scopes.');
        $this->assertGreaterThanOrEqual(2, substr_count($response->getContent(), $wizardLabel));
    }

    /**
     * Supplies established evolution names for all supported adapters.
     *
     * @return array<string, array{string, string, string}>
     */
    public static function seasonLabels(): array
    {
        return [
            '97d+99i' => ['darksteam_97d99i', 'Dark Wizard / Soul Master', ''],
            'Season 1' => ['darksteam_1_0m', 'Dark Wizard / Soul Master', 'Dark Lord'],
            'Season 6' => ['muemu_season_6', 'Dark Wizard / Soul Master / Grand Master', 'Dark Lord / Lord Emperor'],
            'Season 21' => ['mudevs_season_21', 'Dark Wizard / Soul Master / Grand Master / Soul Wizard / Darkness Wizard', 'Dark Lord / Lord Emperor / Empire Lord / Force Empire'],
        ];
    }
}
