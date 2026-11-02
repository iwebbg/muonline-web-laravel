<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use Illuminate\Database\DatabaseManager;
use Tests\TestCase;

/**
 * Verifies that private character data remains scoped to the authenticated account.
 */
final class AccountDashboardTest extends TestCase
{
    /**
     * Creates isolated 97d+99i character and online-status tables.
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

        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Class INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, MapNumber INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');
    }

    /**
     * Confirms that the dashboard renders an owned character and hides another account.
     */
    public function test_dashboard_displays_only_characters_owned_by_authenticated_account(): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $connection = $database->connection('game');

        $connection->table('MEMB_STAT')->insert([
            ['memb___id' => 'owner', 'ConnectStat' => 0],
            ['memb___id' => 'other', 'ConnectStat' => 1],
        ]);
        $connection->table('Character')->insert([
            $this->characterRecord('owner', 'OwnedKnight'),
            $this->characterRecord('other', 'HiddenWizard', 1),
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response->assertOk();
        $response->assertSee('OwnedKnight');
        $response->assertDontSee('HiddenWizard');
        $response->assertSee('Blade Knight');
        $response->assertSee('Lorencia');
        $response->assertSee('Grand reset');
        $response->assertSee('grand-reset-preview', false);
        $response->assertSee('character-grand-reset', false);
        $response->assertSee('stat-reset-preview', false);
        $response->assertSee('character-stat-reset', false);
        $response->assertSee('pk-clear-preview', false);
        $response->assertSee('character-pk-clear', false);
        $response->assertSee('move-to-town-preview', false);
        $response->assertSee('character-move', false);
    }

    /**
     * Confirms that an account with no characters receives a useful empty state.
     */
    public function test_dashboard_displays_empty_state_when_account_has_no_characters(): void
    {
        $response = $this
            ->actingAs($this->account('empty'))
            ->get('/account');

        $response->assertOk();
        $response->assertSee('No characters found');
    }

    /**
     * Creates one authenticated test account identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Test User',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Creates one portable Character table record for an ownership test.
     *
     * @return array<string, int|string>
     */
    private function characterRecord(string $accountId, string $name, int $classCode = 17): array
    {
        return [
            'AccountID' => $accountId,
            'Name' => $name,
            'cLevel' => 400,
            'LevelUpPoint' => 1200,
            'Class' => $classCode,
            'Strength' => 1000,
            'Dexterity' => 800,
            'Vitality' => 700,
            'Energy' => 200,
            'Money' => 200_000_000,
            'MapNumber' => 0,
            'Resets' => 20,
            'GrandResets' => 2,
        ];
    }
}
