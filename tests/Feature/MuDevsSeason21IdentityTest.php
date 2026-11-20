<?php

namespace Tests\Feature;

use App\Auth\GameAccount;
use App\CharacterServices\Actions\ExecuteCharacterService;
use App\CharacterServices\CharacterServiceBlockReason;
use App\CharacterServices\Exceptions\CharacterServiceRejected;
use App\Economy\WalletCurrency;
use App\Game\Contracts\BattleCoreReadRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Data\BattleCoreAccountSnapshot;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Models\AccountWallet;
use App\Models\CharacterServiceOperation;
use App\Models\EconomySetting;
use App\Models\WalletTransaction;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Database\Connection;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Facades\Exceptions;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * Verifies native identity purchases preserve stable ownership and recover committed results exactly once.
 */
final class MuDevsSeason21IdentityTest extends TestCase
{
    use LazilyRefreshDatabase;

    private const KEY = '2023557d-9858-490d-a414-a00cfeea9554';

    /**
     * Selects native bindings and isolated game schema without connecting to the running server.
     */
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        config()->set('game.battle_core.server_codes', [20]);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $this->game()->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Renames all ten slots and indexed caches while retaining secondary owners and other realm copies.
     */
    public function test_rename_updates_owned_caches_and_social_references_without_moving_other_identities(): void
    {
        $this->seedIdentity();
        $slots = ['Id' => 'owner', 'AccountIndex' => 1, 'ExtWarehouse' => 0, 'GameIDC' => 'Hero'];
        for ($slot = 1; $slot <= 10; $slot++) {
            $slots['GameID'.$slot] = 'Hero';
            $slots['CharacterIndex_'.$slot] = 42;
        }
        $this->game()->table('AccountCharacter')->insert($slots);
        $this->game()->table('MasterSkillTree')->insert(['Name' => 'Stale', 'CharacterIndex' => 42, 'MasterPoint' => 70]);
        $this->game()->table('MasterSkillTree')->insert(['Name' => 'Other', 'CharacterIndex' => 99, 'MasterPoint' => 80]);
        $this->game()->table('Guild')->insert(['G_Name' => 'Guild', 'G_Master' => 'Hero']);
        $this->game()->table('BattleCoreUser')->insert(['Name' => 'Hero', 'ServerCode' => 99]);
        $payload = ['character_name' => 'Hero', 'new_name' => 'NewHero', 'idempotency_key' => self::KEY];

        $this->actingAs($this->account())->post(route('account.character-rename'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('account.character-rename'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        self::assertSame('NewHero', $this->game()->table('Character')->where('CharacterIndex', 42)->value('Name'));
        self::assertSame('NewHero', $this->game()->table('MasterSkillTree')->where('CharacterIndex', 42)->value('Name'));
        self::assertSame('Other', $this->game()->table('MasterSkillTree')->where('CharacterIndex', 99)->value('Name'));
        self::assertSame('NewHero', $this->game()->table('Guild')->value('G_Master'));
        self::assertSame('Hero', $this->game()->table('BattleCoreUser')->value('Name'));
        self::assertSame('NewHero', $this->game()->table('AccountCharacter')->value('GameID10'));
        self::assertSame('NewHero', $this->game()->table('AccountCharacter')->value('GameIDC'));
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
    }

    /**
     * Retains fifth advancement and all progression while clearing ordinary skills and updating party presentation.
     */
    public function test_class_change_preserves_fifth_advancement_and_progression(): void
    {
        $this->seedIdentity();
        $this->game()->table('PartyRenewalMemberList')->insert(['Name' => 'Hero', 'Class' => 20]);
        $this->game()->table('OptionData')->insert(['CharacterIndex' => 42, 'Name' => 'Hero', 'ChangeSkin' => 123]);
        $before = $this->game()->table('Character')->first();
        $payload = ['character_name' => 'Hero', 'target_family' => 'wizard', 'idempotency_key' => self::KEY];

        $this->actingAs($this->account())->post(route('account.character-class-change'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->post(route('account.character-class-change'), $payload)->assertRedirect()->assertSessionHasNoErrors();

        $after = $this->game()->table('Character')->first();
        self::assertSame(4, $after->Class);
        self::assertSame(str_repeat("\xFF", 246), $after->MagicList);
        foreach (['CharacterIndex', 'cLevel', 'Strength', 'ResetCount', 'LevelUpPoint', 'Quest', 'Inventory'] as $column) {
            self::assertSame($before->{$column}, $after->{$column}, $column);
        }
        self::assertSame(4, $this->game()->table('PartyRenewalMemberList')->value('Class'));
        self::assertSame(0, $this->game()->table('OptionData')->value('ChangeSkin'));
        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
    }

    /**
     * Rejects unsafe native class snapshots with a durable rejection and exactly one refund.
     */
    #[DataProvider('unsafeSnapshots')]
    public function test_class_change_rejects_unsafe_snapshots(string $table, string $column, mixed $value, CharacterServiceBlockReason $reason): void
    {
        $this->seedIdentity();
        if ($table === 'Character' || $table === 'MEMB_STAT') {
            $this->game()->table($table)->update([$column => $value]);
        } elseif ($table === 'RestoreOffline') {
            $this->game()->table($table)->insert(['Account' => 'owner', 'Name' => 'Hero', 'Status' => $value]);
        } else {
            $this->game()->table($table)->insert(['CharacterIndex' => 42, 'Name' => 'Hero', $column => $value]);
        }
        try {
            $this->service()->changeClass('owner', 'Hero', 'wizard', self::KEY);
            self::fail('The unsafe class change must be rejected.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame($reason, $exception->reason);
        }
        self::assertSame(20, $this->game()->table('Character')->value('Class'));
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
        self::assertSame(2, WalletTransaction::query()->count());
        self::assertSame(1, $this->game()->table('IWEB_S21_CharacterOperation')->count());
    }

    /**
     * Enumerates equipped, allocated skill and server activity blockers.
     *
     * @return iterable<string, array{string, string, mixed, CharacterServiceBlockReason}>
     */
    public static function unsafeSnapshots(): iterable
    {
        yield 'equipment' => ['Character', 'Inventory', "\x01".str_repeat("\xFF", 5974), CharacterServiceBlockReason::EquippedItems];
        yield 'master skills' => ['MasterSkillTree', 'MasterSkill', "\x2C\x01\x01", CharacterServiceBlockReason::AllocatedSkills];
        yield 'enhancement skills' => ['EnhanceSkillTree', 'EnhanceSkillPassive', "\x2C\x01", CharacterServiceBlockReason::AllocatedSkills];
        yield 'ability cores' => ['AbilityEnhancementCore', 'Inventory', "\x01", CharacterServiceBlockReason::AllocatedSkills];
        yield 'connected' => ['MEMB_STAT', 'ConnectStat', 1, CharacterServiceBlockReason::CharacterOnline];
        yield 'unknown offline activity' => ['RestoreOffline', 'Status', null, CharacterServiceBlockReason::CharacterOnline];
        yield 'offline activity' => ['RestoreOffline', 'Status', 1, CharacterServiceBlockReason::CharacterOnline];
    }

    /**
     * Resolves a rename committed before a response loss using its old name and original purchase key.
     */
    public function test_interrupted_rename_resumes_even_after_old_name_disappears(): void
    {
        $this->seedIdentity();
        Exceptions::fake();
        $interrupt = true;
        $this->app['events']->listen(TransactionCommitted::class, function (TransactionCommitted $event) use (&$interrupt): void {
            if ($event->connectionName === 'game' && $event->connection->transactionLevel() === 0 && $interrupt) {
                $interrupt = false;
                throw new RuntimeException('Response lost after rename commit.');
            }
        });
        try {
            $this->service()->rename('owner', 'Hero', 'NewHero', self::KEY);
            self::fail('A lost response must remain recoverable.');
        } catch (CharacterServiceRejected $exception) {
            self::assertSame(CharacterServiceBlockReason::OperationInProgress, $exception->reason);
        }
        $this->actingAs($this->account())->get('/account')->assertOk()->assertSee('NewHero')->assertSee(self::KEY);

        $this->service()->rename('owner', 'Hero', 'NewHero', self::KEY);

        self::assertSame(900, AccountWallet::query()->sole()->balance);
        self::assertSame(1, WalletTransaction::query()->count());
        self::assertSame('completed', CharacterServiceOperation::query()->sole()->status->value);
        Exceptions::assertReported(RuntimeException::class);
    }

    /**
     * Blocks identity writes when private Battle Core participation cannot be verified.
     */
    public function test_unavailable_battle_core_rejects_identity_change(): void
    {
        $this->seedIdentity(false);
        $this->actingAs($this->account())->post(route('account.character-rename'), [
            'character_name' => 'Hero', 'new_name' => 'NewHero', 'idempotency_key' => self::KEY,
        ])->assertRedirect()->assertSessionHasErrors();
        self::assertSame('Hero', $this->game()->table('Character')->value('Name'));
        self::assertSame(1000, AccountWallet::query()->sole()->balance);
    }

    /**
     * Creates the exact paid policy and owned offline identity required by each test.
     */
    private function seedIdentity(bool $battleCoreAvailable = true): void
    {
        $battle = $this->createStub(BattleCoreReadRepository::class);
        $battle->method('forAccount')->willReturn(new BattleCoreAccountSnapshot($battleCoreAvailable, [], 0));
        $this->app->instance(BattleCoreReadRepository::class, $battle);
        EconomySetting::factory()->create([
            'character_rename_enabled' => true, 'character_rename_price_credits' => 100,
            'character_class_change_enabled' => true, 'character_class_change_price_credits' => 100,
        ]);
        AccountWallet::factory()->create(['account_id' => 'owner', 'currency' => WalletCurrency::Credits, 'balance' => 1000]);
        $this->game()->table('MEMB_INFO')->insert(['memb_guid' => 1, 'memb___id' => 'owner', 'AccountLevel' => 0]);
        $this->game()->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $this->game()->table('Character')->insert([
            'AccountID' => 'owner', 'Name' => 'Hero', 'CharacterIndex' => 42, 'Class' => 20,
            'cLevel' => 1500, 'ResetCount' => 2, 'LevelUpPoint' => 1200, 'Strength' => 32000,
            'Inventory' => str_repeat("\xFF", 5975), 'Quest' => str_repeat("\xAB", 50), 'MagicList' => str_repeat("\xCD", 246),
        ]);
    }

    /**
     * Returns the native purchase coordinator.
     */
    private function service(): ExecuteCharacterService
    {
        return $this->app->make(ExecuteCharacterService::class);
    }

    /**
     * Returns one authenticated owned account.
     */
    private function account(): GameAccount
    {
        return new GameAccount(1, 'owner', 'Owner', null, false, false, '');
    }

    /**
     * Returns the isolated game connection.
     */
    private function game(): Connection
    {
        return $this->app->make(DatabaseManager::class)->connection('game');
    }
}
