<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterStatResetOperation;
use App\Models\ResetProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies previewed, guarded and idempotent 97d+99i character stat resets.
 */
final class CharacterStatResetEndpointTest extends TestCase
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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Class INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, MapNumber INTEGER NULL, Resets INTEGER NOT NULL, GrandResets INTEGER NOT NULL)');

        ResetProfile::query()->create([
            ...ResetProfileSettings::defaults()->toPersistenceArray(),
            'stat_reset_zen_cost' => 5_000_000,
            'revision' => 1,
        ]);
    }

    /**
     * Confirms an owned character receives an exact read-only stat-reset preview.
     */
    public function test_owned_character_receives_stat_reset_preview_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview();

        $response
            ->assertOk()
            ->assertJsonPath('preview.eligible', true)
            ->assertJsonPath('preview.refundedPoints', 2_617)
            ->assertJsonPath('preview.resultingPoints', 3_817)
            ->assertJsonPath('preview.resultingZen', 195_000_000)
            ->assertJsonPath('preview.resultingStats.strength', 28);
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms an eligible stat reset updates exact values and audit snapshots.
     */
    public function test_eligible_stat_reset_is_applied_and_audited(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postStatReset(
            'cf23d03b-456d-4a70-9725-658284a14a44',
        );

        $response
            ->assertOk()
            ->assertJsonPath('result.refundedPoints', 2_617)
            ->assertJsonPath('result.afterPoints', 3_817)
            ->assertJsonPath('result.afterZen', 195_000_000)
            ->assertJsonPath('result.afterStats.strength', 28);

        $character = $this->gameCharacter('OwnedHero');

        $this->assertSame(3_817, $character['LevelUpPoint']);
        $this->assertSame(195_000_000, $character['Money']);
        $this->assertSame(28, $character['Strength']);
        $this->assertSame(20, $character['Dexterity']);
        $this->assertSame(25, $character['Vitality']);
        $this->assertSame(10, $character['Energy']);

        $operation = CharacterStatResetOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(1_000, $operation->before_snapshot['stats']['strength']);
        $this->assertSame(28, $operation->after_snapshot['stats']['strength']);
        $this->assertSame(2_617, $operation->after_snapshot['refunded_points']);
    }

    /**
     * Confirms retrying one operation identifier cannot refund stats twice.
     */
    public function test_duplicate_idempotency_key_does_not_reset_stats_twice(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '1d3de4f2-c0b3-4857-b29f-a66baa399bdb';

        $this->postStatReset($idempotencyKey)->assertOk();
        $this->postStatReset($idempotencyKey)
            ->assertOk()
            ->assertJsonPath('result.afterPoints', 3_817);

        $this->assertSame(3_817, $this->gameCharacter('OwnedHero')['LevelUpPoint']);
        $this->assertSame(1, CharacterStatResetOperation::query()->count());
    }

    /**
     * Confirms online state returns 422 without game mutation.
     */
    public function test_online_account_returns_422_without_stat_reset_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', connectStatus: 1);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postStatReset(
            'ec29e6a9-cfd1-416e-b64d-62ab227496b4',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_online');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms base-only stats cannot be refunded repeatedly.
     */
    public function test_base_only_character_returns_422_without_mutation(): void
    {
        $this->insertCharacter(
            accountId: 'owner',
            characterName: 'OwnedHero',
            strength: 28,
            agility: 20,
            vitality: 25,
            energy: 10,
        );
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postStatReset(
            '558f19a6-1e89-420a-92ec-0e92d1df5ca4',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'no_allocated_stats');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms write-boundary ownership returns 404 without changing another account.
     */
    public function test_other_account_stat_reset_returns_404_without_mutation(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postStatReset(
            '46a68dbd-2053-4d3e-afd3-93773e3fe70f',
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

        $response = $this->postStatReset(
            '9f844cf1-3ae3-4227-91f5-623fd1e7443f',
        );

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterStatResetOperation::query()->count());
    }

    /**
     * Confirms guests cannot inspect private stat-reset state.
     */
    public function test_guest_preview_returns_401(): void
    {
        $this->postPreview()->assertUnauthorized();
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
     * Inserts one complete stat-reset character and connectivity state.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $connectStatus = 0,
        int $strength = 1_000,
        int $agility = 800,
        int $vitality = 700,
        int $energy = 200,
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
            'Strength' => $strength,
            'Dexterity' => $agility,
            'Vitality' => $vitality,
            'Energy' => $energy,
            'Money' => 200_000_000,
            'MapNumber' => 0,
            'Resets' => 20,
            'GrandResets' => 4,
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
     * Sends one stat-reset preview request with a valid CSRF token.
     */
    private function postPreview(): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/stat-reset-preview',
            data: [
                'characterName' => 'OwnedHero',
            ],
        );
    }

    /**
     * Sends one confirmed stat reset with a valid CSRF token.
     */
    private function postStatReset(string $idempotencyKey): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/character-stat-reset',
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
        $csrfToken = 'character-stat-reset-test-token';

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
