<?php

namespace Tests\Feature;

use App\Game\Contracts\MapNameLookup;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterGrandResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterMoveRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterPkClearRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterResetRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatAllocationRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21CharacterStatResetRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use App\Reset\ResetProfileSettings;
use App\Stats\StatAllocation;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;
use Throwable;

/**
 * Exercises native S21 mutations without connecting to the owner's game server.
 */
final class MuDevsSeason21CharacterOperationsTest extends TestCase
{
    /**
     * Builds a native-shaped disposable schema and selects S21 stat and map metadata.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->singleton(GameVersionProfile::class, MuDevsSeason21Profile::class);
        $this->app->bind(MapNameLookup::class, MapNameMapperMuDevsSeason21::class);
        $connection = $this->game();
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Resets a fifth-form Crusader while preserving all unrelated native progression.
     */
    public function test_reset_preserves_fifth_class_quests_inventory_and_master_progression(): void
    {
        $this->seedCharacter();
        $before = $this->progressionSnapshot();

        $result = $this->app->make(SqlServerMuDevsSeason21CharacterResetRepository::class)
            ->reset('owner', 'Hero', $this->settings());

        self::assertSame(101, $result->afterSnapshot['resets']);
        self::assertSame(1, $this->game()->table('Character')->value('cLevel'));
        self::assertSame(26, $this->game()->table('Character')->value('Strength'));
        self::assertSame(244, $this->game()->table('Character')->value('Class'));
        self::assertSame($before, $this->progressionSnapshot());
    }

    /**
     * Includes the fifth Dark Lord stat in grand-reset writes and audit snapshots.
     */
    public function test_grand_reset_clears_command_for_force_empire_and_preserves_advanced_state(): void
    {
        $this->seedCharacter(['Class' => 68]);
        $before = $this->progressionSnapshot();

        $result = $this->app->make(SqlServerMuDevsSeason21CharacterGrandResetRepository::class)
            ->grandReset('owner', 'Hero', $this->settings(['grandResetClearStats' => true]));

        self::assertSame(1500, $result->beforeStats->toArray()['command']);
        self::assertSame(25, $result->afterStats->toArray()['command']);
        self::assertSame(25, $this->game()->table('Character')->value('Leadership'));
        self::assertSame(3, $this->game()->table('Character')->value('MasterResetCount'));
        self::assertSame($before, $this->progressionSnapshot());
    }

    /**
     * Allocates profile-defined Command and records its before/after values.
     */
    public function test_allocates_command_to_an_advanced_dark_lord(): void
    {
        $this->seedCharacter(['Class' => 68]);

        $result = $this->app->make(SqlServerMuDevsSeason21CharacterStatAllocationRepository::class)
            ->allocate('owner', 'Hero', new StatAllocation(0, 0, 0, 0, ['command' => 10]), $this->settings());

        self::assertSame(1510, $result->afterStats->toArray()['command']);
        self::assertSame(90, $result->afterPoints);
        self::assertSame(1510, $this->game()->table('Character')->value('Leadership'));
    }

    /**
     * Refunds native stats including Command without changing skill or quest data.
     */
    public function test_stat_reset_restores_the_advanced_family_base_stats(): void
    {
        $this->seedCharacter(['Class' => 68]);
        $before = $this->progressionSnapshot();

        $this->app->make(SqlServerMuDevsSeason21CharacterStatResetRepository::class)
            ->resetStats('owner', 'Hero', $this->settings());

        self::assertSame(25, $this->game()->table('Character')->value('Leadership'));
        self::assertSame(26, $this->game()->table('Character')->value('Strength'));
        self::assertSame($before, $this->progressionSnapshot());
    }

    /**
     * Clears only PK state and the configured Zen cost.
     */
    public function test_pk_clear_charges_zen_and_retains_native_character_progression(): void
    {
        $this->seedCharacter();
        $before = $this->progressionSnapshot();

        $this->app->make(SqlServerMuDevsSeason21CharacterPkClearRepository::class)
            ->clearPk('owner', 'Hero', $this->settings(['pkClearBaseZenCost' => 100]));

        $character = $this->game()->table('Character')->first();
        self::assertSame(3, $character->PkLevel);
        self::assertSame(0, $character->PkCount);
        self::assertSame(499999900, $character->Money);
        self::assertSame($before, $this->progressionSnapshot());
    }

    /**
     * Uses native S21 labels for preview and the completed Move to Town result.
     */
    public function test_move_to_town_resolves_native_maps(): void
    {
        $this->seedCharacter();
        $settings = $this->settings(['moveToTownMap' => 51, 'moveToTownX' => 50, 'moveToTownY' => 225]);
        $repository = $this->app->make(SqlServerMuDevsSeason21CharacterMoveRepository::class);

        $result = $repository->moveToTown('owner', 'Hero', $settings);

        self::assertSame('Elbeland', $result->afterMapName);
        self::assertSame(51, $this->game()->table('Character')->value('MapNumber'));
        self::assertSame(50, $this->game()->table('Character')->value('MapPosX'));
    }

