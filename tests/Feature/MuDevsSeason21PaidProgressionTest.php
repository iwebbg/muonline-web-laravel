<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\CharacterServices\PurchasedResetPolicy;
use App\Economy\WalletCurrency;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterServiceRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\AccountWallet;
use App\Models\CharacterServiceOperation;
use App\Models\EconomySetting;
use App\Models\ResetProfile;
use App\Models\WalletTransaction;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Exercises native paid progression, exact replay, interrupted commits and private recovery.
 */
final class MuDevsSeason21PaidProgressionTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = '1159bde4-34e9-4c4e-af27-8235065f1ab1';

    /**
     * Selects the native adapters with writes permitted only in isolated test databases.
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
     * Charges once, journals once and never reallocates or repeats a native point purchase.
     */
    public function test_points_endpoint_charges_and_applies_one_package_once(): void
    {
        $this->seedPurchase();
        $payload = ['character_name' => 'Hero', 'package_count' => 1, 'idempotency_key' => self::KEY];

        $this->actingAs($this->account())->post(route('account.character-level-up-points-purchases.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('account.character-level-up-points-purchases.store'), $payload)
            ->assertRedirect()->assertSessionHasNoErrors();

        self::assertSame(2200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
        self::assertSame(CharacterOperationStatus::Completed, CharacterServiceOperation::query()->sole()->status);
    }

    /**
     * Applies the captured native reset rules while preserving dependent records and Dark Lord audit stats.
     */
    public function test_reset_package_preserves_native_progression_and_inventory(): void
    {
        $this->seedPurchase();
        ResetProfile::factory()->create([
            'inventory_policy' => InventoryPolicy::KeepAll, 'clear_stats' => true,
            'points_mode' => PointsMode::Cumulative, 'points_per_reset' => 500,
        ]);
        $before = $this->game()->table('Character')->first();
        $this->game()->table('MasterSkillTree')->insert(['Name' => 'Stale', 'CharacterIndex' => 42, 'MasterLevel' => 600, 'MasterPoint' => 99]);

        $this->actingAs($this->account())->post(route('account.character-reset-purchases.store'), [
            'character_name' => 'Hero', 'package_count' => 5, 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $after = $this->game()->table('Character')->first();
        self::assertSame(7, $after->ResetCount);
        self::assertSame(1, $after->cLevel);
        self::assertSame(3500, $after->LevelUpPoint);
        self::assertSame(25, $after->Leadership);
        self::assertSame($before->Money, $after->Money);
        self::assertSame($before->Inventory, $after->Inventory);
        self::assertSame($before->Quest, $after->Quest);
        self::assertSame($before->MagicList, $after->MagicList);
        self::assertSame($before->RuudMoney, $after->RuudMoney);
        self::assertSame($before->MasterResetCount, $after->MasterResetCount);
        self::assertSame(600, $this->game()->table('MasterSkillTree')->value('MasterLevel'));
        self::assertSame(1500, CharacterServiceOperation::query()->sole()->before_snapshot['command']);
        self::assertSame(25, CharacterServiceOperation::query()->sole()->after_snapshot['command']);
        self::assertSame(500, AccountWallet::query()->sole()->balance);
    }

    /**
     * Keeps an ambiguous committed purchase pending without refunding, then acknowledges its saved result.
     */
    public function test_interrupted_commit_recovers_without_a_second_charge_or_point_grant(): void
    {
        $this->seedPurchase();
        Exceptions::fake();
        $interrupt = true;
        $this->app['events']->listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$interrupt): void {
            if ($event->connectionName === 'game' && $event->connection->transactionLevel() === 0 && $interrupt) {
                $interrupt = false;
                throw new RuntimeException('Simulated response loss after the native commit.');
            }
        });

        try {
            $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
            self::fail('An ambiguous native outcome must remain pending.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::OperationInProgress, $exception->reason);
        }

        self::assertSame(CharacterOperationStatus::Pending, CharacterServiceOperation::query()->sole()->status);
        self::assertNull(CharacterServiceOperation::query()->sole()->refund_wallet_transaction_id);
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(2200, $this->game()->table('Character')->value('LevelUpPoint'));
        $this->actingAs($this->account())->get('/account')->assertOk()
            ->assertSee('Unfinished character purchases')->assertSee(self::KEY);
        $this->actingAs($this->account('other'))->get('/account')->assertOk()->assertDontSee(self::KEY);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);
        EconomySetting::query()->update(['character_level_up_points_package_size' => 9000, 'character_level_up_points_price_credits' => 999]);

        $completed = $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);

        self::assertSame(CharacterOperationStatus::Completed, $completed->status);
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(2200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * Rolls back character changes when the matching native receipt cannot be inserted.
     */
    public function test_receipt_failure_leaves_no_points_and_can_resume_the_original_purchase(): void
    {
        $this->seedPurchase();
        Exceptions::fake();
        $this->game()->unprepared("CREATE TRIGGER reject_receipt BEFORE INSERT ON IWEB_S21_CharacterOperation BEGIN SELECT RAISE(ABORT, 'receipt denied'); END");

        try {
            $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
            self::fail('Receipt failure must keep the purchase pending.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::OperationInProgress, $exception->reason);
        }

        self::assertSame(1200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(0, $this->game()->table('IWEB_S21_CharacterOperation')->count());
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        $this->game()->unprepared('DROP TRIGGER reject_receipt');
        $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
        self::assertSame(2200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Rejects a new purchase identity while the original account/character purchase awaits confirmation.
     */
    public function test_pending_purchase_cannot_be_replaced_by_a_new_charge(): void
    {
        $this->seedPurchase();
        CharacterServiceOperation::factory()->create([
            'account_id' => 'owner', 'character_name' => 'Hero',
            'service_type' => CharacterServiceType::LevelUpPointsPurchase,
        ]);

        $this->expectException(CharacterServiceRejected::class);
        $this->expectExceptionMessage(CharacterServiceBlockReason::OperationInProgress->value);
        try {
            $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
        } finally {
            self::assertSame(1000, AccountWallet::query()->sole()->balance);
            self::assertSame(0, WalletTransaction::query()->count());
            self::assertSame(1200, $this->game()->table('Character')->value('LevelUpPoint'));
        }
    }

    /**
     * Refunds a definitive offline-state rejection and records no native receipt.
     */
    public function test_online_purchase_is_rejected_and_its_debit_refunded(): void
    {
        $this->seedPurchase();
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);

        $this->actingAs($this->account())->post(route('account.character-level-up-points-purchases.store'), [
            'character_name' => 'Hero', 'package_count' => 1, 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasErrors('character_service');

        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(2, WalletTransaction::query()->count());
        self::assertSame(1200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
        self::assertSame(CharacterOperationStatus::Failed, CharacterServiceOperation::query()->sole()->status);
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 0]);
        try {
            $this->app->make(SqlServerMuDevsSeason21CharacterServiceRepository::class)->applyPurchaseForOperation(
                'owner', 'Hero', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'character-service:'.self::KEY,
            );
            self::fail('A refunded rejection must remain rejected after the account goes offline.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::CharacterOnline, $exception->reason);
        }
        self::assertSame(1200, $this->game()->table('Character')->value('LevelUpPoint'));
    }

    /**
     * Never replays or refunds a paid operation captured under another adapter profile.
     */
    public function test_profile_mismatch_cannot_replay_or_refund_a_native_purchase(): void
    {
        $this->seedPurchase();
        $operation = $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
        $settings = $operation->settings_snapshot;
        $settings['game_profile'] = 'muemu_season_6';
        $operation->update(['settings_snapshot' => $settings, 'status' => CharacterOperationStatus::Pending]);

        try {
            $this->service()->purchaseLevelUpPoints('owner', 'Hero', 1, self::KEY);
            self::fail('A profile change cannot establish native replay eligibility.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::ServiceDisabled, $exception->reason);
        }

        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(2200, $this->game()->table('Character')->value('LevelUpPoint'));
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(CharacterOperationStatus::Pending, $operation->refresh()->status);
    }

    /**
     * Refuses changed native payloads even when callers reuse a completed operation identity.
     */
    public function test_native_receipt_rejects_conflicting_amount_or_owner(): void
    {
        $this->seedPurchase();
        $repository = $this->app->make(SqlServerMuDevsSeason21CharacterServiceRepository::class);
        $repository->applyPurchaseForOperation('owner', 'Hero', CharacterServiceType::LevelUpPointsPurchase, 100, null, self::KEY);

        $this->expectException(CharacterServiceRejected::class);
        $this->expectExceptionMessage(CharacterServiceBlockReason::IdempotencyConflict->value);
        $repository->applyPurchaseForOperation('owner', 'Hero', CharacterServiceType::LevelUpPointsPurchase, 200, null, self::KEY);
    }

    /**
     * Keeps invalid quantities, overflows, missing ownership and unsupported inventory policies unchanged.
     */
    #[DataProvider('invalidPurchases')]
    public function test_native_boundary_rejects_invalid_purchases(string $account, string $name, CharacterServiceType $type, int $amount, ?PurchasedResetPolicy $policy, CharacterServiceBlockReason $reason): void
    {
        $this->seedPurchase();
        $before = $this->game()->table('Character')->first();
        $repository = $this->app->make(SqlServerMuDevsSeason21CharacterServiceRepository::class);

        try {
            $repository->applyPurchaseForOperation($account, $name, $type, $amount, $policy, self::KEY);
            self::fail('Invalid progression must be rejected.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame($reason, $exception->reason);
        }

        self::assertSame((array) $before, (array) $this->game()->table('Character')->first());
        self::assertSame($type === CharacterServiceType::ClassChange ? 0 : 1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
    }

    /**
     * Enumerates native progression input boundaries without any game client dependency.
     *
     * @return iterable<string, array{string, string, CharacterServiceType, int, PurchasedResetPolicy|null, CharacterServiceBlockReason}>
     */
    public static function invalidPurchases(): iterable
    {
        $points = CharacterServiceType::LevelUpPointsPurchase;
        $resets = CharacterServiceType::ResetPurchase;
        yield 'zero quantity' => ['owner', 'Hero', $points, 0, null, CharacterServiceBlockReason::InvalidQuantity];
        yield 'point overflow' => ['owner', 'Hero', $points, 2147483647, null, CharacterServiceBlockReason::PointLimitExceeded];
        yield 'foreign character' => ['owner', 'Missing', $points, 1, null, CharacterServiceBlockReason::CharacterNotFound];
        yield 'unknown online status' => ['other', 'Hero', $points, 1, null, CharacterServiceBlockReason::CharacterOnline];
        yield 'inventory deletion stays disabled' => ['owner', 'Hero', $resets, 1, new PurchasedResetPolicy(999, false, PointsMode::Fixed, 500, InventoryPolicy::ClearAll), CharacterServiceBlockReason::ResetPolicyUnsupported];
        yield 'missing reset policy' => ['owner', 'Hero', $resets, 1, null, CharacterServiceBlockReason::ResetPolicyUnsupported];
        yield 'reset maximum' => ['owner', 'Hero', $resets, 5, new PurchasedResetPolicy(3, false, PointsMode::Fixed, 500, InventoryPolicy::KeepAll), CharacterServiceBlockReason::MaximumResetsReached];
        yield 'identity change stays disabled' => ['owner', 'Hero', CharacterServiceType::ClassChange, 1, null, CharacterServiceBlockReason::ServiceDisabled];
    }

    /**
     * Creates native account data and explicit operator prices for isolated purchase tests.
     */
    private function seedPurchase(): void
    {
        EconomySetting::factory()->create([
            'character_reset_purchase_enabled' => true, 'character_reset_price_credits' => 100,
            'character_level_up_points_purchase_enabled' => true,
            'character_level_up_points_package_size' => 1000, 'character_level_up_points_price_credits' => 100,
        ]);
        AccountWallet::factory()->create(['account_id' => 'owner', 'currency' => WalletCurrency::Credits, 'balance' => 1000]);
        $this->game()->table('MEMB_INFO')->insert(['memb_guid' => 1, 'memb___id' => 'owner', 'AccountLevel' => 0]);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42, 'Class' => 68,
            'cLevel' => 400, 'ResetCount' => 2, 'MasterResetCount' => 1,
            'LevelUpPoint' => 1200, 'Experience' => 12345, 'Money' => 500000000,
            'Strength' => 32000, 'Dexterity' => 32000, 'Vitality' => 32000, 'Energy' => 32000, 'Leadership' => 1500,
            'Inventory' => str_repeat("\xFF", 5975), 'Quest' => str_repeat("\xAB", 50),
            'MagicList' => str_repeat("\xCD", 246), 'RuudMoney' => 120,
        ]);
    }

    /**
     * Returns the real paid service action with native adapter bindings.
     */
    private function service(): ExecuteCharacterService
    {
        return $this->app->make(ExecuteCharacterService::class);
    }

    /**
     * Returns one exact authenticated account for private HTTP checks.
     */
    private function account(string $name = 'owner'): GameAccount
    {
        return new GameAccount(1, $name, 'Owner', null, false, false, '');
    }

    /**
     * Returns the isolated native game database.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
