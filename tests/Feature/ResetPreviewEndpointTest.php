<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies the authenticated and ownership-scoped reset preview endpoint.
 */
final class ResetPreviewEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated 97d+99i tables used by reset preview requests.
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
     * Confirms that an owned offline character receives a read-only preview.
     */
    public function test_owned_character_receives_reset_preview_without_game_write(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');

        $this->actingAs($this->account('owner'));

        $response = $this->postJsonWithCsrf('/account/reset-preview', [
            'characterName' => 'OwnedHero',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('preview.characterName', 'OwnedHero')
            ->assertJsonPath('preview.eligible', true)
            ->assertJsonPath('preview.requiredLevel', 350)
            ->assertJsonPath('preview.maximumResets', 999)
            ->assertJsonPath('preview.resultingResets', 5)
            ->assertJsonPath('preview.zenCost', 75_000_000)
            ->assertJsonPath('preview.resultingZen', 125_000_000)
            ->assertJsonPath('preview.resultingPoints', 2_500);

        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms that another account's character cannot be discovered through preview.
     */
    public function test_other_account_character_returns_not_found(): void
    {
        $this->insertCharacter('other', 'HiddenHero');

        $this->actingAs($this->account('owner'));

        $response = $this->postJsonWithCsrf('/account/reset-preview', [
            'characterName' => 'HiddenHero',
        ]);

        $response->assertNotFound();
    }

    /**
     * Confirms that malformed legacy character identifiers are rejected.
     */
    public function test_invalid_character_name_returns_validation_error(): void
    {
        $this->actingAs($this->account('owner'));

        $response = $this->postJsonWithCsrf('/account/reset-preview', [
            'characterName' => '../other',
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('characterName');
    }

    /**
     * Confirms that guests cannot query private character state.
     */
    public function test_guest_cannot_request_reset_preview(): void
    {
        $response = $this->postJsonWithCsrf('/account/reset-preview', [
            'characterName' => 'OwnedHero',
        ]);

        $response->assertUnauthorized();
    }

    /**
     * Creates one authenticated game identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(
            guid: 1,
            username: $username,
            displayName: 'Test Player',
            email: null,
            blocked: false,
            vip: false,
            password: 'secret1',
        );
    }

    /**
     * Inserts one character and its account connectivity state.
     */
    private function insertCharacter(string $accountId, string $characterName): void
    {
        $database = $this->app->make(DatabaseManager::class);
        $connection = $database->connection('game');

        $connection->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => 0,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'cLevel' => 400,
            'LevelUpPoint' => 1_200,
            'Class' => 17,
            'Strength' => 1_000,
            'Dexterity' => 800,
            'Vitality' => 700,
            'Energy' => 200,
            'Money' => 200_000_000,
            'MapNumber' => 0,
            'Resets' => 4,
            'GrandResets' => 1,
        ]);
    }

    /**
     * Returns the raw game row used to prove that preview performs no mutation.
     *
     * @return array<string, mixed>
     */
    private function gameCharacter(string $characterName): array
    {
        $database = $this->app->make(DatabaseManager::class);
        $row = $database
            ->connection('game')
            ->table('Character')
            ->where('Name', $characterName)
            ->first();

        return (array) $row;
    }

    /**
     * Sends one JSON request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function postJsonWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'reset-preview-endpoint-test-token';
        $payload = [
            ...$data,
            '_token' => $csrfToken,
        ];

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->postJson($uri, $payload);
    }
}
