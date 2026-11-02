<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterStatAllocationOperation;
use App\Models\ResetProfile;
use App\Reset\InventoryPolicy;
use App\Reset\PointsMode;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies previewed, guarded and idempotent 97d+99i stat allocations.
 */
final class StatAllocationEndpointTest extends TestCase
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
    }

    /**
     * Confirms an owned character receives a server preview without game writes.
     */
    public function test_owned_character_receives_allocation_preview_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview();

        $response
            ->assertOk()
            ->assertJsonPath('preview.eligible', true)
            ->assertJsonPath('preview.allocatedPoints', 350)
            ->assertJsonPath('preview.remainingPoints', 850)
            ->assertJsonPath('preview.resultingStats.strength', 1_100)
            ->assertJsonPath('preview.maximumStat', 32_767);

        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms an eligible distribution updates exact values and audit snapshots.
     */
    public function test_eligible_allocation_is_applied_and_audited(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postAllocation(
            idempotencyKey: 'e13ed4a0-e19a-4e19-876f-492d028c1c94',
            extra: ['LevelUpPoint' => 99_999],
        );

        $response
            ->assertOk()
            ->assertJsonPath('result.afterPoints', 850)
            ->assertJsonPath('result.afterStats.strength', 1_100)
            ->assertJsonPath('result.afterStats.agility', 1_000)
            ->assertJsonPath('result.afterStats.vitality', 750)
            ->assertJsonPath('result.afterStats.energy', 200);

        $character = $this->gameCharacter('OwnedHero');

        $this->assertSame(850, $character['LevelUpPoint']);
        $this->assertSame(1_100, $character['Strength']);
        $this->assertSame(1_000, $character['Dexterity']);
        $this->assertSame(750, $character['Vitality']);
        $this->assertSame(200, $character['Energy']);

        $operation = CharacterStatAllocationOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(1_200, $operation->before_snapshot['points']);
        $this->assertSame(850, $operation->after_snapshot['points']);
        $this->assertSame(32_767, $operation->rule_snapshot['maximum_stat']);
    }

    /**
     * Confirms duplicate operation identifiers cannot allocate the same points twice.
     */
    public function test_duplicate_idempotency_key_does_not_allocate_twice(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '754874d7-bb7f-4920-a1a3-caba72596fac';

        $this->postAllocation($idempotencyKey)->assertOk();
        $secondResponse = $this->postAllocation($idempotencyKey);

        $secondResponse
            ->assertOk()
            ->assertJsonPath('result.afterPoints', 850);

        $this->assertSame(850, $this->gameCharacter('OwnedHero')['LevelUpPoint']);
        $this->assertSame(1, CharacterStatAllocationOperation::query()->count());
    }

    /**
     * Confirms the administrator maximum stat is enforced by preview and execution.
     */
    public function test_administrator_maximum_stat_rejects_overflow_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->saveResetProfile(maximumStat: 1_050);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));
        $allocation = [
            'strength' => 51,
            'agility' => 0,
            'vitality' => 0,
            'energy' => 0,
        ];

        $previewResponse = $this->postPreview($allocation);
        $executeResponse = $this->postAllocation(
            idempotencyKey: 'bc00e37c-590f-461d-9529-aa2c24952dcb',
            allocation: $allocation,
        );

        $previewResponse
            ->assertOk()
            ->assertJsonPath('preview.eligible', false)
            ->assertJsonPath('preview.maximumStat', 1_050)
            ->assertJsonPath('preview.blockReasons.0', 'stat_limit_exceeded');
        $executeResponse
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'stat_limit_exceeded');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms online state is rechecked inside the game write transaction.
     */
    public function test_online_account_returns_422_without_character_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', connectStatus: 1);
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postAllocation(
            'c6ec44fc-5c31-497f-b239-4f304953928c',
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'character_online');
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

        $response = $this->postAllocation(
            '7fdac255-c3c6-4ec6-be93-53c21b12923e',
        );

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterStatAllocationOperation::query()->count());
    }

    /**
     * Confirms another account's character remains undiscoverable through preview.
     */
    public function test_other_account_preview_returns_404(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview();

        $response->assertNotFound();
    }

    /**
     * Confirms write-boundary ownership returns 404 without changing another account.
     */
    public function test_other_account_allocation_returns_404_without_mutation(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postAllocation(
            'f73469e5-1fa8-448c-8374-d8f76d5be768',
        );

        $response
            ->assertNotFound()
            ->assertJsonPath('reason', 'character_not_found');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms reusing an operation identifier for different points returns 409.
     */
    public function test_changed_allocation_with_same_idempotency_key_returns_409(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '4ff0a72c-2af7-4e1e-a7ce-574a207cde74';

        $this->postAllocation($idempotencyKey)->assertOk();
        $response = $this->postAllocation(
            idempotencyKey: $idempotencyKey,
            allocation: [
                'strength' => 1,
                'agility' => 0,
                'vitality' => 0,
                'energy' => 0,
            ],
        );

        $response
            ->assertConflict()
            ->assertJsonPath('reason', 'idempotency_conflict');
        $this->assertSame(850, $this->gameCharacter('OwnedHero')['LevelUpPoint']);
    }

    /**
     * Confirms invalid allocation values return validation errors before any write.
     */
    public function test_negative_allocation_returns_422_validation_error(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postPreview([
            'strength' => -1,
            'agility' => 0,
            'vitality' => 0,
            'energy' => 0,
        ]);

        $response
            ->assertUnprocessable()
            ->assertJsonValidationErrors('strength');
    }

    /**
     * Confirms insufficient mutable points return 422 without any game mutation.
     */
    public function test_insufficient_points_return_422_without_character_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $beforeCharacter = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postAllocation(
            idempotencyKey: 'd3781959-63fe-474d-af3a-a92cd082ab4e',
            allocation: [
                'strength' => 1_201,
                'agility' => 0,
                'vitality' => 0,
                'energy' => 0,
            ],
        );

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'insufficient_points');
        $this->assertSame($beforeCharacter, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms guests cannot inspect private character allocation state.
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
     * Inserts one complete character and its account connectivity state.
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
     * Persists one administrator profile with an explicit stat maximum.
     */
    private function saveResetProfile(int $maximumStat): void
    {
        ResetProfile::query()->create([
            'enabled' => true,
            'required_level' => 350,
            'maximum_resets' => 100,
            'maximum_stat' => $maximumStat,
            'grand_reset_required_resets' => 100,
            'base_zen_cost' => 15_000_000,
            'multiply_cost_by_reset' => true,
            'clear_stats' => true,
            'points_mode' => PointsMode::Cumulative,
            'points_per_reset' => 500,
            'inventory_policy' => InventoryPolicy::KeepAll,
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
     * Sends one allocation preview request with a valid CSRF token.
     *
     * @param  array<string, int>|null  $allocation
     */
    private function postPreview(?array $allocation = null): TestResponse
    {
        return $this->postJsonWithCsrf(
            uri: '/account/stat-allocation-preview',
            data: [
                'characterName' => 'OwnedHero',
                ...($allocation ?? $this->validAllocation()),
            ],
        );
    }

    /**
     * Sends one confirmed allocation request with a valid CSRF token.
     *
     * @param  array<string, int>|null  $allocation
     * @param  array<string, mixed>  $extra
     */
    private function postAllocation(
        string $idempotencyKey,
        ?array $allocation = null,
        array $extra = [],
    ): TestResponse {
        return $this->postJsonWithCsrf(
            uri: '/account/stat-allocations',
            data: [
                'characterName' => 'OwnedHero',
                'idempotencyKey' => $idempotencyKey,
                ...($allocation ?? $this->validAllocation()),
                ...$extra,
            ],
        );
    }

    /**
     * Returns one valid point distribution used by endpoint tests.
     *
     * @return array{strength: int, agility: int, vitality: int, energy: int}
     */
    private function validAllocation(): array
    {
        return [
            'strength' => 100,
            'agility' => 200,
            'vitality' => 50,
            'energy' => 0,
        ];
    }

    /**
     * Sends one JSON request with a valid CSRF token.
     *
     * @param  array<string, mixed>  $data
     */
    private function postJsonWithCsrf(string $uri, array $data): TestResponse
    {
        $csrfToken = 'stat-allocation-endpoint-test-token';

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
