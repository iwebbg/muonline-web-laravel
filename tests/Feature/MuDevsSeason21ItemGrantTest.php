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
use PHPUnit\Framework\Attributes\DataProvider;
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
                Administrator::factory()->create(), 'owner', $this->itemOptions(['group' => 12, 'index' => 414]),
                'Unsupported fourth-generation wings', (string) Str::uuid(),
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
        self::assertFalse($rules->allowsConstruction($this->itemOptions(['group' => 12, 'index' => 40, 'excellent_mask' => 48])));
    }

    /**
     * Delivers source-declared standard wings through the actual administrator endpoint.
     */
    #[DataProvider('standardWings')]
    public function test_standard_wing_grants_keep_native_identity_and_options(int $group, int $index, int $excellentMask, string $additionalLabel): void
    {
        $game = $this->app->make(DatabaseManager::class)->connection('game');
        $game->table('MEMB_STAT')->insert(['memb___id' => 'owner', 'ConnectStat' => 0]);
        $game->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => str_repeat("\xFF", 6000)]);
        $this->mock(ItemSerialRepository::class)->shouldReceive('nextSerial')->once()->andReturn(61);
        $token = 'standard-wing-grant';

        $this->withSession(['_token' => $token])
            ->actingAs(Administrator::factory()->create(), 'admin')
            ->post(route('admin.item-grants.store'), [
                ...$this->itemOptions([
                    'level' => 15, 'option_level' => 7, 'has_luck' => true,
                    'excellent_mask' => $excellentMask,
                ]),
                '_token' => $token, 'account_id' => 'owner', 'item_code' => $group.':'.$index,
                'reason' => 'Standard wing commissioning', 'idempotency_key' => (string) Str::uuid(),
            ])->assertRedirect()->assertSessionHasNoErrors();

        $binary = substr($game->table('warehouse')->value('Items'), 0, 25);
        $item = $this->app->make(ItemCodec::class)->decode($binary, 1)[0];
        self::assertSame($group.':'.$index, $item->code());
        self::assertSame(15, $item->level);
        self::assertTrue($item->hasLuck);
        self::assertSame($excellentMask, $item->excellentMask);
        self::assertContains($additionalLabel, array_column($item->nativePresentation['details'], 'value'));
        self::assertSame(1, ItemGrantOperation::query()->count());
    }

    /**
     * Covers every supported standard wing kind, including both cape families.
     *
     * @return array<string, array{int, int, int, string}>
     */
    public static function standardWings(): array
    {
        return [
            'first wings' => [12, 0, 0, 'Automatic HP recovery 7%'],
            'second wings' => [12, 6, 31, 'Additional Wizardry Dmg +28'],
            'third wings' => [12, 36, 15, 'Automatic HP recovery 7%'],
            'third cape' => [12, 40, 15, 'Automatic HP recovery 7%'],
            'cape damage' => [12, 40, 31, 'Additional Dmg +28'],
            'cape defense' => [12, 40, 47, 'Additional defense +28'],
            'lord cape' => [13, 30, 15, 'Additional Dmg +28'],
            'fighter cape' => [12, 49, 7, 'Automatic HP recovery 7%'],
        ];
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
     * Supplies wing and curse-book choices while retaining Bound item restrictions in both editors.
     */
    public function test_native_editors_include_wing_excellent_labels_and_construction_availability(): void
    {
        $catalog = $this->app->make(ItemCatalog::class);
        $cape = $catalog->definitionFor(12, 40)->nativeOptions;
        $boundCape = $catalog->definitionFor(12, 434)->nativeOptions;

        self::assertSame([1, 2, 4, 8], array_column($cape['excellent'], 'bit'));
        self::assertSame('Increase chance of True Damage by 5%', $cape['excellent'][0]['label']);
        self::assertSame($cape['excellent'], $boundCape['excellent']);
        self::assertTrue($cape['constructionSupported']);
        self::assertFalse($boundCape['constructionSupported']);
        self::assertTrue($catalog->definitionFor(0, 0)->nativeOptions['constructionSupported']);
        self::assertCount(6, $catalog->definitionFor(5, 21)->nativeOptions['excellent']);

        $administrator = Administrator::factory()->create();

        foreach (['admin.shop-offers.create', 'admin.item-grants.index'] as $route) {
            $this->actingAs($administrator, 'admin')->get(route($route))
                ->assertOk()
                ->assertSee('Creating and delivering it is not supported')
                ->assertViewHas('definitionsByCode', static function (array $definitions) use ($cape): bool {
                    return $definitions['12:40']['nativeOptions'] === $cape;
                });
        }
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
