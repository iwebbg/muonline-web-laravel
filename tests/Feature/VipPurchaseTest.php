<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Economy\WalletTransactionType;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\CharacterVipRepository;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Repositories\SqlServer97d99iCharacterVipRepository;
use App\Models\AccountWallet;
use App\Models\VipEntitlement;
use App\Models\VipPlan;
use App\Models\VipPurchase;
use App\Models\WalletTransaction;
use App\Vip\Actions\PurchaseCharacterVip;
use App\Vip\Exceptions\VipPurchaseRejected;
use App\Vip\VipPurchaseBlockReason;
use App\Vip\VipSyncStatus;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies atomic VIP purchases, replay safety and Darksteam synchronization.
 */
final class VipPurchaseTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables the explicit game-write safety switches for mocked synchronization.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms a purchase debits Credits, records history and synchronizes once.
     */
    public function test_purchase_is_atomic_and_synchronizes_character_vip(): void
    {
        $plan = VipPlan::factory()->create([
            'duration_days' => 30,
            'price_credits' => 1000,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'vipowner',
            'currency' => WalletCurrency::Credits,
            'balance' => 1500,
        ]);
        $this->mockOwnedCharacter('vipowner', 'VipKnight');
        $this->mockAccountLookup('vipowner');
        $writer = $this->mock(CharacterVipRepository::class);
        $writer->shouldReceive('synchronize')
            ->once()
            ->withArgs(fn (string $accountId, string $characterName): bool => $accountId === 'vipowner'
                && $characterName === 'VipKnight');

        $purchase = $this->purchase(
            accountId: 'vipowner',
            characterName: 'VipKnight',
            planId: (int) $plan->getKey(),
            idempotencyKey: '593a4b05-c846-43f5-9f37-1910f59b1306',
        );

        $this->assertSame(500, AccountWallet::query()->sole()->balance);
        $this->assertSame(-1000, WalletTransaction::query()->sole()->amount);
        $this->assertSame(WalletTransactionType::Purchase, WalletTransaction::query()->sole()->type);
        $this->assertSame('VipKnight', $purchase->character_name);
        $this->assertSame(1, VipPurchase::query()->count());
        $this->assertSame(VipSyncStatus::Synced, VipEntitlement::query()->sole()->sync_status);
    }

    /**
     * Confirms an identical replay never charges or extends VIP twice.
     */
    public function test_identical_replay_returns_original_purchase_without_second_charge(): void
    {
        $plan = VipPlan::factory()->create(['price_credits' => 500]);
        AccountWallet::factory()->create([
            'account_id' => 'vipreplay',
            'balance' => 1000,
        ]);
        $this->mockOwnedCharacter('vipreplay', 'ReplayVip');
        $this->mockAccountLookup('vipreplay');
        $writer = $this->mock(CharacterVipRepository::class);
        $writer->shouldReceive('synchronize')->once();
        $arguments = [
            'accountId' => 'vipreplay',
            'characterName' => 'ReplayVip',
            'planId' => (int) $plan->getKey(),
            'idempotencyKey' => '6284d9f4-9ead-48a2-a2d6-6be09ec5ca42',
        ];

        $first = $this->purchase(...$arguments);
        $replayed = $this->purchase(...$arguments);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(500, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(1, VipPurchase::query()->count());
    }

    /**
     * Confirms active time is extended from its expiry rather than from today.
     */
    public function test_active_entitlement_preserves_remaining_time_when_extended(): void
    {
        $this->travelTo(now()->startOfMinute());
        $plan = VipPlan::factory()->create([
            'duration_days' => 7,
            'price_credits' => 100,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'vipextend',
            'balance' => 500,
        ]);
        $existingExpiry = now()->addDays(5);
        $expectedExpiry = $existingExpiry->copy()->addDays(7);
        VipEntitlement::factory()->create([
            'account_id' => 'vipextend',
            'character_name' => 'ExtendVip',
            'vip_plan_id' => $plan->getKey(),
            'expires_at' => $existingExpiry,
        ]);
        $this->mockOwnedCharacter('vipextend', 'ExtendVip');
        $this->mockAccountLookup('vipextend');
        $this->mock(CharacterVipRepository::class)
            ->shouldReceive('synchronize')
            ->once();

        $purchase = $this->purchase(
            accountId: 'vipextend',
            characterName: 'ExtendVip',
            planId: (int) $plan->getKey(),
            idempotencyKey: '1816cd18-cda4-4bc5-adf5-381794ad5b88',
        );

        $this->assertTrue($purchase->expires_at->equalTo($expectedExpiry));
        $this->assertTrue($purchase->previous_expires_at?->equalTo($existingExpiry));
    }

    /**
     * Confirms insufficient Credits roll back entitlement and purchase creation.
     */
    public function test_insufficient_credits_leave_all_vip_state_unchanged(): void
    {
        $plan = VipPlan::factory()->create(['price_credits' => 1000]);
        AccountWallet::factory()->create([
            'account_id' => 'vippad',
            'balance' => 100,
        ]);
        $this->mockOwnedCharacter('vippad', 'PoorVip');
        $this->mockAccountLookup('vippad');
        $this->mock(CharacterVipRepository::class)
            ->shouldNotReceive('synchronize');

        try {
            $this->purchase(
                accountId: 'vippad',
                characterName: 'PoorVip',
                planId: (int) $plan->getKey(),
                idempotencyKey: '7644b693-419c-47f1-959c-fcdd12cd40f0',
            );
            $this->fail('A VIP purchase without enough Credits must be rejected.');
        } catch (VipPurchaseRejected $exception) {
            $this->assertSame(VipPurchaseBlockReason::InsufficientCredits, $exception->reason);
        }

        $this->assertSame(100, AccountWallet::query()->sole()->balance);
        $this->assertSame(0, WalletTransaction::query()->count());
        $this->assertSame(0, VipEntitlement::query()->count());
        $this->assertSame(0, VipPurchase::query()->count());
    }

    /**
     * Confirms a game synchronization outage preserves a retryable paid entitlement.
     */
    public function test_sync_failure_preserves_purchase_for_automatic_retry(): void
    {
        $plan = VipPlan::factory()->create(['price_credits' => 100]);
        AccountWallet::factory()->create([
            'account_id' => 'vipretry',
            'balance' => 500,
        ]);
        $this->mockOwnedCharacter('vipretry', 'RetryVip');
        $this->mockAccountLookup('vipretry');
        $this->mock(CharacterVipRepository::class)
            ->shouldReceive('synchronize')
            ->once()
            ->andThrow(new RuntimeException('Sensitive infrastructure detail'));

        $this->purchase(
            accountId: 'vipretry',
            characterName: 'RetryVip',
            planId: (int) $plan->getKey(),
            idempotencyKey: '522f5aaa-c817-4784-a61f-3eaf34f97992',
        );

        $entitlement = VipEntitlement::query()->sole();
        $this->assertSame(VipSyncStatus::Failed, $entitlement->sync_status);
        $this->assertSame('game_sync_failed', $entitlement->last_sync_error);
        $this->assertSame(1, VipPurchase::query()->count());
        $this->assertSame(400, AccountWallet::query()->sole()->balance);
    }

    /**
     * Confirms the scheduled command retries and completes a failed projection.
     */
    public function test_sync_command_retries_failed_entitlement(): void
    {
        $entitlement = VipEntitlement::factory()->create([
            'account_id' => 'vipcommand',
            'character_name' => 'CommandVip',
            'sync_status' => VipSyncStatus::Failed,
            'game_synced_expires_at' => null,
            'last_sync_error' => 'game_sync_failed',
        ]);
        $this->mock(CharacterVipRepository::class)
            ->shouldReceive('synchronize')
            ->once()
            ->withArgs(fn (string $accountId, string $characterName): bool => $accountId === 'vipcommand'
                && $characterName === 'CommandVip');

        $this->artisan('vip:sync-pending')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $entitlement->refresh();
        $this->assertSame(VipSyncStatus::Synced, $entitlement->sync_status);
        $this->assertNull($entitlement->last_sync_error);
    }

    /**
     * Confirms the scheduled command clears expired Darksteam VIP state once.
     */
    public function test_sync_command_projects_expiration_and_stops_retrying_it(): void
    {
        $entitlement = VipEntitlement::factory()->create([
            'account_id' => 'vipexpired',
            'character_name' => 'ExpiredVip',
            'starts_at' => now()->subDays(8),
            'expires_at' => now()->subDay(),
            'sync_status' => VipSyncStatus::Synced,
            'game_synced_expires_at' => now()->subDay(),
        ]);
        $writer = $this->mock(CharacterVipRepository::class);
        $writer->shouldReceive('synchronize')->once();

        $this->artisan('vip:sync-pending')
            ->expectsOutputToContain('1 synced, 0 pending retry')
            ->assertSuccessful();

        $entitlement->refresh();
        $this->assertSame(VipSyncStatus::Expired, $entitlement->sync_status);
        $this->assertNull($entitlement->game_synced_expires_at);

        $this->artisan('vip:sync-pending')
            ->expectsOutputToContain('0 synced, 0 pending retry')
            ->assertSuccessful();
    }

    /**
     * Confirms the HTTP endpoint uses only the authenticated account identifier.
     */
    public function test_purchase_endpoint_scopes_character_to_authenticated_account(): void
    {
        $plan = VipPlan::factory()->create(['price_credits' => 100]);
        AccountWallet::factory()->create([
            'account_id' => 'webowner',
            'balance' => 500,
        ]);
        $reader = $this->mock(AccountReadRepository::class);
        $reader->shouldReceive('characterFor')
            ->once()
            ->with('webowner', 'WebKnight')
            ->andReturn($this->character('WebKnight'));
        $this->mockAccountLookup('webowner');
        $this->mock(CharacterVipRepository::class)
            ->shouldReceive('synchronize')
            ->once();
        $csrfToken = 'vip-purchase-endpoint-token';

        $response = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($this->account('webowner'))
            ->post('/account/vip-purchases', [
                '_token' => $csrfToken,
                'character_name' => 'WebKnight',
                'vip_plan_id' => $plan->getKey(),
                'idempotency_key' => 'e881e871-a501-480f-99d0-4d135ffea94f',
            ]);

        $response
            ->assertRedirect()
            ->assertSessionHasNoErrors()
            ->assertSessionHas('accountStatus');
        $this->assertDatabaseHas('vip_purchases', [
            'account_id' => 'webowner',
            'character_name' => 'WebKnight',
        ]);
    }

    /**
     * Confirms the 97d+99i adapter updates only an offline owned character.
     */
    public function test_sql_server_adapter_updates_verified_darksteam_vip_columns(): void
    {
        $database = $this->configurePortableGameSchema(connectStat: 0);
        $repository = new SqlServer97d99iCharacterVipRepository($database);
        $expiresAt = now()->addDays(30);

        $repository->synchronize('adapter1', 'AdapterVip', $expiresAt);

        $character = $database->connection('game')
            ->table('Character')
            ->where('Name', 'AdapterVip')
            ->first();
        $this->assertSame(1, (int) $character->IsVip);
        $this->assertSame($expiresAt->getTimestamp(), (int) $character->VipExpirationTime);
    }

    /**
     * Confirms the 97d+99i adapter refuses an online account without changing VIP.
     */
    public function test_sql_server_adapter_rejects_online_account(): void
    {
        $database = $this->configurePortableGameSchema(connectStat: 1);
        $repository = new SqlServer97d99iCharacterVipRepository($database);

        try {
            $repository->synchronize('adapter1', 'AdapterVip', now()->addDays(30));
            $this->fail('An online account must not receive a VIP database write.');
        } catch (VipPurchaseRejected $exception) {
            $this->assertSame(VipPurchaseBlockReason::CharacterOnline, $exception->reason);
        }

        $this->assertDatabaseHas('Character', [
            'Name' => 'AdapterVip',
            'IsVip' => 0,
        ], 'game');
    }

    /**
     * Confirms the 97d+99i adapter clears both columns after entitlement expiry.
     */
    public function test_sql_server_adapter_clears_expired_darksteam_vip_state(): void
    {
        $database = $this->configurePortableGameSchema(connectStat: 0);
        $connection = $database->connection('game');
        $connection->table('Character')->update([
            'IsVip' => 1,
            'VipExpirationTime' => now()->subDay()->getTimestamp(),
        ]);
        $repository = new SqlServer97d99iCharacterVipRepository($database);

        $repository->synchronize('adapter1', 'AdapterVip', now()->subDay());

        $character = $connection
            ->table('Character')
            ->where('Name', 'AdapterVip')
            ->first();
        $this->assertSame(0, (int) $character->IsVip);
        $this->assertSame(0, (int) $character->VipExpirationTime);
    }

    /**
     * Resolves and executes the production VIP purchase action.
     */
    private function purchase(
        string $accountId,
        string $characterName,
        int $planId,
        string $idempotencyKey,
    ): VipPurchase {
        return $this->app->make(PurchaseCharacterVip::class)->execute(
            accountId: $accountId,
            characterName: $characterName,
            planId: $planId,
            idempotencyKey: $idempotencyKey,
        );
    }

    /**
     * Configures ownership lookup for one offline character.
     */
    private function mockOwnedCharacter(string $accountId, string $characterName): void
    {
        $reader = $this->mock(AccountReadRepository::class);
        $reader->shouldReceive('characterFor')
            ->once()
            ->with($accountId, $characterName)
            ->andReturn($this->character($characterName));
    }

    /**
     * Configures the wallet account existence boundary for one account.
     */
    private function mockAccountLookup(string $accountId): void
    {
        $lookup = $this->mock(GameAccountLookupRepository::class);
        $lookup->shouldReceive('exists')
            ->once()
            ->with($accountId)
            ->andReturnTrue();
    }

    /**
     * Builds one offline character DTO owned by the mocked account reader.
     */
    private function character(string $name): AccountCharacter
    {
        return new AccountCharacter(
            name: $name,
            className: 'Blade Knight',
            classCode: 17,
            image: '/images/characters/blade-knight.webp',
            level: 400,
            resets: 20,
            grandResets: 2,
            zen: 100_000_000,
            points: 100,
            strength: 1000,
            agility: 800,
            vitality: 700,
            energy: 200,
            map: 'Lorencia',
            online: false,
        );
    }

    /**
     * Builds one authenticated game account identity for endpoint verification.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'VIP Test',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Creates portable Darksteam tables for direct adapter integration checks.
     */
    private function configurePortableGameSchema(int $connectStat): DatabaseManager
    {
        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
            'readonly' => false,
        ]);

        $database = $this->app->make(DatabaseManager::class);
        $database->purge('game');
        $connection = $database->connection('game');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, IsVip INTEGER NOT NULL, VipExpirationTime INTEGER NOT NULL)');
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => 'adapter1',
            'ConnectStat' => $connectStat,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => 'adapter1',
            'Name' => 'AdapterVip',
            'IsVip' => 0,
            'VipExpirationTime' => 0,
        ]);

        return $database;
    }
}
