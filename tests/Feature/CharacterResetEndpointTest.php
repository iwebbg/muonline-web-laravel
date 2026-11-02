<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterResetOperation;
use App\Models\ResetProfile;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies guarded, audited and idempotent 97d+99i character reset writes.
 */
final class CharacterResetEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated game tables and enables writes only for this test fixture.
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
     * Confirms an eligible reset mutates only the locked character and records audit snapshots.
     */
    public function test_eligible_character_is_reset_and_audited(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postReset('4d44ac27-8fc2-44d8-8750-b57608f1cbdb');

        $response
            ->assertOk()
            ->assertJsonPath('result.characterName', 'OwnedHero')
            ->assertJsonPath('result.currentResets', 4)
            ->assertJsonPath('result.resultingResets', 5)
            ->assertJsonPath('result.resultingZen', 125_000_000)
            ->assertJsonPath('result.resultingPoints', 2_500)
            ->assertJsonPath('result.statsCleared', true);

        $character = $this->gameCharacter('OwnedHero');

        $this->assertSame(1, $character['cLevel']);
        $this->assertSame(0, $character['Experience']);
        $this->assertSame(5, $character['Resets']);
        $this->assertSame(125_000_000, $character['Money']);
        $this->assertSame(2_500, $character['LevelUpPoint']);
        $this->assertSame(28, $character['Strength']);
        $this->assertSame(20, $character['Dexterity']);
        $this->assertSame(25, $character['Vitality']);
        $this->assertSame(10, $character['Energy']);

        $operation = CharacterResetOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(400, $operation->before_snapshot['level']);
        $this->assertSame(1, $operation->after_snapshot['level']);
        $this->assertNotNull($operation->completed_at);
    }

    /**
     * Confirms retrying the same operation identifier returns its result without a second reset.
     */
    public function test_duplicate_idempotency_key_does_not_reset_twice(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '53b25ab9-5e0d-4052-9779-92b1e25acd80';

        $firstResponse = $this->postReset($idempotencyKey);
        $secondResponse = $this->postReset($idempotencyKey);

        $firstResponse->assertOk();
        $secondResponse
            ->assertOk()
            ->assertJsonPath('result.resultingResets', 5);

        $this->assertSame(5, $this->gameCharacter('OwnedHero')['Resets']);
        $this->assertSame(1, CharacterResetOperation::query()->count());
    }

    /**
     * Confirms the production-safe feature flag prevents game and audit writes.
     */
    public function test_disabled_game_writes_reject_reset_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));
        config()->set('game.writes_enabled', false);

        $response = $this->postReset('62222af3-ed1e-40e8-8a19-ec3094daf975');

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');

        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterResetOperation::query()->count());
    }

    /**
     * Confirms online state is rechecked inside the write boundary and audited as a failure.
     */
    public function test_online_account_is_rejected_without_character_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', connectStatus: 1);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postReset('a2304a23-eb95-4bd0-91af-275bf2f82956');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_online');

        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(
            CharacterOperationStatus::Failed,
            CharacterResetOperation::query()->sole()->status,
        );
    }

    /**
     * Confirms unimplemented binary inventory policies fail closed.
     */
    public function test_destructive_inventory_policy_is_rejected_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->saveResetProfile(InventoryPolicy::ClearAll);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postReset('a3a9d634-90e0-4bbf-a65b-5b2d619d8934');

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'inventory_policy_unsupported');

        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms ownership is repeated inside the transaction and hidden as not found.
     */
    public function test_other_account_character_cannot_be_reset(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postReset('c408eb26-32a8-40c4-a908-1839a94d9c97');

        $response
            ->assertNotFound()
            ->assertJsonPath('reason', 'character_not_found');
        $this->assertSame(4, $this->gameCharacter('OwnedHero')['Resets']);
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
     * Inserts one complete reset fixture and its account connectivity state.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $connectStatus = 0,
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
            'Resets' => 4,
            'GrandResets' => 1,
        ]);
    }

    /**
     * Persists one reset policy used by a focused execution test.
     */
    private function saveResetProfile(InventoryPolicy $inventoryPolicy): void
    {
        ResetProfile::query()->create([
            'enabled' => true,
            'required_level' => 350,
            'maximum_resets' => 20,
            'base_zen_cost' => 15_000_000,
            'multiply_cost_by_reset' => true,
            'clear_stats' => true,
            'points_mode' => PointsMode::Cumulative,
            'points_per_reset' => 500,
            'inventory_policy' => $inventoryPolicy,
            'revision' => 1,
        ]);
    }

    /**
     * Returns the raw fixture row for exact mutation assertions.
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
     * Sends one confirmed reset request with a valid CSRF token.
     */
    private function postReset(string $idempotencyKey): TestResponse
    {
        $csrfToken = 'character-reset-endpoint-test-token';

        return $this
            ->withSession([
                '_token' => $csrfToken,
            ])
            ->postJson('/account/character-reset', [
                '_token' => $csrfToken,
                'characterName' => 'OwnedHero',
                'idempotencyKey' => $idempotencyKey,
            ]);
    }
}
