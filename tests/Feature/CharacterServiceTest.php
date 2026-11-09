<?php

namespace Tests\Feature;

use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Economy\WalletCurrency;
use App\Game\Contracts\AccountReadRepository;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Game\Data\AccountCharacter;
use App\Models\AccountWallet;
use App\Models\CharacterServiceOperation;
use App\Models\EconomySetting;
use App\Models\WalletTransaction;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies paid character services, core references, refunds and replay safety.
 */
final class CharacterServiceTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates the isolated shared legacy schema and opens explicit write switches.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', true);
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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Class INTEGER NOT NULL, MarryName TEXT NULL, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Experience INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, Resets INTEGER NULL, GrandResets INTEGER NULL)');
        $connection->statement('CREATE TABLE AccountCharacter (Id TEXT PRIMARY KEY, GameID1 TEXT NULL, GameID2 TEXT NULL, GameID3 TEXT NULL, GameID4 TEXT NULL, GameID5 TEXT NULL, GameIDC TEXT NULL)');
        $connection->statement('CREATE TABLE Guild (G_Name TEXT PRIMARY KEY, G_Master TEXT NULL)');
        $connection->statement('CREATE TABLE GuildMember (Name TEXT PRIMARY KEY, G_Name TEXT NOT NULL)');
        $connection->statement('CREATE TABLE OptionData (Name TEXT PRIMARY KEY)');

        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturnTrue();
        });
    }

    /**
     * Confirms rename charges once and updates every verified core name reference.
     */
    public function test_offline_character_can_be_renamed_once_with_all_core_references(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 1000);
        $this->insertCharacter('owner', 'OldHero', 17);
        $connection = $this->game();
        $connection->table('Character')->insert([
            'AccountID' => 'spouse',
            'Name' => 'Partner',
            'Class' => 32,
            'MarryName' => 'OldHero',
        ]);
        $connection->table('AccountCharacter')->insert([
            'Id' => 'owner',
            'GameID1' => 'OldHero',
            'GameIDC' => 'OldHero',
        ]);
        $connection->table('Guild')->insert(['G_Name' => 'Guild', 'G_Master' => 'OldHero']);
        $connection->table('GuildMember')->insert(['Name' => 'OldHero', 'G_Name' => 'Guild']);
        $connection->table('OptionData')->insert(['Name' => 'OldHero']);
        $this->mockCharacter('owner', 'OldHero', 17);
        $arguments = [
            'accountId' => 'owner',
            'characterName' => 'OldHero',
            'newName' => 'NewHero',
            'idempotencyKey' => '0e34cd7f-f471-46bb-b20f-953f972c6681',
        ];

        $first = $this->service()->rename(...$arguments);
        $replayed = $this->service()->rename(...$arguments);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(750, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(1, CharacterServiceOperation::query()->count());
        $this->assertTrue($connection->table('Character')->where('Name', 'NewHero')->exists());
        $this->assertSame('NewHero', $connection->table('Character')->where('Name', 'Partner')->value('MarryName'));
        $this->assertSame('NewHero', $connection->table('AccountCharacter')->where('Id', 'owner')->value('GameID1'));
        $this->assertSame('NewHero', $connection->table('AccountCharacter')->where('Id', 'owner')->value('GameIDC'));
        $this->assertSame('NewHero', $connection->table('Guild')->where('G_Name', 'Guild')->value('G_Master'));
        $this->assertTrue($connection->table('GuildMember')->where('Name', 'NewHero')->exists());
        $this->assertTrue($connection->table('OptionData')->where('Name', 'NewHero')->exists());
    }

    /**
     * Confirms a tier-two character changes to the target family's tier two class.
     */
    public function test_class_change_preserves_supported_evolution_tier(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 1000);
        $this->insertCharacter('owner', 'BladeHero', 17);
        $this->mockCharacter('owner', 'BladeHero', 17);

        $operation = $this->service()->changeClass(
            accountId: 'owner',
            characterName: 'BladeHero',
            targetFamily: 'elf',
            idempotencyKey: 'cae9021d-67d7-4857-8e1e-c79658c5fdaf',
        );

        $this->assertSame(33, $this->game()->table('Character')->where('Name', 'BladeHero')->value('Class'));
        $this->assertSame(600, AccountWallet::query()->sole()->balance);
        $this->assertSame(17, $operation->before_snapshot['class_code']);
        $this->assertSame(33, $operation->after_snapshot['class_code']);
    }

    /**
     * Confirms a reset package charges once and applies the captured reset outcome without rewards.
     */
    public function test_reset_package_applies_profile_outcome_once_without_normal_reward(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'ResetHero', 17, resets: 2, points: 1_200);
        $this->mockCharacter('owner', 'ResetHero', 17, resets: 2, points: 1_200);
        $arguments = [
            'accountId' => 'owner',
            'characterName' => 'ResetHero',
            'resetCount' => 5,
            'idempotencyKey' => 'e4ef651f-b871-4142-ac29-c0f0cb1ba21c',
        ];

        $first = $this->service()->purchaseResets(...$arguments);
        $replayed = $this->service()->purchaseResets(...$arguments);

        $character = $this->game()->table('Character')->where('Name', 'ResetHero')->first();
        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(1_500, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(5, $first->requested_amount);
        $this->assertSame(1, (int) $character->cLevel);
        $this->assertSame(7, (int) $character->Resets);
        $this->assertSame(3_500, (int) $character->LevelUpPoint);
        $this->assertSame(28, (int) $character->Strength);
        $this->assertSame(20, (int) $character->Dexterity);
        $this->assertSame(25, (int) $character->Vitality);
        $this->assertSame(10, (int) $character->Energy);
        $this->assertDatabaseCount('character_reset_operations', 0);
    }

    /**
     * Confirms a points package adds unallocated points and charges its exact package price.
     */
    public function test_level_up_points_package_adds_unallocated_points_once(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'PointHero', 17, points: 1_200);
        $this->mockCharacter('owner', 'PointHero', 17, points: 1_200);
        $arguments = [
            'accountId' => 'owner',
            'characterName' => 'PointHero',
            'packageCount' => 5,
            'idempotencyKey' => '769fb0d4-cd20-4f68-b5b6-f4d29aa569dd',
        ];

        $first = $this->service()->purchaseLevelUpPoints(...$arguments);
        $replayed = $this->service()->purchaseLevelUpPoints(...$arguments);

        $this->assertSame($first->getKey(), $replayed->getKey());
        $this->assertSame(1_500, AccountWallet::query()->sole()->balance);
        $this->assertSame(1, WalletTransaction::query()->count());
        $this->assertSame(5_000, $first->requested_amount);
        $this->assertSame(
            6_200,
            (int) $this->game()->table('Character')->where('Name', 'PointHero')->value('LevelUpPoint'),
        );
    }

    /**
     * Confirms a reset package cannot cross the active reset maximum or charge Credits.
     */
    public function test_reset_package_above_maximum_is_rejected_without_charge(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'MaxHero', 17, resets: 998);
        $this->mockCharacter('owner', 'MaxHero', 17, resets: 998);

        try {
            $this->service()->purchaseResets(
                accountId: 'owner',
                characterName: 'MaxHero',
                resetCount: 5,
                idempotencyKey: '552b48a2-1d49-4f21-a535-448e980f40c2',
            );
            $this->fail('A reset package must not exceed the configured maximum.');
        } catch (CharacterServiceRejected $exception) {
            $this->assertSame('maximum_resets_reached', $exception->reason->value);
        }

        $this->assertSame(2_000, AccountWallet::query()->sole()->balance);
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('character_service_operations', 0);
    }

    /**
     * Confirms the final offline check refunds a charged points package.
     */
    public function test_online_race_refunds_level_up_points_package(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'OnlinePoints', 17, connectStatus: 1, points: 1_200);
        $this->mockCharacter('owner', 'OnlinePoints', 17, points: 1_200);

        try {
            $this->service()->purchaseLevelUpPoints(
                accountId: 'owner',
                characterName: 'OnlinePoints',
                packageCount: 1,
                idempotencyKey: '35bb1158-ed73-4b58-8d50-fe16fc0bf3c5',
            );
            $this->fail('An online character must not receive purchased points.');
        } catch (CharacterServiceRejected $exception) {
            $this->assertSame('character_online', $exception->reason->value);
        }

        $this->assertSame(2_000, AccountWallet::query()->sole()->balance);
        $this->assertSame([-100, 100], WalletTransaction::query()->orderBy('id')->pluck('amount')->all());
        $this->assertSame(
            1_200,
            (int) $this->game()->table('Character')->where('Name', 'OnlinePoints')->value('LevelUpPoint'),
        );
    }

    /**
     * Confirms the final transactional offline check returns every charged Credit.
     */
    public function test_online_race_refunds_the_full_service_price(): void
    {
        $this->enableServices(renamePrice: 250, classChangePrice: 400);
        $this->wallet('owner', 1000);
        $this->insertCharacter('owner', 'OnlineHero', 17, connectStatus: 1);
        $this->mockCharacter('owner', 'OnlineHero', 17);

        try {
            $this->service()->rename(
                accountId: 'owner',
                characterName: 'OnlineHero',
                newName: 'SafeName',
                idempotencyKey: 'f28ce705-78d5-45b5-8dda-afc956c21496',
            );
            $this->fail('An online character must not be renamed.');
        } catch (CharacterServiceRejected $exception) {
            $this->assertSame('character_online', $exception->reason->value);
        }

        $this->assertSame(1000, AccountWallet::query()->sole()->balance);
        $this->assertSame([-250, 250], WalletTransaction::query()->orderBy('id')->pluck('amount')->all());
        $this->assertTrue($this->game()->table('Character')->where('Name', 'OnlineHero')->exists());
    }

    /**
     * Returns the fully resolved application action.
     */
    private function service(): ExecuteCharacterService
    {
        return $this->app->make(ExecuteCharacterService::class);
    }

    /**
     * Returns the isolated game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Persists active paid-service settings.
     */
    private function enableServices(int $renamePrice, int $classChangePrice): void
    {
        EconomySetting::factory()->create([
            'character_rename_enabled' => true,
            'character_rename_price_credits' => $renamePrice,
            'character_class_change_enabled' => true,
            'character_class_change_price_credits' => $classChangePrice,
            'character_reset_purchase_enabled' => true,
            'character_reset_price_credits' => 100,
            'character_level_up_points_purchase_enabled' => true,
            'character_level_up_points_package_size' => 1_000,
            'character_level_up_points_price_credits' => 100,
        ]);
    }

    /**
     * Creates one Credits wallet.
     */
    private function wallet(string $accountId, int $balance): void
    {
        AccountWallet::factory()->create([
            'account_id' => $accountId,
            'currency' => WalletCurrency::Credits,
            'balance' => $balance,
        ]);
    }

    /**
     * Inserts one game character and connectivity record.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $classCode,
        int $connectStatus = 0,
        int $resets = 0,
        int $points = 0,
    ): void {
        $this->game()->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => $connectStatus,
        ]);
        $this->game()->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'Class' => $classCode,
            'MarryName' => null,
            'cLevel' => 400,
            'LevelUpPoint' => $points,
            'Experience' => 1_000,
            'Strength' => 100,
            'Dexterity' => 100,
            'Vitality' => 100,
            'Energy' => 100,
            'Money' => 5_000_000,
            'Resets' => $resets,
            'GrandResets' => 0,
        ]);
    }

    /**
     * Mocks the ownership-scoped account reader for one character.
     */
    private function mockCharacter(
        string $accountId,
        string $characterName,
        int $classCode,
        int $resets = 0,
        int $points = 0,
    ): void {
        $character = new AccountCharacter(
            name: $characterName,
            className: 'Fixture',
            classCode: $classCode,
            image: '',
            level: 400,
            resets: $resets,
            grandResets: 0,
            zen: 0,
            points: $points,
            strength: 0,
            agility: 0,
            vitality: 0,
            energy: 0,
            map: 'Lorencia',
            online: false,
        );

        $this->mock(AccountReadRepository::class, function (MockInterface $mock) use ($accountId, $characterName, $character): void {
            $mock->shouldReceive('characterFor')
                ->with($accountId, $characterName)
                ->andReturn($character);
        });
    }
}
