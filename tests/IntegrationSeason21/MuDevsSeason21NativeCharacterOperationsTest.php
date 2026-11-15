<?php

namespace Tests\IntegrationSeason21;

use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\CharacterServiceResult;
use App\CharacterServices\CharacterServiceType;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\CharacterServices\PurchasedResetPolicy;
use App\Game\Contracts\MapNameLookup;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterGrandResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterMoveRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterPkClearRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterServiceRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatAllocationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatResetRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\CharacterServiceJournalMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use App\Stats\StatAllocation;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Integration\TestCase;

/**
 * Exercises native character writes and progression retention on isolated SQL Server.
 */
final class MuDevsSeason21NativeCharacterOperationsTest extends TestCase
{
    /**
     * Selects native stat and map definitions and isolates each operation in a rollback.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(GameVersionProfile::class, MuDevsSeason21Profile::class);
        $this->app->bind(MapNameLookup::class, MapNameMapperMuDevsSeason21::class);
        $this->game()->beginTransaction();
    }

    /**
     * Restores the disposable schema's rows after each independent operation.
     */
    protected function tearDown(): void
    {
        $this->game()->rollBack();

        parent::tearDown();
    }

    /**
     * Changes only the intended native fields while retaining complete advanced state.
     *
     * @param  array<string, int>  $expectedFields
     */
    #[DataProvider('nativeOperations')]
    public function test_native_character_operation_preserves_advanced_state(string $operation, array $expectedFields): void
    {
        $this->seedCharacter();
        $before = $this->advancedState();
        $this->apply($operation);
        $character = $this->game()->table('Character')->where('Name', 'Hero')->first();

        foreach ($expectedFields as $column => $expected) {
            self::assertSame($expected, (int) $character->{$column}, $column);
        }

        self::assertSame($before, $this->advancedState());
    }

    /**
     * Covers every implemented native core character operation with its observable result.
     *
     * @return iterable<string, array{string, array<string, int>}>
     */
    public static function nativeOperations(): iterable
    {
        yield 'reset' => ['reset', ['ResetCount' => 101, 'cLevel' => 1, 'Leadership' => 25]];
        yield 'paid reset' => ['paid-reset', ['ResetCount' => 105, 'cLevel' => 1, 'Leadership' => 25, 'LevelUpPoint' => 500]];
        yield 'paid points' => ['paid-points', ['LevelUpPoint' => 1100]];
        yield 'grand reset' => ['grand-reset', ['MasterResetCount' => 3, 'ResetCount' => 0, 'Leadership' => 25]];
        yield 'allocation' => ['allocate', ['Leadership' => 1510, 'LevelUpPoint' => 90]];
        yield 'stat reset' => ['stat-reset', ['Leadership' => 25, 'Strength' => 26]];
        yield 'PK clear' => ['pk-clear', ['PkLevel' => 3, 'PkCount' => 0]];
        yield 'town movement' => ['move', ['MapNumber' => 51, 'MapPosX' => 50, 'MapPosY' => 225]];
    }

