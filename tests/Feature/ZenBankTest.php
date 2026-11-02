<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\ZenBankRepository;
use App\Game\Data\AccountCharacter;
use App\Game\Repositories\SqlServer97d99iZenBankRepository;
use App\Models\AccountZenBalance;
use App\Models\ZenBankOperation;
use App\ZenBank\Actions\CreateZenBankOperation;
use App\ZenBank\ZenBankOperationStatus;
use App\ZenBank\ZenBankOperationType;
use App\ZenBank\ZenProjectionResult;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies atomic, replay-safe and recoverable Zen Bank transfers.
 */
final class ZenBankTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables mocked game writes for every transfer scenario.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Confirms a deposit debits game Zen before crediting the web ledger.
     */
    public function test_deposit_completes_exact_game_and_bank_transition(): void
    {
        $this->mockOfflineCharacter('owner01');
        $repository = $this->mock(ZenBankRepository::class);
        $repository->shouldReceive('currentBalance')->once()->with('owner01')->andReturn(10_000_000);
        $repository->shouldReceive('project')
            ->once()
            ->with('owner01', 10_000_000, 7_000_000)
            ->andReturn(ZenProjectionResult::Applied);

        $operation = $this->createOperation(
            accountId: 'owner01',
            type: ZenBankOperationType::Deposit,
            amount: 3_000_000,
            key: '77cf9b89-bf4b-4207-b4d9-38e1da912761',
        );

        $this->assertSame(ZenBankOperationStatus::Completed, $operation->status);
        $this->assertSame(3_000_000, AccountZenBalance::query()->value('balance'));
        $this->assertSame(7_000_000, $operation->after_game_zen);
    }

    /**
     * Confirms an identical deposit replay cannot debit game Zen twice.
     */
    public function test_deposit_replay_returns_original_completed_operation(): void
    {
        $this->mockOfflineCharacter('owner02');
        $repository = $this->mock(ZenBankRepository::class);
        $repository->shouldReceive('currentBalance')->once()->andReturn(10_000_000);
        $repository->shouldReceive('project')->once()->andReturn(ZenProjectionResult::Applied);
        $key = 'c907fc5f-c42e-4271-9385-f59595f8a546';

        $first = $this->createOperation('owner02', ZenBankOperationType::Deposit, 2_000_000, $key);
        $replayed = $this->createOperation('owner02', ZenBankOperationType::Deposit, 2_000_000, $key);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(2_000_000, AccountZenBalance::query()->value('balance'));
        $this->assertSame(1, ZenBankOperation::query()->count());
    }

    /**
     * Confirms a withdrawal reserves bank funds before crediting the warehouse.
     */
    public function test_withdrawal_reserves_bank_balance_and_completes(): void
    {
        AccountZenBalance::factory()->create([
            'account_id' => 'owner03',
            'balance' => 5_000_000,
        ]);
        $this->mockOfflineCharacter('owner03');
        $repository = $this->mock(ZenBankRepository::class);
        $repository->shouldReceive('currentBalance')->once()->andReturn(10_000_000);
        $repository->shouldReceive('project')
            ->once()
            ->with('owner03', 10_000_000, 13_000_000)
            ->andReturn(ZenProjectionResult::Applied);

        $operation = $this->createOperation(
            'owner03',
            ZenBankOperationType::Withdrawal,
            3_000_000,
            '5f420292-ad90-40d8-9292-43e770f45b7d',
        );

        $this->assertSame(ZenBankOperationStatus::Completed, $operation->status);
        $this->assertSame(2_000_000, AccountZenBalance::query()->value('balance'));
    }

    /**
     * Confirms an interrupted withdrawal remains reserved and completes on retry.
     */
    public function test_interrupted_withdrawal_is_recovered_by_scheduler(): void
    {
        AccountZenBalance::factory()->create([
            'account_id' => 'owner04',
            'balance' => 5_000_000,
        ]);
        $this->mockOfflineCharacter('owner04');
        $repository = $this->mock(ZenBankRepository::class);
        $repository->shouldReceive('currentBalance')->once()->andReturn(10_000_000);
        $repository->shouldReceive('project')
            ->once()
            ->andThrow(new RuntimeException('Private connection detail'));
        $operation = $this->createOperation(
            'owner04',
            ZenBankOperationType::Withdrawal,
            3_000_000,
            '7f407a13-91f7-48b5-bfb9-7924ef05a78f',
        );

        $this->assertSame(ZenBankOperationStatus::Pending, $operation->status);
        $this->assertSame(2_000_000, AccountZenBalance::query()->value('balance'));
        $this->assertSame('game_sync_failed', $operation->last_sync_error);

        $repository->shouldReceive('project')
            ->once()
            ->andReturn(ZenProjectionResult::AlreadyApplied);
        $this->artisan('zen-bank:sync')
            ->expectsOutputToContain('1 synced, 0 pending or conflicted')
            ->assertSuccessful();

        $this->assertSame(ZenBankOperationStatus::Completed, $operation->refresh()->status);
        $this->assertSame(2_000_000, AccountZenBalance::query()->value('balance'));
    }

    /**
     * Confirms a game balance conflict releases reserved withdrawal funds.
     */
    public function test_withdrawal_conflict_refunds_reserved_bank_balance(): void
    {
        AccountZenBalance::factory()->create([
            'account_id' => 'owner05',
            'balance' => 5_000_000,
        ]);
        $this->mockOfflineCharacter('owner05');
        $repository = $this->mock(ZenBankRepository::class);
        $repository->shouldReceive('currentBalance')->once()->andReturn(10_000_000);
        $repository->shouldReceive('project')->once()->andReturn(ZenProjectionResult::Conflict);

        $operation = $this->createOperation(
            'owner05',
            ZenBankOperationType::Withdrawal,
            3_000_000,
            'b95bc012-14d9-4dc0-8945-b03e33e48ab3',
        );

        $this->assertSame(ZenBankOperationStatus::Conflict, $operation->status);
        $this->assertSame(5_000_000, AccountZenBalance::query()->value('balance'));
        $this->assertSame('game_balance_conflict', $operation->last_sync_error);
    }

    /**
     * Confirms the production adapter applies and recognizes an exact transition.
     */
    public function test_adapter_compare_and_set_is_idempotent(): void
    {
        $database = $this->portableGameDatabase();
        $repository = new SqlServer97d99iZenBankRepository($database);

        $applied = $repository->project('owner06', 10_000_000, 7_000_000);
        $replayed = $repository->project('owner06', 10_000_000, 7_000_000);

        $this->assertSame(ZenProjectionResult::Applied, $applied);
        $this->assertSame(ZenProjectionResult::AlreadyApplied, $replayed);
        $this->assertSame(7_000_000, $repository->currentBalance('owner06'));
    }

    /**
     * Confirms the authenticated page renders its own balances and transfer history.
     */
    public function test_page_displays_account_scoped_balance_and_history(): void
    {
        $balance = AccountZenBalance::factory()->create([
            'account_id' => 'pagezen',
            'balance' => 4_000_000,
        ]);
        ZenBankOperation::factory()->create([
            'account_zen_balance_id' => $balance->getKey(),
            'account_id' => 'pagezen',
            'amount' => 2_000_000,
        ]);
        ZenBankOperation::factory()->create(['account_id' => 'hiddenzen']);
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('charactersFor')
            ->once()
            ->with('pagezen')
            ->andReturn([$this->character()]);
        $this->mock(ZenBankRepository::class)
            ->shouldReceive('currentBalance')
            ->once()
            ->with('pagezen')
            ->andReturn(12_000_000);

        $response = $this
            ->actingAs($this->account('pagezen'))
            ->get(route('account.zen-bank.index'));

        $response
            ->assertOk()
            ->assertSee('4,000,000')
            ->assertSee('12,000,000')
            ->assertSee('2,000,000')
            ->assertDontSee('hiddenzen');
    }

    /**
     * Executes the production operation action with one offline owned character.
     */
    private function createOperation(
        string $accountId,
        ZenBankOperationType $type,
        int $amount,
        string $key,
    ): ZenBankOperation {
        return $this->app->make(CreateZenBankOperation::class)->execute(
            accountId: $accountId,
            characterName: 'ZenChar',
            type: $type,
            amount: $amount,
            idempotencyKey: $key,
        );
    }

    /**
     * Configures ownership lookup for one offline character.
     */
    private function mockOfflineCharacter(string $accountId): void
    {
        $this->mock(AccountReadRepository::class)
            ->shouldReceive('characterFor')
            ->once()
            ->with($accountId, 'ZenChar')
            ->andReturn($this->character());
    }

    /**
     * Builds one offline character DTO for ownership checks.
     */
    private function character(): AccountCharacter
    {
        return new AccountCharacter(
            name: 'ZenChar',
            className: 'Blade Knight',
            classCode: 17,
            image: '/images/characters/blade-knight.webp',
            level: 400,
            resets: 20,
            grandResets: 2,
            zen: 0,
            points: 0,
            strength: 100,
            agility: 100,
            vitality: 100,
            energy: 100,
            map: 'Lorencia',
            online: false,
        );
    }

    /**
     * Creates portable status and warehouse tables for adapter verification.
     */
    private function portableGameDatabase(): DatabaseManager
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
        $connection->statement('CREATE TABLE warehouse (AccountID TEXT PRIMARY KEY, Money INTEGER NOT NULL)');
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner06', 'ConnectStat' => 0]);
        $connection->table('warehouse')->insert(['AccountID' => 'owner06', 'Money' => 10_000_000]);

        return $database;
    }

    /**
     * Creates one authenticated legacy account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: $username,
            email: null,
            blocked: false,
            vip: false,
            password: 'secret',
        );
    }
}
