<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\Game\Contracts\AccountMembershipReadRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Repositories\SqlServerMuDevsSeason21AccountMembershipReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Verifies private S21 membership expiry without changing native account state.
 */
final class MuDevsSeason21AccountMembershipTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Installs the sanitized native schema for the account membership boundary.
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Displays only the signed-in account's native tier and expiry on its private dashboard.
     */
    public function test_dashboard_reads_owned_native_membership_without_exposing_foreign_expiry(): void
    {
        $this->selectNativeProfile();
        $this->seedMembership('owner', 2, '2070-01-01 12:30:00');
        $this->seedMembership('other', 3, '2071-02-02 15:00:00');

        $this->actingAs(new GameAccount(1, 'owner', 'Owner', null, false, false, ''))
            ->get('/account')->assertOk()
            ->assertSee('Game account membership')
            ->assertSee('VIP tier 2')
            ->assertSee('2070-01-01 12:30:00')
            ->assertDontSee('2071-02-02 15:00:00')
            ->assertDontSee('VIP tier 3');
    }

    /**
     * Evaluates membership using the database clock while retaining stored expired values.
     */
    #[DataProvider('membershipStates')]
    public function test_expiry_projection_never_writes_the_native_account(?int $level, ?string $expiry, ?int $effective, bool $expired): void
    {
        $this->seedMembership('owner', $level, $expiry);
        $connection = $this->game();
        $connection->enableQueryLog();
        $connection->flushQueryLog();

        $snapshot = $this->app->make(SqlServerMuDevsSeason21AccountMembershipReadRepository::class)->forAccount('owner');

        self::assertSame($level, $snapshot->storedLevel);
        self::assertSame($effective, $snapshot->effectiveLevel);
        self::assertSame($expiry, $snapshot->expiresAt);
        self::assertSame($expired, $snapshot->expired);
        foreach ($connection->getQueryLog() as $query) {
            self::assertStringStartsWith('select', strtolower($query['query']));
        }
        self::assertSame($level, $connection->table('MEMB_INFO')->value('AccountLevel'));
    }

    /**
     * Covers standard, active, expired and incomplete native membership states.
     *
     * @return iterable<string, array{int|null, string|null, int|null, bool}>
     */
    public static function membershipStates(): iterable
    {
        yield 'standard with native sentinel' => [0, '1900-01-01 00:00:00', 0, false];
        yield 'active VIP' => [3, '2070-01-01 12:30:00', 3, false];
        yield 'expired VIP' => [2, '2000-01-01 12:30:00', 0, true];
        yield 'missing tier' => [null, '2070-01-01 12:30:00', null, false];
        yield 'missing expiry' => [1, null, null, false];
        yield 'invalid negative tier' => [-1, '2070-01-01 12:30:00', null, false];
    }

    /**
     * Treats SQL-shaped identifiers as literal account names rather than expanding ownership.
     */
    public function test_missing_or_sql_shaped_account_cannot_read_another_membership(): void
    {
        $this->seedMembership('other', 3, '2070-01-01 12:30:00');
        $repository = $this->app->make(SqlServerMuDevsSeason21AccountMembershipReadRepository::class);

        self::assertNull($repository->forAccount('missing'));
        self::assertNull($repository->forAccount("' OR 1=1--"));
    }

    /**
     * Keeps native membership reads absent from the existing default adapter.
     */
    public function test_legacy_profile_does_not_bind_native_membership(): void
    {
        self::assertFalse($this->app->bound(AccountMembershipReadRepository::class));
    }

    /**
     * Selects the real native profile registration for the HTTP boundary.
     */
    private function selectNativeProfile(): void
    {
        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
    }

    /**
     * Seeds a native account directly because external game accounts have no Eloquent model.
     */
    private function seedMembership(string $account, ?int $level, ?string $expiry): void
    {
        $this->game()->table('MEMB_INFO')->insert([
            'memb_guid' => 1, 'memb___id' => $account,
            'AccountLevel' => $level, 'AccountExpireDate' => $expiry,
        ]);
    }

    /**
     * Returns the disposable game connection without accessing the configured LAN server.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
