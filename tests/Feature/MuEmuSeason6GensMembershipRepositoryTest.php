<?php

namespace Tests\Feature;

use App\Game\Data\GensFamily;
use App\Game\Repositories\SqlServerMuEmuSeason6GensMembershipRepository;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies account-scoped Gens reads against the standard Season 6 fields.
 */
final class MuEmuSeason6GensMembershipRepositoryTest extends TestCase
{
    /**
     * Creates isolated character and Gens membership tables.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('database.connections.game', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY)');
        $connection->statement('CREATE TABLE Gens_Rank (Name TEXT PRIMARY KEY, Family INTEGER NULL, Contribution INTEGER NULL)');
    }

    /**
     * Confirms family rank includes all members but returns only owned characters.
     */
    public function test_returns_only_owned_gens_memberships_with_dense_family_rank(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert([
            ['AccountID' => 'owner', 'Name' => 'OwnedDuprian'],
            ['AccountID' => 'owner', 'Name' => 'OwnedVanert'],
            ['AccountID' => 'other', 'Name' => 'HiddenDuprian'],
        ]);
        $connection->table('Gens_Rank')->insert([
            ['Name' => 'OwnedDuprian', 'Family' => 1, 'Contribution' => 15_000],
            ['Name' => 'OwnedVanert', 'Family' => 2, 'Contribution' => 9_000],
            ['Name' => 'HiddenDuprian', 'Family' => 1, 'Contribution' => 20_000],
        ]);

        $memberships = $this->repository()->forAccount('owner');

        $this->assertSame(['OwnedDuprian', 'OwnedVanert'], array_keys($memberships));
        $this->assertSame(GensFamily::Duprian, $memberships['OwnedDuprian']->family);
        $this->assertSame(15_000, $memberships['OwnedDuprian']->contribution);
        $this->assertSame(2, $memberships['OwnedDuprian']->rank);
        $this->assertSame(GensFamily::Vanert, $memberships['OwnedVanert']->family);
        $this->assertSame(0, $memberships['OwnedVanert']->rank);
    }

    /**
     * Confirms non-standard family codes never cross the generic Gens boundary.
     */
    public function test_ignores_unknown_gens_family_codes(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert(['AccountID' => 'owner', 'Name' => 'UnknownFamily']);
        $connection->table('Gens_Rank')->insert([
            'Name' => 'UnknownFamily',
            'Family' => 3,
            'Contribution' => 50_000,
        ]);

        $memberships = $this->repository()->forAccount('owner');

        $this->assertSame([], $memberships);
    }

    /**
     * Builds the Gens adapter with the isolated game connection.
     */
    private function repository(): SqlServerMuEmuSeason6GensMembershipRepository
    {
        return new SqlServerMuEmuSeason6GensMembershipRepository(
            $this->app->make(DatabaseManager::class),
        );
    }
}
