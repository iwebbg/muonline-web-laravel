<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterGrandResetOperation;
use App\Models\ResetProfile;
use App\Reset\InventoryPolicy;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies previewed, guarded and idempotent 97d+99i grand resets.
 */
final class GrandResetEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated game tables and enables writes only for this fixture.
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

        $this->withoutMiddleware(ThrottleRequests::class);

        $database = $this->app->make(DatabaseManager::class);

        $database->purge('game');

        $connection = $database->connection('game');

        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Class INTEGER NULL, Experience INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, MapNumber INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');
    }

    /**
     * Confirms an owned character receives a read-only DarksTeam grand-reset preview.
     */
    public function test_owned_character_receives_grand_reset_preview_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview();

        $response
            ->assertOk()
            ->assertJsonPath('preview.eligible', true)
            ->assertJsonPath('preview.requiredLevel', 350)
            ->assertJsonPath('preview.requiredResets', 100)
            ->assertJsonPath('preview.maximumGrandResets', 999)
            ->assertJsonPath('preview.resultingLevel', 1)
            ->assertJsonPath('preview.resultingResets', 20)
            ->assertJsonPath('preview.resultingGrandResets', 5)
            ->assertJsonPath('preview.zenCost', 5_000_000)
            ->assertJsonPath('preview.resultingPoints', 1_700);
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms an eligible grand reset updates exact values and audit snapshots.
     */
    public function test_eligible_grand_reset_is_applied_and_audited(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postGrandReset(
            '5e37f61e-baad-4193-b284-ef68fed77ce3',
        );

        $response
            ->assertOk()
            ->assertJsonPath('result.afterLevel', 1)
            ->assertJsonPath('result.afterResets', 20)
            ->assertJsonPath('result.afterGrandResets', 5)
            ->assertJsonPath('result.afterZen', 195_000_000)
            ->assertJsonPath('result.afterPoints', 1_700);

        $character = $this->gameCharacter('OwnedHero');

        $this->assertSame(1, $character['cLevel']);
        $this->assertSame(0, $character['Experience']);
        $this->assertSame(20, $character['Resets']);
        $this->assertSame(5, $character['GrandResets']);
        $this->assertSame(195_000_000, $character['Money']);
        $this->assertSame(1_700, $character['LevelUpPoint']);
        $this->assertSame(1_000, $character['Strength']);

        $operation = CharacterGrandResetOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(120, $operation->before_snapshot['resets']);
        $this->assertSame(20, $operation->after_snapshot['resets']);
        $this->assertSame('add_fixed', $operation->settings_snapshot['grand_reset_points_mode']);
    }

    /**
     * Confirms retrying one operation identifier cannot grand-reset twice.
     */
    public function test_duplicate_idempotency_key_does_not_grand_reset_twice(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = 'b605ac55-6a95-4e53-958e-f8ef4da00a5b';

        $this->postGrandReset($idempotencyKey)->assertOk();
        $response = $this->postGrandReset($idempotencyKey);

        $response
            ->assertOk()
            ->assertJsonPath('result.afterGrandResets', 5);
        $this->assertSame(5, $this->gameCharacter('OwnedHero')['GrandResets']);
        $this->assertSame(1, CharacterGrandResetOperation::query()->count());
    }

    /**
     * Confirms online state returns 422 without game mutation.
     */
    public function test_online_account_returns_422_without_grand_reset_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', connectStatus: 1);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postGrandReset(
            'a7340580-c557-47bf-aa5d-2a4372280f6c',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_online');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms insufficient normal resets return 422 without game mutation.
     */
    public function test_insufficient_resets_return_422_without_grand_reset_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', resets: 99);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postGrandReset(
            '2bb36e45-2394-4423-a390-09acb5385cdc',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'insufficient_resets');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms destructive inventory policies fail closed before Phase 5.
     */
    public function test_destructive_inventory_policy_returns_422_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->saveProfile([
            'grand_reset_inventory_policy' => InventoryPolicy::ClearAll->value,
        ]);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postGrandReset(
            '62d9c707-183c-4de6-b115-a6cad6fb4ba7',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'inventory_policy_unsupported');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms write-boundary ownership returns 404 without changing another account.
     */
    public function test_other_account_grand_reset_returns_404_without_mutation(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postGrandReset(
            '726296f9-ddfb-42d4-88b5-2b07af99846f',
        );

        $response
            ->assertNotFound()
            ->assertJsonPath('reason', 'character_not_found');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms disabled writes return 503 before game or audit mutation.
     */
    public function test_disabled_game_writes_return_503_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));
        config()->set('game.writes_enabled', false);

        $response = $this->postGrandReset(
            'b62478cd-88e5-4804-957f-07471b82f342',
        );

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterGrandResetOperation::query()->count());
    }

    /**
     * Confirms guests cannot inspect private grand-reset state.
     */
    public function test_guest_preview_returns_401(): void
    {
        $response = $this->postPreview();

        $response->assertUnauthorized();
    }

    /**
     * Creates one authenticated legacy game identity.
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
     * Inserts one complete grand-reset character and connectivity state.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $connectStatus = 0,
        int $resets = 120,
    ): void {
        $connection = $this->app
            ->make(DatabaseManager::class)
            ->connection('game');

        $connection->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => $connectStatus,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'cLevel' => 400,
            'LevelUpPoint' => 1_200,
            'Class' => 17,
            'Experience' => 9_999,
            'Strength' => 1_000,
            'Dexterity' => 800,
            'Vitality' => 700,
            'Energy' => 200,
            'Money' => 200_000_000,
            'MapNumber' => 0,
            'Resets' => $resets,
            'GrandResets' => 4,
        ]);
    }

    /**
     * Persists one complete profile with focused overrides.
     *
     * @param  array<string, bool|int|string>  $overrides
     */
    private function saveProfile(array $overrides): void
    {
        ResetProfile::query()->create([
            ...ResetProfileSettings::defaults()->toPersistenceArray(),
            ...$overrides,
            'revision' => 7,
        ]);
    }

    /**
     * Returns the raw game row for exact mutation assertions.
     *
     * @return array<string, mixed>
     */
    private function gameCharacter(string $characterName): array
    {
        $row = $this->app
            ->make(DatabaseManager::class)
            ->connection('game')
            ->table('Character')
            ->where('Name', $characterName)
            ->first();

        return (array) $row;
    }

    /**
     * Sends one grand-reset preview request with a valid CSRF token.
     */
    private function postPreview(): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/grand-reset-preview',
            data: [
                'characterName' => 'OwnedHero',
            ],
        );
    }

    /**
     * Sends one confirmed grand reset with a valid CSRF token.
     */
    private function postGrandReset(string $idempotencyKey): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/character-grand-reset',
            data: [
                'characterName' => 'OwnedHero',
                'idempotencyKey' => $idempotencyKey,
            ],
        );
    }

    /**
     * Sends one JSON request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function postJsonWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'grand-reset-endpoint-test-token';

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->postJson($uri, [
                ...$data,
                '_token' => $csrfToken,
            ]);
    }
}
