<?php

namespace Tests\IntegrationSeason21;

use App\Game\Repositories\SqlServerMuDevsSeason21AccountMembershipReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21GensMembershipRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MasterProgressionRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21WarehouseInspectionRepository;
use App\Game\Versions\MuDevsSeason21\GuildMarkDecoderMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\ItemCodecMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MapNameMapperMuDevsSeason21;
use App\Game\Versions\MuDevsSeason21\MuDevsSeason21Profile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies S21 read queries and binary guild marks on disposable SQL Server.
 */
final class MuDevsSeason21ReadIntegrationTest extends TestCase
{
    /**
     * Preserves a captured high-tier socket header while excluding its flag from ancient state.
     */
    public function test_reads_tier_seven_socket_header_from_native_sql_server_binary(): void
    {
        $connection = $this->gameConnection();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-cyclone-fire-tier-seven.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->statement(
            'INSERT INTO warehouse (AccountID, AccountIndex, Items, Money) VALUES (?, ?, CONVERT(varbinary(6000), ?, 2), 0)',
            ['s21grade7', 1, strtoupper(bin2hex($binary))],
        );

        $records = $this->app->make(SqlServerMuDevsSeason21WarehouseInspectionRepository::class)->forAccount('s21grade7');

        self::assertNotNull($records);
        self::assertSame('0:36', $records[3]->code());
        self::assertSame([46, 254, 254, 255, 255], $records[3]->socketOptions);
        self::assertSame(64, $records[3]->socketExtensionFlags);
        self::assertSame(0, $records[3]->ancientOption);
        self::assertNull($records[3]->ancientSetName);
        self::assertSame($binary, $this->app->make(ItemCodecMuDevsSeason21::class)->encode($records));
    }

    /**
     * Reads full 6,000-byte storage through SQL Server while preserving owner and page scope.
     */
    public function test_inspects_native_warehouse_binary_and_extended_pages_without_changes(): void
    {
        $connection = $this->gameConnection();
        $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-warehouse-items.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = str_repeat("\xFF", 6000);

        foreach ($fixtures as $fixture) {
            $binary = substr_replace($binary, hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        }

        $connection->statement(
            'INSERT INTO warehouse (AccountID, AccountIndex, Items, Money) VALUES (?, ?, CONVERT(varbinary(6000), ?, 2), 0)',
            ['s21owner', 1, strtoupper(bin2hex($binary))],
        );
        $connection->statement(
            'INSERT INTO ExtWarehouse (AccountID, AccountIndex, Number, Items, Money) VALUES (?, ?, ?, CONVERT(varbinary(6000), ?, 2), 0)',
            ['s21owner', 1, 2, strtoupper(bin2hex($binary))],
        );
        $connection->statement(
            'INSERT INTO ExtWarehouse (AccountID, AccountIndex, Number, Items, Money) VALUES (?, ?, ?, CONVERT(varbinary(6000), ?, 2), 0)',
            ['s21other', 2, 1, strtoupper(bin2hex($binary))],
        );
        $inspector = $this->app->make(SqlServerMuDevsSeason21WarehouseInspectionRepository::class);

        $records = $inspector->forAccount('s21owner');
        $extendedRecords = $inspector->forAccount('s21owner', 2);

        self::assertNotNull($records);
        self::assertNotNull($extendedRecords);
        self::assertCount(240, $records);
        self::assertSame('7:270', $records[0]->code());
        self::assertSame([254, 254, 254, 255, 255], $records[66]->socketOptions);
        self::assertSame(63, $records[75]->excellentMask);
        self::assertSame("Warrior's", $records[99]->ancientSetName);
        self::assertSame(17, $records[110]->harmonyOption);
        self::assertSame(0, $records[38]->socketBonus);
        self::assertSame($binary, $this->app->make(ItemCodecMuDevsSeason21::class)->encode($records));
        self::assertSame($binary, $this->app->make(ItemCodecMuDevsSeason21::class)->encode($extendedRecords));
        self::assertNull($inspector->forAccount('s21owner', 1));
        self::assertNull($inspector->forAccount('missing'));
        self::assertSame(6000, (int) $connection->table('warehouse')->where('AccountID', 's21owner')->selectRaw('DATALENGTH(Items) AS byte_count')->value('byte_count'));
    }

    /**
     * Starts an isolated transaction after the base case validates the SQL endpoint.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->gameConnection()->beginTransaction();
    }

    /**
     * Rolls back every fixture record while preserving the disposable schema.
     */
    protected function tearDown(): void
    {
        try {
            if ($this->app !== null && $this->gameConnection()->transactionLevel() > 0) {
                $this->gameConnection()->rollBack();
            }
        } finally {
            parent::tearDown();
        }
    }

    /**
     * Applies native SQL Server expiry rules without invoking the mutating VIP procedure.
     */
    public function test_reads_account_membership_using_the_native_database_clock_without_updates(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_INFO')->insert([
            ['memb_guid' => 1, 'memb___id' => 's21active', 'AccountLevel' => 2, 'AccountExpireDate' => '2070-01-01 12:30:00'],
            ['memb_guid' => 2, 'memb___id' => 's21expired', 'AccountLevel' => 3, 'AccountExpireDate' => '2000-01-01 12:30:00'],
        ]);
        $reader = $this->app->make(SqlServerMuDevsSeason21AccountMembershipReadRepository::class);
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $active = $reader->forAccount('s21active');
        $expired = $reader->forAccount('s21expired');

