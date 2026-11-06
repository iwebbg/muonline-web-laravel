<?php

namespace Tests\Integration\Game\Repositories;

use App\Game\Repositories\SqlServer97d99iAccountReadRepository;
use App\Game\Versions\Version97d99i\MapNameMapper;
use App\Game\Versions\Version97d99i\Version97d99iProfile;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Tests\Integration\TestCase;

/**
 * Verifies private character reads against the disposable 97d+99i SQL Server schema.
 */
final class SqlServer97d99iAccountReadRepositoryTest extends TestCase
{
    private const ACCOUNT_ID = 'inttest1';

    private const CHARACTER_NAME = 'IntKnight';

    /**
     * Removes only the sanitized records owned by this integration test.
     */
    protected function tearDown(): void
    {
        if ($this->app !== null) {
            $connection = $this->gameConnection();
            $connection->table('Character')->where('AccountID', self::ACCOUNT_ID)->delete();
            $connection->table('MEMB_STAT')->where('memb___id', self::ACCOUNT_ID)->delete();
        }

        parent::tearDown();
    }

    /**
     * Confirms the schema fixture provides columns consumed by account reads.
     */
    public function test_script_schema_contains_required_account_read_columns(): void
    {
        $characterColumns = $this->columnNames('Character');
        $statusColumns = $this->columnNames('MEMB_STAT');

        $this->assertContains('AccountID', $characterColumns);
        $this->assertContains('Name', $characterColumns);
        $this->assertContains('cLevel', $characterColumns);
        $this->assertContains('LevelUpPoint', $characterColumns);
        $this->assertContains('Class', $characterColumns);
        $this->assertContains('Strength', $characterColumns);
        $this->assertContains('Dexterity', $characterColumns);
        $this->assertContains('Vitality', $characterColumns);
        $this->assertContains('Energy', $characterColumns);
        $this->assertContains('Money', $characterColumns);
        $this->assertContains('MapNumber', $characterColumns);
        $this->assertContains('Resets', $characterColumns);
        $this->assertContains('GrandResets', $characterColumns);
        $this->assertContains('memb___id', $statusColumns);
        $this->assertContains('ConnectStat', $statusColumns);
    }

    /**
     * Confirms the production SQL adapter maps one real SQL Server row exactly.
     */
    public function test_repository_reads_owned_character_from_sql_server(): void
    {
        $connection = $this->gameConnection();
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => self::ACCOUNT_ID,
            'ConnectStat' => 1,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => self::ACCOUNT_ID,
            'Name' => self::CHARACTER_NAME,
            'cLevel' => 400,
            'LevelUpPoint' => 250,
            'Class' => 17,
            'Strength' => 1_000,
            'Dexterity' => 900,
            'Vitality' => 800,
            'Energy' => 700,
            'Money' => 12_345_678,
            'MapNumber' => 0,
            'Resets' => 20,
            'GrandResets' => 2,
        ]);

        $repository = new SqlServer97d99iAccountReadRepository(
            database: $this->app->make(DatabaseManager::class),
            versionProfile: new Version97d99iProfile,
            mapNameMapper: new MapNameMapper,
        );

        $characters = $repository->charactersFor(self::ACCOUNT_ID);

        $this->assertCount(1, $characters);
        $this->assertSame(self::CHARACTER_NAME, $characters[0]->name);
        $this->assertSame('Blade Knight', $characters[0]->className);
        $this->assertSame(400, $characters[0]->level);
        $this->assertSame(20, $characters[0]->resets);
        $this->assertSame(2, $characters[0]->grandResets);
        $this->assertSame(12_345_678, $characters[0]->zen);
        $this->assertSame(250, $characters[0]->points);
        $this->assertSame(1_000, $characters[0]->strength);
        $this->assertSame(900, $characters[0]->agility);
        $this->assertSame(800, $characters[0]->vitality);
        $this->assertSame(700, $characters[0]->energy);
        $this->assertSame('Lorencia', $characters[0]->map);
        $this->assertTrue($characters[0]->online);
    }

    /**
     * Returns the disposable legacy game connection.
     */
    private function gameConnection(): ConnectionInterface
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Lists one legacy table's columns from SQL Server metadata.
     *
     * @return list<string>
     */
    private function columnNames(string $table): array
    {
        $rows = $this->gameConnection()->select(
            'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION',
            ['dbo', $table],
        );

        return array_map(
            static fn (object $row): string => (string) $row->COLUMN_NAME,
            $rows,
        );
    }
}
