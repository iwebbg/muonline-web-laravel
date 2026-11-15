<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Auth\GameAccount;
use App\Game\Clocks\SystemRealmClock;
use App\Game\Contracts\RealmClock;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\Administrator;
use App\Models\EventScheduleEntry;
use App\Models\EventScheduleEntryRevision;
use App\Models\ModuleProfile;
use App\Modules\ModuleManager;
use App\Modules\ServerModule;
use App\Providers\AppServiceProvider;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Exercises the selectable S21 read bundle through real website requests.
 */
final class MuDevsSeason21WebsiteTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Selects native adapters against disposable SQLite projections only.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.profile.version', 'S21.1-2');
        config()->set('game.event_profile', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);

        (new AppServiceProvider($this->app))->register();

        $this->app->bind(RealmClock::class, SystemRealmClock::class);
        $this->app->make(DatabaseManager::class)->connection('game')->unprepared(
            file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')),
        );
        $this->app->make(DatabaseManager::class)->connection('game')->unprepared(
            file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')),
        );
    }

    /**
     * Displays Crusader with native stats while hiding another account's character.
     */
    public function test_account_displays_native_class_240_without_s6_stat_mapping(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert([
            $this->character('owner', 'Crusader', 1, 240),
            $this->character('other', 'Hidden', 2, 0),
            $this->character('owner', 'OldKnight', 3, 18),
        ]);

        $response = $this->actingAs($this->account())->get('/account?tab=inventory');

        $response->assertOk()
            ->assertSee('Crusader')
            ->assertSee('OldKnight')
            ->assertSee('Blade Master')
            ->assertDontSee('Hidden')
            ->assertSee("selectSection('inventory')", false)
            ->assertSee("selectSection('reset')", false)
            ->assertViewHas('readOnlyGameProfile', false);
        $this->assertSame('inventory', $response->viewData('accountPanelConfiguration')['initialSection']);
        $this->assertSame(240, $connection->table('Character')->where('Name', 'Crusader')->value('Class'));
    }

    /**
     * Displays the empty-account state without requesting an unsupported vault reader.
     */
    public function test_empty_account_stays_on_the_supported_overview(): void
    {
        $response = $this->actingAs($this->account())->get('/account');

        $response->assertOk()->assertSee(__('ui.account.no_characters_heading'));
        $this->assertSame('vaults', $response->viewData('accountPanelConfiguration')['initialSection']);
    }

    /**
     * Keeps authentication required for the supported private dashboard.
     */
    public function test_guest_cannot_read_the_account_dashboard(): void
    {
        $this->get('/account')->assertRedirect(route('login'));
    }

    /**
     * Authenticates the native account schema without selecting S6 VIP columns.
     */
    public function test_native_login_restores_the_s21_account_session(): void
    {
        $this->app->make(DatabaseManager::class)->connection('game')->table('MEMB_INFO')->insert([
            'memb_guid' => 1,
            'memb___id' => 'owner',
            'memb__pwd' => 'test-pass',
            'memb_name' => 'Owner',
            'bloc_code' => '0',
        ]);
        $token = 'native-login-token';

        $this->withSession(['_token' => $token])->post(route('login.store'), [
            '_token' => $token,
            'username' => 'owner',
            'password' => 'test-pass',
        ])->assertRedirect('/account');

        $this->get('/account')->assertOk();
        $this->assertAuthenticated();
    }

    /**
     * Shows only owned native coins and character Ruud without creating a web wallet.
     */
    public function test_native_currencies_remain_private_and_separate_from_web_credits(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert([
            [...$this->character('owner', 'Crusader', 1, 240), 'RuudMoney' => 81],
            [...$this->character('other', 'Hidden', 2, 0), 'RuudMoney' => 999],
        ]);
        $connection->table('CashShopData')->insert([
            ['AccountID' => 'owner', 'WCoinC' => 123, 'WCoinP' => 45, 'GoblinPoint' => 67],
            ['AccountID' => 'other', 'WCoinC' => 999, 'WCoinP' => 999, 'GoblinPoint' => 999],
        ]);
        $connection->table('LuckyCoin')->insert(['AccountID' => 'owner', 'LuckyCoin' => 9]);

        $response = $this->actingAs($this->account())->get('/account');

        $response->assertOk()->assertSee('WCoin(C)')->assertSee('WCoin(P)')->assertSee('GoblinPoint');
        $this->assertSame([
            'wCoinC' => 123, 'wCoinP' => 45, 'goblinPoints' => 67,
            'luckyCoins' => 9, 'ruudByCharacter' => ['Crusader' => 81],
        ], $response->viewData('nativeCurrencyBalances')->toArray());
        $this->assertSame(81, $response->viewData('characters')[0]['ruud']);
        $this->assertDatabaseCount('account_wallets', 0);
    }

    /**
     * Publishes S21 events and native ranking rows through the main website.
     */
    public function test_home_and_rankings_use_native_s21_reads_and_event_profile(): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert($this->character('owner', 'Crusader', 1, 240));

        $this->get('/')->assertOk()->assertSee('Crusader')->assertSee('Group Boss Battle')
            ->assertSee('Imperial Guardian Renewal')->assertSee('Labyrinth of Dimensions')->assertSee('Campaign ended');
        $this->get('/rankings')->assertOk()->assertSee('Crusader');
    }

    /**
     * Restores supported account menus without exposing unfinished economy modules.
     */
    public function test_supported_account_pages_and_registration_are_available(): void
    {
        $this->get('/register')->assertOk();
        $this->get('/forgot-password')->assertOk();
        $this->actingAs($this->account());
        $this->get('/account/security')->assertOk();
        $this->get('/account/zen-bank')->assertOk();
        $this->get('/account')->assertOk()->assertSee(route('account.security.index'), false)
            ->assertSee(route('account.zen-bank.index'), false);
        $this->get('/market')->assertOk();
    }

    /**
     * Prevents persisted settings from activating an unfinished native adapter module.
     */
    public function test_persisted_module_settings_cannot_bypass_native_availability(): void
    {
        ModuleProfile::query()->create([
            'profile_key' => 'mudevs_season_21',
            'game_version' => 'S21.1-2',
            'modules' => ['market' => false, 'vip' => true, 'registration' => false, 'account_security' => true],
            'revision' => 1,
        ]);
        $this->app->make(ModuleManager::class)->forget();

        $this->assertFalse($this->app->make(ModuleManager::class)->enabled(ServerModule::Vip));
        $this->assertFalse($this->app->make(ModuleManager::class)->enabled(ServerModule::Market));
        $this->assertFalse($this->app->make(ModuleManager::class)->enabled(ServerModule::Registration));
        $this->assertTrue($this->app->make(ModuleManager::class)->enabled(ServerModule::AccountSecurity));
        $this->get('/market')->assertNotFound();
        $this->get('/register')->assertNotFound();
    }

    /**
     * Publishes Castle Siege from the native database cycle and configured battle phase.
     */
    public function test_castle_siege_uses_the_native_database_cycle(): void
    {
        $this->app->make(DatabaseManager::class)->connection('game')->table('MuCastle_DATA')->insert([
            'MAP_SVR_GROUP' => 0,
            'SIEGE_START_DATE' => '2026-11-01 00:00:00',
            'SIEGE_END_DATE' => '2026-11-08 00:00:00',
            'SIEGE_ENDED' => 0,
            'CASTLE_OCCUPY' => 1,
            'OWNER_GUILD' => 'Guard',
        ]);

        $this->get('/')->assertOk()->assertSee('Castle Siege');
    }

    /**
     * Prevents unfinished storage and mutation routes from resolving legacy services.
     */
    public function test_unimplemented_account_operations_are_unavailable_even_if_writes_are_enabled(): void
    {
        config()->set('game.writes_enabled', true);
        $this->actingAs($this->account());

        $this->getJson('/account/item-storage')->assertNotFound();
        $this->get('/shop')->assertNotFound();

        foreach (ServerModule::cases() as $module) {
            $this->assertSame(
                $module !== ServerModule::Vip,
                $this->app->make(ModuleManager::class)->enabled($module),
            );
        }
    }

    /**
     * Resolves each native character preview without writing game state.
     */
    #[DataProvider('nativePreviewRoutes')]
    public function test_native_character_preview_endpoints_are_available(string $path): void
    {
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert($this->character('owner', 'Crusader', 1, 244));
        $connection->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $before = $connection->table('Character')->first();
        $token = 's21-preview';

        $this->actingAs($this->account())->withSession(['_token' => $token])->postJson($path, [
            '_token' => $token, 'characterName' => 'Crusader', 'strength' => 1,
        ])->assertOk()->assertJsonPath('preview.characterName', 'Crusader');
        $this->assertEquals($before, $connection->table('Character')->first());
    }

    /**
     * Covers all currently implemented native core character previews.
     *
     * @return iterable<string, array{string}>
     */
    public static function nativePreviewRoutes(): iterable
    {
        foreach (['reset', 'grand-reset', 'stat-allocation', 'stat-reset', 'pk-clear', 'move-to-town'] as $operation) {
            yield $operation => ['/account/'.$operation.'-preview'];
        }
    }

    /**
     * Returns an explicit write-disabled result without changing the owned native character.
     */
    #[DataProvider('nativeMutationRoutes')]
    public function test_native_character_endpoint_respects_the_closed_write_switch(string $path): void
    {
        config()->set('game.writes_enabled', false);
        $connection = $this->app->make(DatabaseManager::class)->connection('game');
        $connection->table('Character')->insert($this->character('owner', 'Crusader', 1, 244));
        $before = $connection->table('Character')->first();
        $token = 's21-reset';

        $this->actingAs($this->account())->withSession(['_token' => $token])->postJson($path, [
            '_token' => $token, 'characterName' => 'Crusader', 'idempotencyKey' => 'd224d36c-3214-49f7-a5c0-04c315fe3d68', 'strength' => 1,
        ])->assertStatus(503)->assertJsonPath('reason', 'writes_disabled');
        $this->assertEquals($before, $connection->table('Character')->first());
        $this->assertDatabaseCount('character_reset_operations', 0);
    }

    /**
     * Covers each native core mutation endpoint while its global write switch stays closed.
     *
     * @return iterable<string, array{string}>
     */
    public static function nativeMutationRoutes(): iterable
    {
        foreach (['character-reset', 'character-grand-reset', 'stat-allocations', 'character-stat-reset', 'character-pk-clear', 'character-move'] as $operation) {
            yield $operation => ['/account/'.$operation];
        }
    }

    /**
     * Uses native stat bounds and exposes only inventory modes whose state is preserved.
     */
    public function test_native_character_rules_accept_65000_and_reject_destructive_inventory_modes(): void
    {
        $this->actingAs(Administrator::factory()->create(), 'admin');
        $this->get('/admin/character-rules/reset')->assertOk()
            ->assertViewHas('maximumStatLimit', 65000)
            ->assertSee('value="keep_all"', false)
            ->assertDontSee('value="clear_all"', false);
        $token = 's21-rule';
        $payload = [
            '_token' => $token, 'enabled' => true, 'requiredLevel' => 400,
            'maximumResets' => 999, 'maximumStat' => 65000, 'baseZenCost' => 0,
            'multiplyCostByReset' => false, 'clearStats' => true, 'pointsMode' => 'cumulative',
            'pointsPerReset' => 500, 'inventoryPolicy' => 'keep_all', 'resetRewardType' => 'none',
        ];

        $this->withSession(['_token' => $token])->putJson('/admin/character-rules/reset', $payload)
            ->assertOk()->assertJsonPath('settings.maximumStat', 65000);
        $this->putJson('/admin/character-rules/reset', [...$payload, 'inventoryPolicy' => 'clear_all'])
            ->assertUnprocessable()->assertJsonValidationErrors('inventoryPolicy');
        $this->putJson('/admin/character-rules/reset', [...$payload, 'maximumStat' => 65001])
            ->assertUnprocessable()->assertJsonValidationErrors('maximumStat');
        $this->assertDatabaseCount('reset_profile_revisions', 1);
        $this->get('/admin/moderation')->assertOk();
        $this->get('/admin/game-masters')->assertOk();
        $this->get('/admin/modules')->assertOk();
    }

    /**
     * Keeps event administration available with the existing permission boundary.
     */
    public function test_administrator_can_review_and_edit_s21_event_schedules(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')->get(route('admin.event-schedules.index'))
            ->assertOk()
            ->assertSee('Group Boss Battle')
            ->assertSee('href="'.route('admin.item-grants.index').'"', false)
            ->assertSee('href="'.route('admin.modules.index').'"', false);
        $this->get(route('admin.item-grants.index'))->assertOk();
    }

    /**
     * Persists a standard-event website override with its S21 revision and audit.
     */
    public function test_standard_event_override_is_saved_for_s21_only(): void
    {
        $administrator = Administrator::factory()->create();
        $token = 's21-event-token';

        $this->withSession(['_token' => $token])->actingAs($administrator, 'admin')
            ->post(route('admin.event-schedules.store'), [
                '_token' => $token,
                'event_key' => 'blood-castle',
                'name_en' => 'Blood Castle',
                'location_en' => 'Blood Castle',
                'accent' => 'crimson',
                'enabled' => '1',
                'duration_minutes' => 15,
                'schedule_mode' => 'times',
                'times' => '20:30',
                'days_of_week' => [],
                'sort_order' => 0,
            ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = EventScheduleEntry::query()->sole();
        $this->assertSame('mudevs_season_21', $entry->profile_key);
        $this->assertSame('S21.1-2', $entry->game_version);
        $this->assertSame(['20:30'], $entry->times);
        $this->assertSame('blood-castle', EventScheduleEntryRevision::query()->sole()->after_snapshot['event_key']);
    }

    /**
     * Retains settings permission checks after the native profile is activated.
     */
    public function test_content_only_staff_cannot_manage_event_schedules(): void
    {
        $staff = Administrator::factory()->gameMaster([AdministratorPermission::ManageContent])->create();

        $this->actingAs($staff, 'admin')->get(route('admin.event-schedules.index'))->assertForbidden();
    }

    /**
     * Builds a minimal authenticated identity without reading live credentials.
     */
    private function account(): GameAccount
    {
        return new GameAccount(1, 'owner', 'Owner', null, false, false, 'local-test');
    }

    /**
     * Supplies native character columns needed by private and public read models.
     *
     * @return array<string, int|string>
     */
    private function character(string $account, string $name, int $index, int $classCode): array
    {
        return [
            'AccountID' => $account,
            'Name' => $name,
            'CharacterIndex' => $index,
            'Class' => $classCode,
            'cLevel' => 400,
            'ResetCount' => 0,
            'MasterResetCount' => 0,
            'Money' => 1000,
            'LevelUpPoint' => 5,
            'Strength' => 25,
            'Dexterity' => 20,
            'Vitality' => 20,
            'Energy' => 15,
            'Leadership' => 0,
            'MapNumber' => 0,
            'PkCount' => 0,
            'PkLevel' => 3,
        ];
    }
}
