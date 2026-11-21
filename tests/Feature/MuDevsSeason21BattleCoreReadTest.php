<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21BattleCoreReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Prevents Battle Core account reads from leaking registrations or rewards across realms.
 */
final class MuDevsSeason21BattleCoreReadTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables only the native read projection against disposable SQLite connections.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.battle_core.reads_enabled', true);
        config()->set('game.battle_core.server_codes', [19, 20]);
        $this->battleCore()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-battle-core-read-schema.sql')));
    }

    /**
     * Counts unclaimed items only for the exact account in its configured source realms.
     */
    public function test_native_reads_are_scoped_by_account_and_server_code(): void
    {
        $this->seedRegistrations();
        $this->seedRewards();
        $connection = $this->battleCore();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $snapshot = $this->repository()->forAccount('owner');

        self::assertTrue($snapshot->available);
        self::assertSame(['OwnHero', 'Unknown'], array_column($snapshot->registrations, 'name'));
        self::assertSame([true, null], array_column($snapshot->registrations, 'registered'));
        self::assertSame(15, $snapshot->registrations[0]['day']);
        self::assertSame(7, $snapshot->unclaimedRewardItems);
        foreach ($connection->getQueryLog() as $query) {
            self::assertStringStartsWith('select', strtolower($query['query']));
        }
    }

    /**
     * Returns no data and opens no game query when the optional reader is disabled.
     */
    public function test_disabled_reads_do_not_query_the_battle_realm(): void
    {
        config()->set('game.battle_core.reads_enabled', false);
        $this->battleCore()->enableQueryLog();
        $this->battleCore()->flushQueryLog();

        self::assertNull($this->repository()->forAccount('owner'));
        self::assertSame([], $this->battleCore()->getQueryLog());
    }

    /**
     * Fails closed instead of reading every server when no realm boundary is configured.
     */
    public function test_empty_server_allow_list_does_not_expand_to_all_realms(): void
    {
        config()->set('game.battle_core.server_codes', []);
        $this->seedRegistrations();

        $snapshot = $this->repository()->forAccount('owner');

        self::assertFalse($snapshot->available);
        self::assertSame([], $snapshot->registrations);
    }

    /**
     * Keeps the account page usable when the optional Battle Core source is unavailable.
     */
    public function test_missing_battle_source_returns_an_unavailable_snapshot(): void
    {
        $this->battleCore()->statement('DROP TABLE BattleCoreReward');

        $snapshot = $this->repository()->forAccount('owner');

        self::assertFalse($snapshot->available);
        self::assertSame(0, $snapshot->unclaimedRewardItems);
    }

    /**
     * Displays only the authenticated account's registrations and safely escapes stored names.
     */
    public function test_private_dashboard_displays_owned_battle_core_data_without_other_realms(): void
    {
        $this->selectNativeProfile();
        $this->seedRegistrations();
        $this->seedRewards();
        $this->battleCore()->table('BattleCoreUser')->where('UniqueKey', 2)->update(['Name' => '<b>x</b>']);

        $this->actingAs(new GameAccount(1, 'owner', 'Owner', null, false, false, ''))
            ->get('/account')->assertOk()
            ->assertSee('Inter-server participation')->assertSee('OwnHero')
            ->assertSee('&lt;b&gt;x&lt;/b&gt;', false)->assertDontSee('<b>x</b>', false)
            ->assertDontSee('OtherRealm')->assertDontSee('PrivateAlt');
        self::assertInstanceOf(SqlServerMuDevsSeason21BattleCoreReadRepository::class, $this->app->make(BattleCoreReadRepository::class));
    }

    /**
     * Keeps the new reader absent from legacy profiles even when its optional switch is enabled.
     */
    public function test_legacy_profile_has_no_native_battle_core_binding(): void
    {
        self::assertFalse($this->app->bound(BattleCoreReadRepository::class));
    }

    /**
     * Seeds colliding account names and unknown states across source realms.
     */
    private function seedRegistrations(): void
    {
        $this->battleCore()->table('BattleCoreUser')->insert([
            ['UniqueKey' => 1, 'AccountID' => 'owner', 'Name' => 'OwnHero', 'ServerCode' => 20, 'RegisterState' => 1, 'RegisterMonth' => 11, 'RegisterDay' => 15],
            ['UniqueKey' => 2, 'AccountID' => 'owner', 'Name' => 'Unknown', 'ServerCode' => 19, 'RegisterState' => 7, 'RegisterMonth' => 11, 'RegisterDay' => 14],
            ['UniqueKey' => 3, 'AccountID' => 'owner', 'Name' => 'OtherRealm', 'ServerCode' => 99, 'RegisterState' => 1, 'RegisterMonth' => 11, 'RegisterDay' => 15],
            ['UniqueKey' => 4, 'AccountID' => 'other', 'Name' => 'PrivateAlt', 'ServerCode' => 20, 'RegisterState' => 1, 'RegisterMonth' => 11, 'RegisterDay' => 15],
        ]);
    }

    /**
     * Separates pending rewards from already claimed, foreign-account and foreign-realm records.
     */
    private function seedRewards(): void
    {
        $this->battleCore()->table('BattleCoreReward')->insert([
            ['id' => 1, 'AccountID' => 'owner', 'RealName' => 'OwnHero', 'ServerCode' => 20, 'ItemCount' => 7, 'TookState' => 0],
            ['id' => 2, 'AccountID' => 'owner', 'RealName' => 'OwnHero', 'ServerCode' => 20, 'ItemCount' => 10, 'TookState' => 1],
            ['id' => 3, 'AccountID' => 'owner', 'RealName' => 'OtherRealm', 'ServerCode' => 99, 'ItemCount' => 80, 'TookState' => 0],
            ['id' => 4, 'AccountID' => 'other', 'RealName' => 'PrivateAlt', 'ServerCode' => 20, 'ItemCount' => 90, 'TookState' => 0],
        ]);
    }

    /**
     * Selects the actual profile bindings and owned dashboard schema for the HTTP test.
     */
    private function selectNativeProfile(): void
    {
        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Resolves the native read adapter independently of profile availability.
     */
    private function repository(): SqlServerMuDevsSeason21BattleCoreReadRepository
    {
        return $this->app->make(SqlServerMuDevsSeason21BattleCoreReadRepository::class);
    }

    /**
     * Returns only the isolated Battle Core projection installed for this test.
     */
    private function battleCore(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('battle_core');
    }
}
