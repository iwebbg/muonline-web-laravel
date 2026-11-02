<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Operations\CharacterOperationStatus;
use App\Models\CharacterMoveOperation;
use App\Models\ResetProfile;
use App\Reset\ResetProfileSettings;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Verifies previewed, guarded and idempotent 97d+99i character moves.
 */
final class CharacterMoveEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates isolated legacy tables and enables writes only for this fixture.
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
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Money INTEGER NULL, MapNumber INTEGER NULL, MapPosX INTEGER NULL, MapPosY INTEGER NULL)');

        ResetProfile::query()->create([
            ...ResetProfileSettings::defaults()->toPersistenceArray(),
            'move_to_town_zen_cost' => 2_000,
            'revision' => 1,
        ]);
    }

    /**
     * Confirms preview and execution produce exact location and audit state.
     */
    public function test_owned_offline_character_can_preview_and_move_to_town(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));

        $this->postPreview()
            ->assertOk()
            ->assertJsonPath('preview.destinationMapName', 'Lorencia')
            ->assertJsonPath('preview.destinationX', 141)
            ->assertJsonPath('preview.destinationY', 127)
            ->assertJsonPath('preview.zenCost', 2_000);

        $response = $this->postMove('7e28277a-54cf-47a8-a9cb-98892e80202b');

        $response
            ->assertOk()
            ->assertJsonPath('result.afterMap', 0)
            ->assertJsonPath('result.afterX', 141)
            ->assertJsonPath('result.afterY', 127)
            ->assertJsonPath('result.afterZen', 9_998_000);

        $character = $this->gameCharacter('OwnedHero');
        $operation = CharacterMoveOperation::query()->sole();

        $this->assertSame(0, $character['MapNumber']);
        $this->assertSame(141, $character['MapPosX']);
        $this->assertSame(127, $character['MapPosY']);
        $this->assertSame(9_998_000, $character['Money']);
        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(8, $operation->before_snapshot['map']);
        $this->assertSame(0, $operation->after_snapshot['map']);
    }

    /**
     * Confirms retrying one operation identifier never charges or moves twice.
     */
    public function test_duplicate_idempotency_key_returns_the_original_result(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $this->actingAs($this->account('owner'));
        $idempotencyKey = '0c6a6ac4-40ca-4194-8575-f60bb318c8f3';

        $this->postMove($idempotencyKey)->assertOk();
        $this->postMove($idempotencyKey)
            ->assertOk()
            ->assertJsonPath('result.afterZen', 9_998_000);

        $this->assertSame(9_998_000, $this->gameCharacter('OwnedHero')['Money']);
        $this->assertSame(1, CharacterMoveOperation::query()->count());
    }

    /**
     * Confirms online state blocks the write and preserves location.
     */
    public function test_online_character_returns_422_without_location_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero', 1);
        $before = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postMove('d9cd4b56-a233-4931-9f6e-1bc0f1fe14bd');

        $response->assertUnprocessable()->assertJsonPath('reason', 'character_online');
        $this->assertSame($before, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms ownership isolation hides and preserves another account's character.
     */
    public function test_other_account_character_returns_404_without_mutation(): void
    {
        $this->insertCharacter('other', 'OwnedHero');
        $before = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));

        $response = $this->postMove('694c30f4-687c-4fda-b225-bc67b53c868f');

        $response->assertNotFound()->assertJsonPath('reason', 'character_not_found');
        $this->assertSame($before, $this->gameCharacter('OwnedHero'));
    }

    /**
     * Confirms both location endpoints require an authenticated account.
     */
    public function test_guest_cannot_preview_or_execute_character_move(): void
    {
        $this->postPreview()->assertUnauthorized();
        $this->postMove('770fa09a-d068-494b-a8f1-37eff390c802')->assertUnauthorized();
    }

    /**
     * Confirms explicit write flags reject mutation before an audit row is created.
     */
    public function test_disabled_game_writes_return_503_without_mutation(): void
    {
        $this->insertCharacter('owner', 'OwnedHero');
        $before = $this->gameCharacter('OwnedHero');
        $this->actingAs($this->account('owner'));
        config()->set('game.writes_enabled', false);

        $response = $this->postMove('14ab2192-9a78-467d-bc8e-e325416f270e');

        $response->assertStatus(503)->assertJsonPath('reason', 'writes_disabled');
        $this->assertSame($before, $this->gameCharacter('OwnedHero'));
        $this->assertSame(0, CharacterMoveOperation::query()->count());
    }

    /**
     * Creates one authenticated legacy game identity.
     */
    private function account(string $username): GameAccount
    {
        return new GameAccount(1, $username, 'Test Player', null, false, false, 'secret1');
    }

    /**
     * Inserts one complete location and connectivity fixture.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $connectStatus = 0,
    ): void {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');

        $connection->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => $connectStatus,
        ]);
        $connection->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'Money' => 10_000_000,
            'MapNumber' => 8,
            'MapPosX' => 200,
            'MapPosY' => 60,
        ]);
    }

    /**
     * Returns one normalized game record for exact before/after assertions.
     *
     * @return array<string, int|string>
     */
    private function gameCharacter(string $characterName): array
    {
        $row = $this->app->make(DatabaseManager::class)
            ->connection('game')
            ->table('Character')
            ->where('Name', $characterName)
            ->first();

        return array_map(
            static fn (mixed $value): int|string => is_numeric($value) ? (int) $value : (string) $value,
            (array) $row,
        );
    }

    /**
     * Sends one preview request for the fixture character.
     */
    private function postPreview(): TestResponse
    {
        $csrfToken = 'character-move-test-token';

        return $this->withSession(['_token' => $csrfToken])->postJson('/account/move-to-town-preview', [
            'characterName' => 'OwnedHero',
            '_token' => $csrfToken,
        ]);
    }

    /**
     * Sends one idempotent move request for the fixture character.
     */
    private function postMove(string $idempotencyKey): TestResponse
    {
        $csrfToken = 'character-move-test-token';

        return $this->withSession(['_token' => $csrfToken])->postJson('/account/character-move', [
            'characterName' => 'OwnedHero',
            'idempotencyKey' => $idempotencyKey,
            '_token' => $csrfToken,
        ]);
    }
}
