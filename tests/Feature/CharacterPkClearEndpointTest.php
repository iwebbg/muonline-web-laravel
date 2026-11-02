<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterPkClearOperation;
use App\Models\ResetProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies previewed, guarded and idempotent 97d+99i character PK clears.
 */
final class CharacterPkClearEndpointTest extends TestCase
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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, cLevel INTEGER NULL, Money INTEGER NULL, PkLevel INTEGER NULL, PkCount INTEGER NULL, PkTime INTEGER NULL)');

        ResetProfile::query()->create([
            ...ResetProfileSettings::defaults()->toPersistenceArray(),
            'pk_clear_base_zen_cost' => 100_000,
            'pk_clear_price_mode' => 'by_pk_level',
            'revision' => 1,
        ]);
    }

    /**
     * Confirms an owned character receives an exact read-only PK clear preview.
     */
    public function test_owned_character_receives_pk_clear_preview_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview();

        $response
            ->assertOk()
            ->assertJsonPath('preview.eligible', true)
            ->assertJsonPath('preview.zenCost', 600_000)
            ->assertJsonPath('preview.resultingZen', 9_400_000)
            ->assertJsonPath('preview.resultingPkLevel', 3)
            ->assertJsonPath('preview.resultingPkCount', 0)
            ->assertJsonPath('preview.resultingPkTime', 0);
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms an eligible PK clear updates exact values and audit snapshots.
     */
    public function test_eligible_pk_clear_is_applied_and_audited(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPkClear(
            '7e28277a-54cf-47a8-a9cb-98892e80202b',
        );

        $response
            ->assertOk()
            ->assertJsonPath('result.afterZen', 9_400_000)
            ->assertJsonPath('result.afterPkLevel', 3)
            ->assertJsonPath('result.afterPkCount', 0)
            ->assertJsonPath('result.afterPkTime', 0);

        $character = $this->gameCharacter('OwnedHero');

        $this->assertSame(9_400_000, $character['Money']);
        $this->assertSame(3, $character['PkLevel']);
        $this->assertSame(0, $character['PkCount']);
        $this->assertSame(0, $character['PkTime']);

        $operation = CharacterPkClearOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(6, $operation->before_snapshot['pk_level']);
        $this->assertSame(3, $operation->after_snapshot['pk_level']);
        $this->assertSame('by_pk_level', $operation->rule_snapshot['price_mode']);
    }

    /**
     * Confirms retrying one operation identifier cannot charge Zen twice.
     */
    public function test_duplicate_idempotency_key_does_not_clear_pk_twice(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '0c6a6ac4-40ca-4194-8575-f60bb318c8f3';

        $this->postPkClear($idempotencyKey)->assertOk();
        $this->postPkClear($idempotencyKey)
            ->assertOk()
            ->assertJsonPath('result.afterZen', 9_400_000);

        $this->assertSame(9_400_000, $this->gameCharacter('OwnedHero')['Money']);
        $this->assertSame(1, CharacterPkClearOperation::query()->count());
    }

    /**
     * Confirms online state returns 422 without game mutation.
     */
    public function test_online_account_returns_422_without_pk_clear_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', connectStatus: 1);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPkClear(
            'd9cd4b56-a233-4931-9f6e-1bc0f1fe14bd',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_online');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms normal PK state cannot be charged or rewritten.
     */
    public function test_non_killer_returns_422_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', pkLevel: 3);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPkClear(
            '20efb282-31c8-4b56-8828-bc592d7830b8',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_not_killer');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms write-boundary ownership returns 404 without changing another account.
     */
    public function test_other_account_pk_clear_returns_404_without_mutation(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPkClear(
            '694c30f4-687c-4fda-b225-bc67b53c868f',
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

        $response = $this->postPkClear(
            '770fa09a-d068-494b-a8f1-37eff390c802',
        );

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterPkClearOperation::query()->count());
    }

    /**
     * Confirms guests cannot inspect private PK state.
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
     * Inserts one complete PK character and connectivity state.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $connectStatus = 0,
        int $pkLevel = 6,
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
            'Money' => 10_000_000,
            'PkLevel' => $pkLevel,
            'PkCount' => 4,
            'PkTime' => 3_600,
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
     * Sends one PK clear preview request with a valid CSRF token.
     */
    private function postPreview(): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/pk-clear-preview',
            data: [
                'characterName' => 'OwnedHero',
            ],
        );
    }

    /**
     * Sends one confirmed PK clear with a valid CSRF token.
     */
    private function postPkClear(string $idempotencyKey): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/character-pk-clear',
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
        $csrfToken = 'character-pk-clear-test-token';

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
