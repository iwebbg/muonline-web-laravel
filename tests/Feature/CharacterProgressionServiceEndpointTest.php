<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Economy\WalletCurrency;
use App\Game\Contracts\GameAccountLookupRepository;
use App\Models\AccountWallet;
use App\Models\EconomySetting;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Verifies paid progression endpoints enforce authentication, packages and writes.
 */
final class CharacterProgressionServiceEndpointTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Creates an isolated legacy character schema with explicit writes enabled.
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

        $database = $this->app->make(DatabaseManager::class);
        $database->purge('game');
        $connection = $database->connection('game');
        $connection->statement('CREATE TABLE MEMB_STAT (memb___id TEXT PRIMARY KEY, ConnectStat INTEGER NULL)');
        $connection->statement('CREATE TABLE Character (AccountID TEXT NOT NULL, Name TEXT PRIMARY KEY, Class INTEGER NOT NULL, cLevel INTEGER NULL, LevelUpPoint INTEGER NULL, Experience INTEGER NULL, Strength INTEGER NULL, Dexterity INTEGER NULL, Vitality INTEGER NULL, Energy INTEGER NULL, Money INTEGER NULL, MapNumber INTEGER NULL, Resets INTEGER NULL, GrandResets INTEGER NULL)');

        $this->mock(GameAccountLookupRepository::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturnTrue();
        });
    }

    /**
     * Confirms guests cannot purchase either progression service.
     */
    public function test_guest_cannot_purchase_character_progression(): void
    {
        $this->post(route('account.character-reset-purchases.store'), [])
            ->assertRedirect(route('login'));
        $this->post(route('account.character-level-up-points-purchases.store'), [])
            ->assertRedirect(route('login'));
    }

    /**
     * Confirms package multipliers outside the public catalog are rejected.
     */
    public function test_progression_purchase_rejects_unknown_package(): void
    {
        $response = $this
            ->actingAs($this->account('owner'))
            ->from('/account?character=Hero&tab=services&service=buy-resets')
            ->post(route('account.character-reset-purchases.store'), [
                'character_name' => 'Hero',
                'package_count' => 2,
                'idempotency_key' => '2f98154f-af8c-4a78-96de-3374976c0283',
            ]);

        $response
            ->assertRedirect('/account?character=Hero&tab=services&service=buy-resets')
            ->assertSessionHasErrors('package_count');
        $this->assertDatabaseCount('character_service_operations', 0);
    }

    /**
     * Confirms the reset endpoint applies a package and redirects to the workspace.
     */
    public function test_reset_purchase_endpoint_applies_package(): void
    {
        $this->enableProgressionServices();
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'ResetHero', resets: 2, points: 1_200);

        $response = $this
            ->actingAs($this->account('owner'))
            ->post(route('account.character-reset-purchases.store'), [
                'character_name' => 'ResetHero',
                'package_count' => 5,
                'idempotency_key' => '1107fd2a-9825-4498-bb9f-6f26f1daf7b8',
            ]);

        $response
            ->assertRedirect(route('account.dashboard', [
                'tab' => 'services',
                'character' => 'ResetHero',
            ]))
            ->assertSessionHas('accountStatus', __('ui.account.character_reset_purchase_completed'));
        $this->assertSame(1_500, AccountWallet::query()->sole()->balance);
        $this->assertSame(
            7,
            (int) $this->game()->table('Character')->where('Name', 'ResetHero')->value('Resets'),
        );
    }

    /**
     * Confirms the points endpoint adds the selected package without allocating it.
     */
    public function test_level_up_points_purchase_endpoint_adds_package(): void
    {
        $this->enableProgressionServices();
        $this->wallet('owner', 2_000);
        $this->insertCharacter('owner', 'PointHero', points: 1_200);

        $response = $this
            ->actingAs($this->account('owner'))
            ->post(route('account.character-level-up-points-purchases.store'), [
                'character_name' => 'PointHero',
                'package_count' => 5,
                'idempotency_key' => 'bbf0afab-302d-4b0a-a6a7-09270ec8b756',
            ]);

        $response
            ->assertRedirect(route('account.dashboard', [
                'tab' => 'services',
                'character' => 'PointHero',
            ]))
            ->assertSessionHas('accountStatus', __('ui.account.character_level_up_points_purchase_completed'));
        $this->assertSame(1_500, AccountWallet::query()->sole()->balance);
        $this->assertSame(
            6_200,
            (int) $this->game()->table('Character')->where('Name', 'PointHero')->value('LevelUpPoint'),
        );
    }

    /**
     * Persists enabled progression package pricing.
     */
    private function enableProgressionServices(): void
    {
        EconomySetting::factory()->create([
            'character_reset_purchase_enabled' => true,
            'character_reset_price_credits' => 100,
            'character_level_up_points_purchase_enabled' => true,
            'character_level_up_points_package_size' => 1_000,
            'character_level_up_points_price_credits' => 100,
        ]);
    }

    /**
     * Creates one Credits wallet for endpoint assertions.
     */
    private function wallet(string $accountId, int $balance): void
    {
        AccountWallet::factory()->create([
            'account_id' => $accountId,
            'currency' => WalletCurrency::Credits,
            'balance' => $balance,
        ]);
    }

    /**
     * Inserts one offline character with deterministic progression values.
     */
    private function insertCharacter(
        string $accountId,
        string $characterName,
        int $resets = 0,
        int $points = 0,
    ): void {
        $this->game()->table('MEMB_STAT')->insert([
            'memb___id' => $accountId,
            'ConnectStat' => 0,
        ]);
        $this->game()->table('Character')->insert([
            'AccountID' => $accountId,
            'Name' => $characterName,
            'Class' => 17,
            'cLevel' => 400,
            'LevelUpPoint' => $points,
            'Experience' => 1_000,
            'Strength' => 100,
            'Dexterity' => 100,
            'Vitality' => 100,
            'Energy' => 100,
            'Money' => 5_000_000,
            'MapNumber' => 0,
            'Resets' => $resets,
            'GrandResets' => 0,
        ]);
    }

    /**
     * Returns the isolated game database connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }

    /**
     * Creates one authenticated legacy account identity.
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
}
