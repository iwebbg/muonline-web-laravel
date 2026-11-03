<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Models\AccountWallet;
use App\Models\ModuleProfile;
use App\Models\WebStorageItem;
use App\Modules\ServerModule;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies that private character data remains scoped to the authenticated account.
 */
final class AccountDashboardTest extends TestCase
{
    use LazilyRefreshDatabase;

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
        $response->assertSee('item-storage', false);
        $response->assertSee('x-if="itemStorage !== null', false);
        $response->assertSee('Account tools');
        $response->assertSee(route('account.votes.index'), false);
        $response->assertSee(route('account.security.index'), false);
        $response->assertSee(route('account.credits.index'), false);
        $response->assertSee('x-teleport="body"', false);
        $response->assertSee('aria-modal="true"', false);
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
        $response->assertSee('Account storage');
        $this->assertMatchesRegularExpression(
            '/initialSection.{0,80}vaults/',
            $response->getContent(),
        );
    }

    /**
     * Confirms disabled account modules disappear from the internal account menu.
     */
    public function test_dashboard_hides_disabled_modules_from_account_navigation(): void
    {
        $modules = [];

        foreach (ServerModule::cases() as $module) {
            $modules[$module->value] = true;
        }

        $modules[ServerModule::VoteRewards->value] = false;
        $modules[ServerModule::Credits->value] = false;

        ModuleProfile::query()->create([
            'profile_key' => 'darksteam_97d99i',
            'game_version' => '97d + 99i',
            'modules' => $modules,
            'revision' => 1,
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response->assertOk();
        $response->assertDontSee(route('account.votes.index'), false);
        $response->assertDontSee(route('account.credits.index'), false);
    }

    /**
     * Confirms the signed-in summary includes only the owned Credits balance.
     */
    public function test_dashboard_displays_owned_wallet_balance_in_signed_in_summary(): void
    {
        AccountWallet::factory()->create([
            'account_id' => 'owner',
            'balance' => 125,
        ]);
        AccountWallet::factory()->create([
            'account_id' => 'other',
            'balance' => 999,
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account');

        $response
            ->assertOk()
            ->assertSee('125 Credits')
            ->assertDontSee('999 Credits');
    }

    /**
     * Confirms available web-vault items are managed from the unified inventory panel.
     */
    public function test_dashboard_includes_owned_web_storage_items_and_item_actions(): void
    {
        $gameConnection = $this->app->make(DatabaseManager::class)->connection('game');

        $gameConnection->table('MEMB_STAT')->insert([
            'memb___id' => 'owner',
            'ConnectStat' => 0,
        ]);
        $gameConnection->table('Character')->insert(
            $this->characterRecord('owner', 'OwnedKnight'),
        );
        $storedItem = WebStorageItem::factory()->create([
            'account_id' => 'owner',
            'item_snapshot' => [
                ...WebStorageItem::factory()->make()->item_snapshot,
                'name' => 'Excellent Unified Sword',
                'excellentMask' => 1,
                'excellentType' => 0,
            ],
        ]);
        WebStorageItem::factory()->create([
            'account_id' => 'other',
            'item_snapshot' => [
                ...WebStorageItem::factory()->make()->item_snapshot,
                'name' => 'Hidden Web Item',
            ],
        ]);
        WebStorageItem::factory()->jewelBank()->create([
            'account_id' => 'owner',
            'item_snapshot' => [
                ...WebStorageItem::factory()->make()->item_snapshot,
                'name' => 'Hidden Jewel Bank Item',
            ],
        ]);

        $response = $this
            ->actingAs($this->account('owner'))
            ->get('/account?character=OwnedKnight&tab=vaults');

        $response
            ->assertOk()
            ->assertSee('Excellent Unified Sword')
            ->assertSee($storedItem->public_id)
            ->assertSee('marketListingStoreUrl')
            ->assertSee('Main Vault')
            ->assertSee('Account storage')
            ->assertDontSee('Hidden Web Item')
            ->assertDontSee('Hidden Jewel Bank Item');
        $this->assertMatchesRegularExpression(
            '/initialSection.{0,80}vaults/',
            $response->getContent(),
        );
        $this->assertMatchesRegularExpression(
            '/initialCharacter.{0,80}OwnedKnight/',
            $response->getContent(),
        );
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
