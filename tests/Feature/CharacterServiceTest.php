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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Class INTEGER NOT NULL, MarryName TEXT NULL)');
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
        ]);
    }

    /**
     * Mocks the ownership-scoped account reader for one character.
     */
    private function mockCharacter(string $accountId, string $characterName, int $classCode): void
    {
        $character = new AccountCharacter(
            name: $characterName,
            className: 'Fixture',
            classCode: $classCode,
            image: '',
            level: 400,
            resets: 0,
            grandResets: 0,
            zen: 0,
            points: 0,
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
