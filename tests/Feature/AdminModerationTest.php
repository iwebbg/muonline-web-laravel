<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\Administrator;
use App\Models\ModerationOperation;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Pagination\Paginator;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies permission-scoped, audited moderation against a 97d+99i fixture.
 */
final class AdminModerationTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated legacy moderation tables and enables fixture writes.
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

        $connection->statement('CREATE TABLE MEMB_INFO (memb___id TEXT PRIMARY KEY, memb__pwd TEXT NOT NULL, bloc_code INTEGER NULL)');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, CtlCode INTEGER NULL)');
    }

    /**
     * Confirms the search page exposes status but never legacy credentials.
     */
    public function test_administrator_can_search_non_sensitive_moderation_targets(): void
    {
        $this->insertAccount('testowner', 'legacy-secret');
        $this->insertCharacter('testowner', 'SafeHero', 8);
        $this->insertCharacter('testowner', 'SafeMage');
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/moderation?search=Safe');

        $response
            ->assertOk()
            ->assertSee('SafeHero')
            ->assertSee('SafeMage')
            ->assertSee('testowner')
            ->assertSee('Game Master')
            ->assertDontSee('Protected control code 8')
            ->assertViewHas('characterGroups', static function (array $groups): bool {
                return count($groups) === 1
                    && count($groups['testowner']) === 2;
            })
            ->assertDontSee('legacy-secret');
    }

    /**
     * Confirms account and grouped character results paginate independently.
     */
    public function test_grouped_moderation_results_use_one_account_paginator(): void
    {
        foreach (range(1, 16) as $index) {
            $accountId = sprintf('owner%02d', $index);
            $characterName = sprintf('Hero%02d', $index);

            $this->insertAccount($accountId);
            $this->insertCharacter($accountId, $characterName);
        }
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->get('/admin/moderation?page=2');

        $response
            ->assertOk()
            ->assertSee('owner16')
            ->assertSee('Hero16')
            ->assertDontSee('owner01')
            ->assertDontSee('Hero01')
            ->assertViewHas('accountTargets', static function (Paginator $targets): bool {
                return $targets->currentPage() === 2
                    && $targets->count() === 1;
            })
            ->assertViewHas('characterGroups', static function (array $groups): bool {
                return array_keys($groups) === ['owner16']
                    && count($groups['owner16']) === 1;
            });
    }

    /**
     * Confirms an account block updates bloc_code and records exact snapshots.
     */
    public function test_authorized_game_master_can_block_account_with_audit(): void
    {
        $this->insertAccount('testowner');
        $gameMaster = $this->gameMaster(AdministratorPermission::ModerateAccounts);

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->postModeration('/admin/moderation/accounts', [
                'targetIdentifier' => 'testowner',
                'action' => 'block',
                'reason' => 'Verified account abuse.',
                'idempotencyKey' => '6af10ab6-ef13-4f26-bdbf-69d03fdefd16',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('result.beforeBlocked', false)
            ->assertJsonPath('result.afterBlocked', true);

        $operation = ModerationOperation::query()->sole();

        $this->assertSame(1, $this->accountControlCode('testowner'));
        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame('Verified account abuse.', $operation->reason);
        $this->assertFalse($operation->before_snapshot['blocked']);
        $this->assertTrue($operation->after_snapshot['blocked']);
    }

    /**
     * Confirms an offline character block is idempotent.
     */
    public function test_character_moderation_is_offline_and_idempotent(): void
    {
        $this->insertAccount('testowner');
        $this->insertCharacter('testowner', 'SafeHero');
        $gameMaster = $this->gameMaster(AdministratorPermission::ModerateCharacters);
        $payload = [
            'targetIdentifier' => 'SafeHero',
            'action' => 'block',
            'reason' => 'Verified character abuse.',
            'idempotencyKey' => '5323f7fd-1860-4829-a25b-c055e80cbcc1',
        ];

        $this->actingAs($gameMaster, 'admin')
            ->postModeration('/admin/moderation/characters', $payload)
            ->assertOk();
        $this->actingAs($gameMaster, 'admin')
            ->postModeration('/admin/moderation/characters', $payload)
            ->assertOk();

        $this->assertSame(1, $this->characterControlCode('SafeHero'));
        $this->assertSame(1, ModerationOperation::query()->count());
    }

    /**
     * Confirms online state rejects CtlCode writes and records a failed audit row.
     */
    public function test_online_character_is_rejected_without_mutation(): void
    {
        $this->insertAccount('testowner', connectStatus: 1);
        $this->insertCharacter('testowner', 'SafeHero');
        $gameMaster = $this->gameMaster(AdministratorPermission::ModerateCharacters);

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->postModeration('/admin/moderation/characters', [
                'targetIdentifier' => 'SafeHero',
                'action' => 'block',
                'reason' => 'Online rejection check.',
                'idempotencyKey' => 'd850f696-1d63-4448-abf6-ec0c81d0841c',
            ]);

        $response
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'target_online');

        $operation = ModerationOperation::query()->sole();

        $this->assertSame(0, $this->characterControlCode('SafeHero'));
        $this->assertSame(CharacterOperationStatus::Failed, $operation->status);
        $this->assertSame('target_online', $operation->failure_code);
    }

    /**
     * Confirms GM control codes cannot be overwritten by character moderation.
     */
    public function test_game_master_character_control_code_is_protected(): void
    {
        $this->insertAccount('testowner');
        $this->insertCharacter('testowner', 'GameMaster', 8);
        $administrator = Administrator::factory()->create();

        $response = $this
            ->actingAs($administrator, 'admin')
            ->postModeration('/admin/moderation/characters', [
                'targetIdentifier' => 'GameMaster',
                'action' => 'unblock',
                'reason' => 'Protected code check.',
                'idempotencyKey' => 'b6fd2e89-4b1b-4736-840f-dc68d7917e95',
            ]);

        $response
            ->assertConflict()
            ->assertJsonPath('reason', 'unsupported_control_code');
        $this->assertSame(8, $this->characterControlCode('GameMaster'));
    }

    /**
     * Confirms account and character permissions cannot be used interchangeably.
     */
    public function test_game_master_permissions_are_target_specific(): void
    {
        $this->insertAccount('testowner');
        $this->insertCharacter('testowner', 'SafeHero');
        $accountModerator = $this->gameMaster(AdministratorPermission::ModerateAccounts);

        $this->actingAs($accountModerator, 'admin')
            ->get('/admin/moderation')
            ->assertOk()
            ->assertSee('testowner')
            ->assertDontSee('SafeHero');

        $this->actingAs($accountModerator, 'admin')
            ->postModeration('/admin/moderation/characters', [
                'targetIdentifier' => 'SafeHero',
                'action' => 'block',
                'reason' => 'Permission boundary check.',
                'idempotencyKey' => '73ff632a-38fc-4377-bf8f-679fa17826c7',
            ])
            ->assertForbidden();
    }

    /**
     * Confirms the global write switch rejects moderation before any audit row.
     */
    public function test_disabled_game_writes_reject_moderation_without_mutation(): void
    {
        $this->insertAccount('testowner');
        $administrator = Administrator::factory()->create();
        config()->set('game.writes_enabled', false);

        $response = $this
            ->actingAs($administrator, 'admin')
            ->postModeration('/admin/moderation/accounts', [
                'targetIdentifier' => 'testowner',
                'action' => 'block',
                'reason' => 'Write switch check.',
                'idempotencyKey' => '3989beab-c6dc-4413-a853-c765c8a1b360',
            ]);

        $response
            ->assertStatus(503)
            ->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame(0, $this->accountControlCode('testowner'));
        $this->assertSame(0, ModerationOperation::query()->count());
    }

    /**
     * Creates one permission-scoped Game Master identity.
     */
    private function gameMaster(AdministratorPermission $permission): Administrator
    {
        return Administrator::factory()
            ->gameMaster([$permission])
            ->create();
    }

    /**
     * Inserts one legacy account and connectivity fixture.
     */
    private function insertAccount(
        string $accountId,
        string $password = 'legacy-password',
        int $controlCode = 0,
        int $connectStatus = 0,
    ): void {
        $connection = $this->gameConnection();

        $connection->table('MEMB_INFO')->insert([
            'memb___id' => $accountId,
            'memb__pwd' => $password,
            'bloc_code' => $controlCode,
        ]);
        $connection->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => $connectStatus,
        ]);
    }

    /**
     * Inserts one legacy character moderation fixture.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $controlCode = 0,
    ): void {
        $this->gameConnection()->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'CtlCode' => $controlCode,
        ]);
    }

    /**
     * Returns the isolated game database connection.
     */
    private function gameConnection(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Reads one exact account block code after a moderation request.
     */
    private function accountControlCode(string $accountId): int
    {
        return (int) $this->gameConnection()
            ->table('MEMB_INFO')
            ->where('memb___id', $accountId)
            ->value('bloc_code');
    }

    /**
     * Reads one exact character control code after a moderation request.
     */
    private function characterControlCode(string $characterName): int
    {
        return (int) $this->gameConnection()
            ->table('Character')
            ->where('Name', $characterName)
            ->value('CtlCode');
    }

    /**
     * Sends one JSON moderation request with a valid CSRF token.
     *
     * @param  array{targetIdentifier: string, action: string, reason: string, idempotencyKey: string}  $payload
     */
    private function postModeration(string $uri, array $payload): TestResponse
    {
        $csrfToken = 'admin-moderation-test-token';

        return $this->withSession(['_token' => $csrfToken])->postJson($uri, [
            ...$payload,
            '_token' => $csrfToken,
        ]);
    }
}
