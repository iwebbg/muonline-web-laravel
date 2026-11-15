<?php

namespace Tests\Feature;

use App\Game\Contracts\ItemCatalog;
use App\Game\Contracts\ItemCodec;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\RealmReadRepository;
use App\Game\Versions\Contracts\GameVersionProfile;
use App\Game\Versions\MuDevsSeason21\ItemGrantRulesMuDevsSeason21;
use App\ItemGrants\Actions\GrantItemToWarehouse;
use App\ItemGrants\Exceptions\ItemGrantRejected;
use App\ItemGrants\ItemGrantBlockReason;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use App\Modules\ModuleManager;
use App\Providers\AppServiceProvider;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Verifies native administrator grants and source-backed construction limits.
 */
final class MuDevsSeason21ItemGrantTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Installs only disposable native projections and selects their profile bindings.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.profile.key', 'mudevs_season_21');
        config()->set('game.data_source', 'database');
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
        $this->app->forgetInstance(GameVersionProfile::class);
        $this->app->forgetInstance(ModuleManager::class);
        $this->app->forgetInstance(RealmReadRepository::class);
        (new AppServiceProvider($this->app))->register();
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));
        $game->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-web-columns.sql')));
    }

    /**
     * Delivers one native high-index item and reuses the completed operation on replay.
     */
    public function test_high_index_administrator_grant_preserves_native_bytes_and_reserves_one_serial(): void
    {
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $empty = str_repeat("\xFF", 6000);
        $game->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $empty]);
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(0x01020309);
        $administrator = Administrator::factory()->create();
        $options = $this->itemOptions(['group' => 7, 'index' => 270]);
        $key = (string) Str::uuid();
        $action = $this->app->make(GrantItemToWarehouse::class);

        $token = 'native-grant-token';
        $this->withSession(['_token' => $token])->actingAs($administrator, 'admin')->post(route('admin.item-grants.store'), [
            ...$options, '_token' => $token, 'account_id' => 'owner', 'item_code' => '7:270',
            'reason' => 'Native commissioning', 'idempotency_key' => $key,
        ])->assertRedirect()->assertSessionHasNoErrors();
        $result = $action->execute($administrator, 'owner', $options, 'Native commissioning', $key);
        $replay = $action->execute($administrator, 'owner', $options, 'Native commissioning', $key);

        self::assertSame($result->operationId, $replay->operationId);
        $expected = hex2bin('0E00FF0102030980007000FFFFFFFFFFFFFFFFFFFFFFFFFFFF');
        self::assertSame(substr_replace($empty, $expected, 0, 25), $game->table('warehouse')->value('Items'));
        self::assertSame(1, ItemGrantOperation::query()->count());
        self::assertSame(1, $game->table('IWEB_S21_ItemOperation')->count());
    }

    /**
     * Rejects unsupported special items before serial allocation or audit creation.
     */
    public function test_special_item_grant_does_not_allocate_a_serial(): void
    {
        $this->mock(ItemSerialRepository::class)->shouldNotReceive('nextSerial');

        try {
            $this->app->make(GrantItemToWarehouse::class)->execute(
                Administrator::factory()->create(), 'owner', $this->itemOptions(['group' => 12, 'index' => 0]),
                'Unsupported wings', (string) Str::uuid(),
            );
            self::fail('Special-item grants require their own persistence adapter.');
        } catch (ItemGrantRejected $exception) {
            self::assertSame(ItemGrantBlockReason::UnknownItem, $exception->reason);
            self::assertSame(0, ItemGrantOperation::query()->count());
        }
    }

    /**
     * Uses the source option declarations rather than inheriting S6 class or skill assumptions.
     */
    public function test_native_construction_rejects_kris_skill_and_accepts_supported_options(): void
    {
        $rules = $this->app->make(ItemGrantRulesMuDevsSeason21::class);

        self::assertFalse($rules->allowsConstruction($this->itemOptions(['has_skill' => true])));
        self::assertTrue($rules->allowsConstruction($this->itemOptions(['has_luck' => true, 'excellent_mask' => 63, 'option_level' => 7, 'level' => 15])));
        self::assertTrue($rules->allowsConstruction($this->itemOptions(['group' => 14, 'index' => 22, 'durability' => 1])));
        self::assertFalse($rules->allowsConstruction($this->itemOptions(['group' => 14, 'index' => 22, 'durability' => 51])));
        self::assertFalse($rules->allowsConstruction($this->itemOptions(['has_380_option' => true])));
    }

    /**
     * Keeps native armor values and shield additional options independent of legacy labels.
     */
    public function test_native_editor_and_tooltip_use_the_exact_item_option_source(): void
    {
        $catalog = $this->app->make(ItemCatalog::class);
        $armor = $catalog->definitionFor(8, 21)->nativeOptions;
        $shield = $catalog->definitionFor(6, 0)->nativeOptions;
        $codec = $this->app->make(ItemCodec::class);
        $binary = $codec->create(8, 21, 0, 255, 1, false, false, 1, 0, 123);
        $item = $codec->decode($binary, 1)[0];

        self::assertFalse($armor['skill']);
        self::assertSame(5, $shield['additionalValue']);
        self::assertStringContainsString('30%', $armor['excellent'][0]['label']);
        self::assertContains($armor['excellent'][0]['label'], array_column($item->nativePresentation['details'], 'value'));
        self::assertContains('Additional defense +4', array_column($item->nativePresentation['details'], 'value'));
    }

    /**
     * Supplies a canonical basic item request with focused overrides.
     *
     * @param  array<string, int|bool>  $overrides
     * @return array<string, int|bool>
     */
    private function itemOptions(array $overrides = []): array
    {
        return array_replace([
            'group' => 0, 'index' => 0, 'level' => 0, 'durability' => 255,
            'option_level' => 0, 'has_skill' => false, 'has_luck' => false,
            'excellent_mask' => 0, 'ancient_option' => 0,
        ], $overrides);
    }
}