    /**
     * Refuses every supported operation unless the locked account status proves offline.
     */
    #[DataProvider('operationNames')]
    public function test_online_state_blocks_each_operation(string $operation): void
    {
        $this->seedCharacter();
        $this->game()->table('MEMB_STAT')->update(['ConnectStat' => 1]);
        $before = (array) $this->game()->table('Character')->first();
        $caught = null;

        try {
            $this->applyOperation($operation);
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertNotNull($caught);
        self::assertSame('character_online', $caught->reason->value);
        self::assertSame($before, (array) $this->game()->table('Character')->first());
    }

    /**
     * Repeats ownership checks inside the transaction before applying any mutation.
     */
    #[DataProvider('operationNames')]
    public function test_foreign_account_cannot_mutate_character(string $operation): void
    {
        $this->seedCharacter();
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'other', 'ConnectStat' => 0]);
        $before = (array) $this->game()->table('Character')->first();
        $caught = null;

        try {
            $this->applyOperation($operation, 'other');
        } catch (Throwable $exception) {
            $caught = $exception;
        }

        self::assertNotNull($caught);
        self::assertSame('character_not_found', $caught->reason->value);
        self::assertSame($before, (array) $this->game()->table('Character')->first());
    }

    /**
     * Lists the six native guarded mutation boundaries.
     *
     * @return list<array{string}>
     */
    public static function operationNames(): array
    {
        return [['reset'], ['grand-reset'], ['allocate'], ['stat-reset'], ['pk-clear'], ['move']];
    }

    /**
     * Dispatches a test operation using the same S21 repositories as the website.
     */
    private function applyOperation(string $operation, string $account = 'owner'): void
    {
        match ($operation) {
            'reset' => $this->app->make(SqlServerMuDevsSeason21CharacterResetRepository::class)->reset($account, 'Hero', $this->settings()),
            'grand-reset' => $this->app->make(SqlServerMuDevsSeason21CharacterGrandResetRepository::class)->grandReset($account, 'Hero', $this->settings()),
            'allocate' => $this->app->make(SqlServerMuDevsSeason21CharacterStatAllocationRepository::class)->allocate($account, 'Hero', new StatAllocation(1, 0, 0, 0), $this->settings()),
            'stat-reset' => $this->app->make(SqlServerMuDevsSeason21CharacterStatResetRepository::class)->resetStats($account, 'Hero', $this->settings()),
            'pk-clear' => $this->app->make(SqlServerMuDevsSeason21CharacterPkClearRepository::class)->clearPk($account, 'Hero', $this->settings()),
            'move' => $this->app->make(SqlServerMuDevsSeason21CharacterMoveRepository::class)->moveToTown($account, 'Hero', $this->settings()),
        };
    }

    /**
     * Creates fresh owned game records with deliberately opaque native blobs.
     *
     * @param  array<string, int|string>  $overrides
     */
    private function seedCharacter(array $overrides = []): void
    {
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert(array_replace([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42,
            'Class' => 244, 'cLevel' => 400, 'LevelUpPoint' => 100,
            'ResetCount' => 100, 'MasterResetCount' => 2, 'EnhanceResetCount' => 3,
            'Strength' => 1000, 'Dexterity' => 1000, 'Vitality' => 1000, 'Energy' => 1000, 'Leadership' => 1500,
            'Money' => 500000000, 'Experience' => 123456, 'MapNumber' => 0, 'MapPosX' => 120, 'MapPosY' => 120,
            'PkLevel' => 6, 'PkCount' => 10, 'PkTime' => 100, 'CtlCode' => 0,
            'Inventory' => str_repeat("\xFF", 5975), 'Quest' => 'native-quests', 'MagicList' => 'native-skills',
            'GiantMountType' => 1, 'GiantMountStatus' => 1,
        ], $overrides));
        $this->game()->table('MasterSkillTree')->insert(['Name' => 'Hero', 'CharacterIndex' => 42, 'MasterLevel' => 400, 'MasterPoint' => 17, 'MasterExperience' => 19000000000]);
        $this->game()->table('EnhanceSkillTree')->insert(['Name' => 'Hero', 'CharacterIndex' => 42, 'EnhancePoint' => 29]);
    }

    /**
     * Captures every advanced field that these basic services must preserve.
     *
     * @return array<string, mixed>
     */
    private function progressionSnapshot(): array
    {
        return [
            'character' => (array) $this->game()->table('Character')->first(['Inventory', 'Quest', 'MagicList', 'EnhanceResetCount', 'GiantMountType', 'GiantMountStatus', 'CharacterIndex']),
            'master' => (array) $this->game()->table('MasterSkillTree')->first(),
            'enhance' => (array) $this->game()->table('EnhanceSkillTree')->first(),
        ];
    }

    /**
     * Builds explicit test rules independently of the administrator's defaults.
     *
     * @param  array<string, mixed>  $overrides
     */
    private function settings(array $overrides = []): ResetProfileSettings
    {
        return new ResetProfileSettings(...array_replace([
            'enabled' => true, 'requiredLevel' => 400, 'maximumResets' => 999,
            'maximumStat' => 65000, 'grandResetRequiredResets' => 100,
            'baseZenCost' => 1000, 'multiplyCostByReset' => false, 'clearStats' => true,
            'pointsMode' => PointsMode::Fixed, 'pointsPerReset' => 500,
            'inventoryPolicy' => InventoryPolicy::KeepAll,
        ], $overrides));
    }

    /**
     * Returns only the test bootstrap's network-isolated game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
