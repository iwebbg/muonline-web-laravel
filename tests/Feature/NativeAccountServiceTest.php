<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\Actions\PurchaseNativeAccountService;
use App\Economy\Exceptions\NativePurchaseRejected;
use App\Economy\NativeCurrency;
use App\Economy\NativePurchaseBlockReason;
use App\Economy\NativePurchaseKind;
use App\Economy\WalletCurrency;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\AccountWallet;
use App\Models\GameCurrencyExchangeRate;
use App\Models\NativeAccountPurchase;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\WalletTransaction;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies native coin/VIP purchases, ownership, integer prices and interrupted-grant recovery.
 */
final class NativeAccountServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = 'c371e29e-c748-4b84-a78b-2c3058ea8d79';

    /**
     * Selects native adapters and permits writes only in disposable network-isolated test connections.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Applies each declared currency once at its captured integer ratio without combining game balances.
     */
    #[DataProvider('currencies')]
    public function test_currency_exchange_charges_once_and_grants_only_the_selected_balance(NativeCurrency $currency, string $table, string $column): void
    {
        $this->seedAccount();
        GameCurrencyExchangeRate::query()->where('currency', $currency)->update(['credit_units' => 3, 'coin_units' => 7]);
        $name = $currency === NativeCurrency::Ruud ? 'Hero' : null;

        $first = $this->service()->exchange('owner', $currency, 5, $name, self::KEY);
        $repeat = $this->service()->exchange('owner', $currency, 5, $name, self::KEY);

        self::assertSame($first->getKey(), $repeat->getKey());
        self::assertSame(35, (int) $this->game()->table($table)->value($column));
        self::assertSame(985, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_AccountPurchase')->count());
        self::assertSame(15, $first->credit_cost);
        self::assertSame(35, $first->after_snapshot['balance']);
    }

    /**
     * Enumerates all native account and character currencies using their actual source columns.
     *
     * @return iterable<string, array{NativeCurrency, string, string}>
     */
    public static function currencies(): iterable
    {
        yield 'WCoinC' => [NativeCurrency::WCoinC, 'CashShopData', 'WCoinC'];
        yield 'WCoinP' => [NativeCurrency::WCoinP, 'CashShopData', 'WCoinP'];
        yield 'Goblin' => [NativeCurrency::GoblinPoints, 'CashShopData', 'GoblinPoint'];
        yield 'Lucky' => [NativeCurrency::LuckyCoins, 'LuckyCoin', 'LuckyCoin'];
        yield 'Ruud' => [NativeCurrency::Ruud, 'Character', 'RuudMoney'];
    }

    /**
     * Delivers exactly one captured grant after a response loss even when the operator changes the rate.
     */
    public function test_interrupted_grant_can_resume_without_a_second_debit_or_changed_rate(): void
    {
        $this->seedAccount();
        Exceptions::fake();
        $interrupt = true;
        $this->app['events']->listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$interrupt): void {
            if ($event->connectionName === 'game' && $event->connection->transactionLevel() === 0 && $interrupt) {
                $interrupt = false;
                throw new RuntimeException('Simulated loss after a native currency commit.');
            }
        });
        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);
            self::fail('An uncertain commit must remain pending.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::PurchasePending, $exception->reason);
        }
        $pending = NativeAccountPurchase::query()->sole();
        self::assertSame(CharacterOperationStatus::Pending, $pending->status);
        self::assertSame(990, AccountWallet::query()->sole()->balance);
        self::assertSame(10, $this->game()->table('CashShopData')->value('WCoinC'));
        GameCurrencyExchangeRate::query()->update(['credit_units' => 100, 'coin_units' => 200, 'enabled' => false]);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);

        $completed = $this->service()->resume('owner', $pending);

        self::assertSame(CharacterOperationStatus::Completed, $completed->status);
        self::assertSame(990, AccountWallet::query()->sole()->balance);
        self::assertSame(10, $this->game()->table('CashShopData')->value('WCoinC'));
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_AccountPurchase')->count());
        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * Rejects foreign Ruud targets before charging the wallet.
     */
    public function test_foreign_character_cannot_receive_ruud_or_consume_credits(): void
    {
        $this->seedAccount();
        $this->game()->table('Character')->insert(['AccountID' => 'other', 'Name' => 'Foreign', 'CharacterIndex' => 99, 'Class' => 16, 'RuudMoney' => 0]);

        $this->actingAs($this->account())->post(route('account.native-services.exchange'), [
            'currency' => 'ruud', 'units' => 10, 'character_name' => 'Foreign', 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasErrors('native_purchase');

        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, WalletTransaction::query()->count());
        self::assertSame(0, NativeAccountPurchase::query()->count());
    }

    /**
     * Rejects and refunds a connected account once while retaining its native rejection receipt.
     */
    public function test_online_exchange_does_not_change_native_balances(): void
    {
        $this->seedAccount();
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);

        $this->actingAs($this->account())->post(route('account.native-services.exchange'), [
            'currency' => 'wcoin_c', 'units' => 10, 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasErrors('native_purchase');

        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, $this->game()->table('CashShopData')->count());
        self::assertSame(2, WalletTransaction::query()->count());
        self::assertSame(CharacterOperationStatus::Failed, NativeAccountPurchase::query()->sole()->status);
        self::assertSame(1, $this->game()->table('IWEB_S21_AccountPurchase')->count());
    }

    /**
     * Refuses a grant above the source SQL INT limit and restores the complete Credits charge.
     */
    public function test_native_balance_overflow_refunds_without_changing_any_coin(): void
    {
        $this->seedAccount();
        $this->game()->table('CashShopData')->insert(['AccountID' => 'owner', 'WCoinC' => 2147483640, 'WCoinP' => 12, 'GoblinPoint' => 34]);

        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);
            self::fail('Native signed INT balances cannot overflow.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::BalanceLimit, $exception->reason);
        }

        self::assertSame(2147483640, $this->game()->table('CashShopData')->value('WCoinC'));
        self::assertSame(12, $this->game()->table('CashShopData')->value('WCoinP'));
        self::assertSame(34, $this->game()->table('CashShopData')->value('GoblinPoint'));
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
    }

    /**
     * Extends account membership once without creating or modifying a character VIP entitlement.
     */
    public function test_account_vip_extends_native_tier_and_expiry_once(): void
    {
        $this->seedAccount();
        $this->game()->table('MEMB_INFO')->update(['AccountLevel' => 2, 'AccountExpireDate' => '2070-01-01 12:30:00']);
        $plan = VipPlan::factory()->create(['enabled' => true, 'native_account_level' => 2, 'duration_days' => 30, 'price_credits' => 200]);

        $first = $this->service()->purchaseVip('owner', $plan->getKey(), self::KEY);
        $repeat = $this->service()->purchaseVip('owner', $plan->getKey(), self::KEY);

        self::assertSame($first->getKey(), $repeat->getKey());
        self::assertSame(2, $this->game()->table('MEMB_INFO')->value('AccountLevel'));
        self::assertSame('2070-01-31 12:30:00', $this->game()->table('MEMB_INFO')->value('AccountExpireDate'));
        self::assertSame(800, AccountWallet::query()->sole()->balance);
        self::assertSame(0, VipEntitlement::query()->count());
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(NativePurchaseKind::AccountVip, $first->kind);
        $plan->delete();
        $this->actingAs($this->account())->get(route('account.native-services.index'))
            ->assertOk()->assertSee('Tier 2')->assertSee('30 days');
    }

    /**
     * Refuses a tier switch that would discard an already active paid VIP period.
     */
    public function test_active_vip_cannot_be_replaced_by_a_different_tier(): void
    {
        $this->seedAccount();
        $this->game()->table('MEMB_INFO')->update(['AccountLevel' => 3, 'AccountExpireDate' => '2070-01-01 12:30:00']);
        $plan = VipPlan::factory()->create(['enabled' => true, 'native_account_level' => 1, 'duration_days' => 30, 'price_credits' => 200]);

        try {
            $this->service()->purchaseVip('owner', $plan->getKey(), self::KEY);
            self::fail('An active paid VIP period cannot be discarded by another tier.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::DifferentVipTier, $exception->reason);
        }

        self::assertSame(3, $this->game()->table('MEMB_INFO')->value('AccountLevel'));
        self::assertSame('2070-01-01 12:30:00', $this->game()->table('MEMB_INFO')->value('AccountExpireDate'));
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
    }

    /**
     * Shows native offers and private purchase history without accepting foreign recovery requests.
     */
    public function test_native_service_page_and_recovery_remain_account_scoped(): void
    {
        $this->seedAccount();
        $purchase = NativeAccountPurchase::factory()->create(['account_id' => 'other']);
        $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);

        $this->actingAs($this->account())->get(route('account.native-services.index'))
            ->assertOk()->assertSee('Credits → game coins')->assertSee('10 WCoin(C)')->assertSee('Game account VIP');
        $this->post(route('account.native-services.resume', $purchase))->assertNotFound();
    }

    /**
     * Returns unchanged balances when the global mutation switches remain closed.
     */
    public function test_closed_global_writes_prevent_native_purchases_before_a_charge(): void
    {
        $this->seedAccount();
        config()->set('game.writes_enabled', false);

        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);
            self::fail('A closed write gate must prevent charges.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::WritesDisabled, $exception->reason);
        }

        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, WalletTransaction::query()->count());
    }

    /**
     * Rejects altered replay intent without a second charge or grant.
     */
    public function test_reusing_a_purchase_key_for_another_quantity_is_rejected(): void
    {
        $this->seedAccount();
        $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);
        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 11, null, self::KEY);
            self::fail('Changed intent must not reuse a purchase.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::IdempotencyConflict, $exception->reason);
        }
        self::assertSame(990, AccountWallet::query()->sole()->balance);
        self::assertSame(10, $this->game()->table('CashShopData')->value('WCoinC'));
        self::assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Prevents an unaffordable exchange from reaching the native adapter or making a refund.
     */
    public function test_insufficient_credits_leave_native_balances_and_receipts_untouched(): void
    {
        $this->seedAccount();
        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 1001, null, self::KEY);
            self::fail('An unaffordable exchange must be rejected.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::InsufficientCredits, $exception->reason);
        }
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, WalletTransaction::query()->count());
        self::assertSame(0, $this->game()->table('CashShopData')->count());
        self::assertSame(0, $this->game()->table('IWEB_S21_AccountPurchase')->count());
    }

    /**
     * Keeps an uncertain receipt failure pending and safely grants the original purchase after repair.
     */
    public function test_receipt_insert_failure_rolls_back_game_grant_without_refunding_an_uncertain_purchase(): void
    {
        $this->seedAccount();
        Exceptions::fake();
        $this->game()->unprepared("CREATE TRIGGER reject_native_receipt BEFORE INSERT ON IWEB_S21_AccountPurchase BEGIN SELECT RAISE(ABORT, 'Receipt unavailable'); END");
        try {
            $this->service()->exchange('owner', NativeCurrency::WCoinC, 10, null, self::KEY);
            self::fail('Unavailable receipts must leave a protected pending purchase.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::PurchasePending, $exception->reason);
        }
        self::assertSame(0, $this->game()->table('CashShopData')->count());
        self::assertSame(990, AccountWallet::query()->sole()->balance);
        $this->game()->statement('DROP TRIGGER reject_native_receipt');
        $this->service()->resume('owner', NativeAccountPurchase::query()->sole());
        self::assertSame(10, $this->game()->table('CashShopData')->value('WCoinC'));
        self::assertSame(1, WalletTransaction::query()->count());
        Exceptions::assertReported(QueryException::class);
    }

    /**
     * Blocks restored offline activity so cached game balances cannot overwrite an exchange.
     */
    public function test_restored_offline_activity_prevents_coin_exchange(): void
    {
        $this->seedAccount();
        $this->game()->table('RestoreOffline')->insert(['Account' => 'owner', 'Name' => 'Hero', 'Status' => 1]);
        $this->actingAs($this->account())->post(route('account.native-services.exchange'), [
            'currency' => 'wcoin_c', 'units' => 10, 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasErrors('native_purchase');
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, $this->game()->table('CashShopData')->count());
    }

    /**
     * Rejects an extension outside native smalldatetime range without discarding membership or Credits.
     */
    public function test_vip_expiry_cannot_overflow_native_date_storage(): void
    {
        $this->seedAccount();
        $this->game()->table('MEMB_INFO')->update(['AccountLevel' => 2, 'AccountExpireDate' => '2079-06-01 12:30:00']);
        $plan = VipPlan::factory()->create(['enabled' => true, 'native_account_level' => 2, 'duration_days' => 30, 'price_credits' => 200]);
        try {
            $this->service()->purchaseVip('owner', $plan->getKey(), self::KEY);
            self::fail('Native date storage cannot overflow.');
        } catch (NativePurchaseRejected $exception) {
            self::assertSame(NativePurchaseBlockReason::InvalidQuantity, $exception->reason);
        }
        self::assertSame('2079-06-01 12:30:00', $this->game()->table('MEMB_INFO')->value('AccountExpireDate'));
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
    }

    /**
     * Uses only administrator rate terms when a player submits a successful exchange with invented price fields.
     */
    public function test_exchange_endpoint_ignores_client_prices_and_grant_amounts(): void
    {
        $this->seedAccount();
        GameCurrencyExchangeRate::query()->where('currency', NativeCurrency::WCoinP)->update(['credit_units' => 3, 'coin_units' => 7]);
        $this->actingAs($this->account())->post(route('account.native-services.exchange'), [
            'currency' => 'wcoin_p', 'units' => 3, 'idempotency_key' => self::KEY,
            'credit_cost' => 1, 'amount' => 999999,
        ])->assertRedirect(route('account.native-services.index'))->assertSessionHasNoErrors();
        self::assertSame(991, AccountWallet::query()->sole()->balance);
        self::assertSame(21, $this->game()->table('CashShopData')->value('WCoinP'));
        self::assertSame(9, NativeAccountPurchase::query()->sole()->credit_cost);
    }

    /**
     * Applies a successful account VIP purchase through the native endpoint without accepting a submitted tier.
     */
    public function test_vip_endpoint_uses_the_configured_plan_instead_of_client_terms(): void
    {
        $this->seedAccount();
        $plan = VipPlan::factory()->create(['enabled' => true, 'native_account_level' => 2, 'duration_days' => 30, 'price_credits' => 200]);
        $this->actingAs($this->account())->post(route('account.native-services.vip'), [
            'plan_id' => $plan->getKey(), 'idempotency_key' => self::KEY,
            'native_account_level' => 3, 'duration_days' => 3650, 'price_credits' => 1,
        ])->assertRedirect(route('account.native-services.index'))->assertSessionHasNoErrors();
        self::assertSame(800, AccountWallet::query()->sole()->balance);
        self::assertSame(2, $this->game()->table('MEMB_INFO')->value('AccountLevel'));
        self::assertSame(30, NativeAccountPurchase::query()->sole()->mutation_snapshot['duration_days']);
        self::assertSame(0, VipEntitlement::query()->count());
    }

    /**
     * Rejects unsupported currencies and incomplete Ruud requests before creating a financial operation.
     */
    public function test_invalid_currency_and_missing_ruud_target_are_rejected_before_charging(): void
    {
        $this->seedAccount();
        $this->actingAs($this->account())->post(route('account.native-services.exchange'), [
            'currency' => 'invented_coin', 'units' => 1, 'idempotency_key' => self::KEY,
        ])->assertSessionHasErrors('currency');
        $this->post(route('account.native-services.exchange'), [
            'currency' => 'ruud', 'units' => 1, 'idempotency_key' => self::KEY,
        ])->assertSessionHasErrors('character_name');
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(0, NativeAccountPurchase::query()->count());
    }

    /**
     * Seeds a complete native owner and website wallet without contacting a game server.
     */
    private function seedAccount(): void
    {
        $this->game()->table('MEMB_INFO')->insert(['memb_guid' => 1, 'memb___id' => 'owner', 'AccountLevel' => 0, 'AccountExpireDate' => '1900-01-01 00:00:00']);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert(['AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42, 'Class' => 16, 'RuudMoney' => 0]);
        AccountWallet::factory()->create(['account_id' => 'owner', 'currency' => WalletCurrency::Credits, 'balance' => 1000]);
    }

    /**
     * Resolves the real native payment coordinator.
     */
    private function service(): PurchaseNativeAccountService
    {
        return $this->app->make(PurchaseNativeAccountService::class);
    }

    /**
     * Returns an exact authenticated game identity for endpoint tests.
     */
    private function account(): GameAccount
    {
        return new GameAccount(1, 'owner', 'Owner', null, false, false, '');
    }

    /**
     * Returns the disposable native game database connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