        self::assertSame(2, $active->effectiveLevel);
        self::assertFalse($active->expired);
        self::assertSame(0, $expired->effectiveLevel);
        self::assertTrue($expired->expired);
        self::assertSame(3, $expired->storedLevel);
        self::assertStringStartsWith('2000-01-01 12:30:00', $expired->expiresAt);
        foreach ($connection->getQueryLog() as $query) {
            self::assertStringStartsWith('select', strtolower($query['query']));
        }
        self::assertSame(3, (int) $connection->table('MEMB_INFO')->where('memb___id', 's21expired')->value('AccountLevel'));
        self::assertNull($reader->forAccount('missing'));
    }

    /**
     * Confirms private queries preserve Command and never return foreign characters.
     */
    public function test_reads_owned_characters_with_native_sql_server_types(): void
    {
        $this->seedCharacters();
        $reader = new SqlServerMuDevsSeason21AccountReadRepository(
            $this->app->make(DatabaseManager::class),
            new MuDevsSeason21Profile,
            new MapNameMapperMuDevsSeason21,
        );

        $character = $reader->characterFor('s21owner', 'S21Lord');

        self::assertSame(['command' => 620], $character?->additionalStats);
        self::assertSame('Aquilas Temple', $character?->map);
        self::assertSame(200, $character?->level);
        self::assertFalse($character?->online);
        self::assertNull($reader->characterFor('s21owner', 'S21Hidden'));
        self::assertCount(1, $reader->charactersFor('s21owner'));
    }

    /**
     * Keeps native sequential family rank and low-contribution standings on SQL Server.
     */
    public function test_reads_native_gens_ties_and_low_contribution_by_character_index(): void
    {
        $this->seedCharacters();
        $connection = $this->gameConnection();
        $connection->table('Gens_Rank')->insert([
            ['Name' => 'Alpha', 'CharacterIndex' => 202, 'Family' => 1, 'Contribution' => 9999],
            ['Name' => 'Zcached', 'CharacterIndex' => 101, 'Family' => 1, 'Contribution' => 9999],
        ]);
        $reader = new SqlServerMuDevsSeason21GensMembershipRepository(
            $this->app->make(DatabaseManager::class),
            new MuDevsSeason21Profile,
        );

        $memberships = $reader->forAccount('s21owner');

        self::assertSame(['S21Lord'], array_keys($memberships));
        self::assertSame(2, $memberships['S21Lord']->rank);
        self::assertSame(9999, $memberships['S21Lord']->contribution);
        self::assertSame(1, $reader->forCharacter('S21Hidden')->rank);
    }

    /**
     * Confirms bigint experience and index-based joins survive the SQL Server driver.
     */
    public function test_reads_master_and_enhancement_progression_by_index_with_stale_names(): void
    {
        $this->seedCharacters();
        $connection = $this->gameConnection();
        $connection->table('MasterSkillTree')->insert([
            'Name' => 'S21Hidden', 'CharacterIndex' => 101,
            'MasterLevel' => 500, 'MasterPoint' => 12, 'MasterExperience' => 9_000_000_000,
        ]);
        $connection->table('EnhanceSkillTree')->insert([
            'Name' => 'S21Hidden', 'CharacterIndex' => 101, 'EnhancePoint' => 23,
        ]);
        $reader = new SqlServerMuDevsSeason21MasterProgressionRepository(
            $this->app->make(DatabaseManager::class),
        );

        $progression = $reader->forAccount('s21owner');

        self::assertSame(['S21Lord'], array_keys($progression));
        self::assertSame([
            'level' => 500, 'availablePoints' => 12, 'experience' => 9_000_000_000,
            'enhancementPoints' => 23,
        ], $progression['S21Lord']->toArray());
        self::assertSame([], $reader->forAccount('s21other'));
        self::assertSame(9_000_000_000, $reader->forCharacter('S21Lord')?->experience);
    }

    /**
     * Confirms SQL Server pagination, public privacy and packed guild marks are compatible.
     */
    public function test_reads_rankings_public_profiles_and_binary_guild_marks(): void
    {
        $this->seedCharacters();
        $connection = $this->gameConnection();
        $connection->table('Guild')->insert([
            'G_Name' => 'S21Test', 'G_Master' => 'S21Lord', 'G_Score' => 10,
            'G_Count' => 1, 'G_Notice' => 'Test',
        ]);
        $connection->statement(
            'UPDATE Guild SET G_Mark = CONVERT(varbinary(32), ?, 2) WHERE G_Name = ?',
            [str_repeat('11', 32), 'S21Test'],
        );
        $connection->table('GuildMember')->insert(['Name' => 'S21Lord', 'G_Name' => 'S21Test']);
        $reader = new SqlServerMuDevsSeason21RealmReadRepository(
            $this->app->make(DatabaseManager::class),
            new MuDevsSeason21Profile,
            new GuildMarkDecoderMuDevsSeason21,
            new MapNameMapperMuDevsSeason21,
        );

        $rankings = $reader->topCharacters(1);
        $character = $reader->publicCharacter('S21Lord');
        $guild = $reader->publicGuild('S21Test');

        self::assertSame('S21Hidden', $rankings[0]->name);
        self::assertSame('50% 35%', $rankings[0]->imagePosition);
        self::assertSame('S21Test', $character?->guildName);
        self::assertArrayNotHasKey('accountId', get_object_vars($character));
        self::assertSame(array_fill(0, 64, '#000000'), $guild?->markColors);
        self::assertSame(2, $reader->overview()->characters);
        self::assertCount(2, $reader->analytics()->classes);
    }

    /**
     * Creates reserved synthetic accounts and characters only inside the test transaction.
     */
    private function seedCharacters(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_INFO')->insert([
            ['memb_guid' => 1, 'memb___id' => 's21owner', 'AccountLevel' => 0],
            ['memb_guid' => 2, 'memb___id' => 's21other', 'AccountLevel' => 0],
        ]);
        $connection->table('MEMB_STAT')->insert([
            ['memb___id' => 's21owner', 'ConnectStat' => 0],
            ['memb___id' => 's21other', 'ConnectStat' => 1],
        ]);
        $connection->table('Character')->insert([
            [
                'AccountID' => 's21owner', 'Name' => 'S21Lord', 'CharacterIndex' => 101,
                'Class' => 64, 'cLevel' => 200, 'Strength' => 100, 'Dexterity' => 200,
                'Vitality' => 300, 'Energy' => 400, 'Leadership' => 620,
                'ResetCount' => 1, 'MasterResetCount' => 0, 'MapNumber' => 145,
            ],
            [
                'AccountID' => 's21other', 'Name' => 'S21Hidden', 'CharacterIndex' => 202,
                'Class' => 240, 'cLevel' => 300, 'Strength' => 100, 'Dexterity' => 200,
                'Vitality' => 300, 'Energy' => 400, 'Leadership' => 0,
                'ResetCount' => 2, 'MasterResetCount' => 0, 'MapNumber' => 141,
            ],
        ]);
    }

    /**
     * Returns the SQL Server connection already guarded by the integration base case.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
