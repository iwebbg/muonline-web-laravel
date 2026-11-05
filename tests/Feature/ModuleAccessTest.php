<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\ModuleProfile;
use App\Modules\ServerModule;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies server-authoritative module access and navigation boundaries.
 */
final class ModuleAccessTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Confirms configured defaults keep existing public modules available.
     */
    public function test_default_profile_keeps_market_and_registration_available(): void
    {
        $marketResponse = $this->get(route('market.index'));
        $registrationResponse = $this->get(route('register'));

        $marketResponse->assertOk();
        $registrationResponse->assertOk();
    }

    /**
     * Confirms the Web Shop stays inside the account area while Market remains in primary navigation.
     */
    public function test_web_shop_is_only_linked_from_account_navigation(): void
    {
        $homeResponse = $this->get(route('home'));
        $accountResponse = $this
            ->actingAs($this->account('module05'))
            ->get(route('account.dashboard'));

        $homeResponse
            ->assertOk()
            ->assertSee(route('market.index'), false)
            ->assertDontSee(route('shop.items.index'), false)
            ->assertDontSee(route('shop.mystery-boxes.index'), false);
        $accountResponse
            ->assertOk()
            ->assertSee(route('account.item-generator.index'), false);
    }

    /**
     * Confirms a disabled public module returns 404 and leaves no navigation link.
     */
    public function test_disabled_market_is_hidden_and_returns_not_found(): void
    {
        $this->persistModules([ServerModule::Market->value => false]);

        $marketResponse = $this->get(route('market.index'));
        $homeResponse = $this->get(route('home'));

        $marketResponse->assertNotFound();
        $homeResponse
            ->assertOk()
            ->assertDontSee(route('market.index'), false);
    }

    /**
     * Confirms disabled authentication support modules cannot be reached directly.
     */
    public function test_disabled_registration_and_recovery_return_not_found(): void
    {
        $this->persistModules([
            ServerModule::Registration->value => false,
            ServerModule::PasswordRecovery->value => false,
        ]);

        $registrationResponse = $this->get(route('register'));
        $recoveryResponse = $this->get(route('password.request'));
        $loginResponse = $this->get(route('login'));

        $registrationResponse->assertNotFound();
        $recoveryResponse->assertNotFound();
        $loginResponse
            ->assertOk()
            ->assertDontSee(route('register'), false)
            ->assertDontSee(route('password.request'), false);
    }

    /**
     * Confirms a disabled authenticated module cannot be bypassed by direct URL.
     */
    #[DataProvider('authenticatedModuleRoutes')]
    public function test_disabled_authenticated_module_returns_not_found_for_direct_get(
        ServerModule $module,
        string $routeName,
    ): void {
        $this->persistModules([$module->value => false]);

        $response = $this
            ->actingAs($this->account('module01'))
            ->get(route($routeName));

        $response->assertNotFound();
    }

    /**
     * Confirms a disabled VIP mutation is rejected before request processing.
     */
    public function test_disabled_vip_purchase_returns_not_found_for_direct_post(): void
    {
        $this->persistModules([ServerModule::Vip->value => false]);
        $csrfToken = 'disabled-vip-module-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('module02'))
            ->post(route('account.vip-purchases.store'), [
                '_token' => $csrfToken,
            ]);

        $response->assertNotFound();
    }

    /**
     * Confirms disabled account security rejects a direct credential mutation.
     */
    public function test_disabled_account_security_returns_not_found_for_direct_put(): void
    {
        $this->persistModules([ServerModule::AccountSecurity->value => false]);
        $csrfToken = 'disabled-account-security-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('module04'))
            ->put(route('account.credentials.update'), [
                '_token' => $csrfToken,
            ]);

        $response->assertNotFound();
    }

    /**
     * Confirms a crafted auction listing cannot bypass the disabled auction module.
     */
    public function test_disabled_auction_rejects_tampered_listing_request(): void
    {
        $this->persistModules([ServerModule::Auction->value => false]);
        $csrfToken = 'disabled-auction-module-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('module03'))
            ->post(route('market.listings.store'), [
                '_token' => $csrfToken,
                'character_name' => 'ModuleHero',
                'vault' => 0,
                'slot' => 0,
                'price_credits' => 100,
                'sale_type' => 'auction',
                'bid_increment_credits' => 10,
                'idempotency_key' => '123e4567-e89b-12d3-a456-426614174000',
            ]);

        $response->assertNotFound();
    }

    /**
     * Provides every authenticated read endpoint controlled by one module.
     *
     * @return array<string, array{ServerModule, string}>
     */
    public static function authenticatedModuleRoutes(): array
    {
        return [
            'vote rewards' => [ServerModule::VoteRewards, 'account.votes.index'],
            'credits' => [ServerModule::Credits, 'account.credits.index'],
            'account security' => [ServerModule::AccountSecurity, 'account.security.index'],
            'web storage' => [ServerModule::WebStorage, 'account.web-storage.index'],
            'jewel bank' => [ServerModule::JewelBank, 'account.jewel-bank.index'],
            'zen bank' => [ServerModule::ZenBank, 'account.zen-bank.index'],
        ];
    }

    /**
     * Persists a complete active profile with selected overrides.
     *
     * @param  array<string, bool>  $overrides
     */
    private function persistModules(array $overrides): void
    {
        $modules = [];

        foreach (ServerModule::cases() as $module) {
            $modules[$module->value] = true;
        }

        ModuleProfile::query()->create([
            'profile_key' => 'darksteam_97d99i',
            'game_version' => '97d + 99i',
            'modules' => array_replace($modules, $overrides),
            'revision' => 1,
        ]);
    }

    /**
     * Builds one in-memory legacy account for authenticated requests.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Module Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }
}