    /**
     * Rolls back a rejected callback before persisting a failure that remains final on replay.
     */
    public function test_paid_rejection_receipt_preserves_the_counter_and_prevents_a_late_grant(): void
    {
        $this->seedCharacter();
        $journal = new CharacterServiceJournalMuDevsSeason21;
        $connection = $this->app->make(DatabaseManager::class)->connection('game');

        try {
            $journal->run($connection, 'sql-rejected-paid', 'owner', str_repeat('a', 64), function () use ($connection): CharacterServiceResult {
                $connection->table('Character')->where('Name', 'Hero')->update(['LevelUpPoint' => 9000]);
                throw new CharacterServiceRejected(CharacterServiceBlockReason::MaximumResetsReached);
            });
            self::fail('The native rejection must reach the caller after being recorded.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::MaximumResetsReached, $exception->reason);
        }

        self::assertSame(100, (int) $connection->table('Character')->where('Name', 'Hero')->value('LevelUpPoint'));
        self::assertSame(1, $connection->table('IWEB_S21_CharacterOperation')->where('OperationKey', 'sql-rejected-paid')->count());
        try {
            $journal->run($connection, 'sql-rejected-paid', 'owner', str_repeat('a', 64), function (): CharacterServiceResult {
                throw new RuntimeException('A rejected paid callback must never be retried.');
            });
            self::fail('A saved rejection must remain a rejection.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::MaximumResetsReached, $exception->reason);
        }
    }

    /**
     * Runs the reviewed native operation without enabling live HTTP game writes.
     */
    private function apply(string $operation): void
    {
        $settings = new ResetProfileSettings(
            enabled: true, requiredLevel: 400, maximumResets: 999, maximumStat: 65000,
            grandResetRequiredResets: 100, baseZenCost: 1000, multiplyCostByReset: false,
            clearStats: true, pointsMode: PointsMode::Fixed, pointsPerReset: 500,
            inventoryPolicy: InventoryPolicy::KeepAll, grandResetClearStats: true,
            moveToTownMap: 51, moveToTownX: 50, moveToTownY: 225,
        );

        match ($operation) {
            'reset' => $this->app->make(SqlServerMuDevsSeason21CharacterResetRepository::class)->reset('owner', 'Hero', $settings),
            'paid-reset' => $this->app->make(SqlServerMuDevsSeason21CharacterServiceRepository::class)->applyPurchaseForOperation('owner', 'Hero', CharacterServiceType::ResetPurchase, 5, new PurchasedResetPolicy(999, true, PointsMode::Fixed, 500, InventoryPolicy::KeepAll), 'sql-paid-reset'),
            'paid-points' => $this->app->make(SqlServerMuDevsSeason21CharacterServiceRepository::class)->applyPurchaseForOperation('owner', 'Hero', CharacterServiceType::LevelUpPointsPurchase, 1000, null, 'sql-paid-points'),
            'grand-reset' => $this->app->make(SqlServerMuDevsSeason21CharacterGrandResetRepository::class)->grandReset('owner', 'Hero', $settings),
            'allocate' => $this->app->make(SqlServerMuDevsSeason21CharacterStatAllocationRepository::class)->allocate('owner', 'Hero', new StatAllocation(0, 0, 0, 0, ['command' => 10]), $settings),
            'stat-reset' => $this->app->make(SqlServerMuDevsSeason21CharacterStatResetRepository::class)->resetStats('owner', 'Hero', $settings),
            'pk-clear' => $this->app->make(SqlServerMuDevsSeason21CharacterPkClearRepository::class)->clearPk('owner', 'Hero', $settings),
            'move' => $this->app->make(SqlServerMuDevsSeason21CharacterMoveRepository::class)->moveToTown('owner', 'Hero', $settings),
        };
    }

    /**
     * Seeds a fourth-form Dark Lord with native binary state and stable progression identities.
     */
    private function seedCharacter(): void
    {
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42, 'Class' => 68,
            'cLevel' => 400, 'LevelUpPoint' => 100, 'ResetCount' => 100, 'MasterResetCount' => 2,
            'EnhanceResetCount' => 3, 'Strength' => 1000, 'Dexterity' => 1000,
            'Vitality' => 1000, 'Energy' => 1000, 'Leadership' => 1500, 'Money' => 500000000,
            'Experience' => 123456, 'MapNumber' => 0, 'MapPosX' => 120, 'MapPosY' => 120,
            'PkLevel' => 6, 'PkCount' => 10, 'PkTime' => 100, 'CtlCode' => 0,
            'GiantMountType' => 1, 'GiantMountStatus' => 1,
        ]);
        $this->game()->statement(
            'UPDATE Character SET Inventory = CONVERT(varbinary(5975), ?, 2), Quest = 0x01020304, MagicList = 0x090807 WHERE Name = ?',
            [str_repeat('FF', 5975), 'Hero'],
        );
        $this->game()->table('MasterSkillTree')->insert([
            'Name' => 'Hero', 'CharacterIndex' => 42, 'MasterLevel' => 800, 'MasterPoint' => 17, 'MasterExperience' => 19000000000,
        ]);
        $this->game()->table('EnhanceSkillTree')->insert(['Name' => 'Hero', 'CharacterIndex' => 42, 'EnhancePoint' => 29]);
    }

    /**
     * Captures exact native bytes and progression rows that these services must not alter.
     *
     * @return array<string, mixed>
     */
    private function advancedState(): array
    {
        return [
            'character' => (array) $this->game()->table('Character')->where('Name', 'Hero')
                ->select(['Class', 'CharacterIndex', 'EnhanceResetCount', 'GiantMountType', 'GiantMountStatus'])
                ->selectRaw('CONVERT(varchar(max), Inventory, 2) AS item_hex, CONVERT(varchar(max), Quest, 2) AS quest_hex, CONVERT(varchar(max), MagicList, 2) AS skill_hex')->first(),
            'master' => (array) $this->game()->table('MasterSkillTree')->where('CharacterIndex', 42)->first(),
            'enhance' => (array) $this->game()->table('EnhanceSkillTree')->where('CharacterIndex', 42)->first(),
        ];
    }

    /**
     * Returns the connection protected by the disposable SQL Server base test.
     */
    private function game(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
