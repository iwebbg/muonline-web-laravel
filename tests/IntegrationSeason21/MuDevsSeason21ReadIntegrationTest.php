<?php

namespace Tests\IntegrationSeason21;

use App\Game\Repositories\SqlServerMuDevsSeason21AccountReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21MasterProgressionRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21RealmReadRepository;
use App\Game\Versions\MuDevsSeason21\GuildMarkDecoderMuDevsSeason21;
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
