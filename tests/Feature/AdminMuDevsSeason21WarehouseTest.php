<?php

namespace Tests\Feature;

use App\Admin\Authorization\AdministratorPermission;
use App\Models\Administrator;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permissioned S21 warehouse previews and safe read-only native presentation.
 */
final class AdminMuDevsSeason21WarehouseTest extends TestCase
{
    use LazilyRefreshDatabase;

    /**
     * Enables only the isolated preview feature for these endpoint checks.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.s21_warehouse_preview_enabled', true);
    }

    /**
     * Redirects unauthenticated users without reading a native warehouse.
     */
    public function test_guest_is_redirected_to_administrator_login(): void
    {
        $this->get(route('admin.s21-warehouse'))->assertRedirect(route('admin.login'));
    }

    /**
     * Refuses staff without settings permission before accessing the game connection.
     */
    public function test_staff_without_settings_permission_cannot_inspect_storage(): void
    {
        $administrator = Administrator::factory()->gameMaster([AdministratorPermission::ManageContent])->create();

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse'))->assertForbidden();
    }

    /**
     * Keeps the commissioning endpoint unavailable unless explicitly enabled.
     */
    public function test_disabled_preview_returns_404_before_reading_native_tables(): void
    {
        $administrator = Administrator::factory()->create();
        config()->set('game.s21_warehouse_preview_enabled', false);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))->assertNotFound();
    }

    /**
     * Shows the initial account form without requiring a saved native warehouse.
     */
    public function test_initial_form_renders_without_querying_native_storage(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse'))
            ->assertOk()
            ->assertSee('S21 warehouse preview')
            ->assertViewHas('hasStorage', false);
    }

    /**
     * Renders native geometry, names and fallback artwork without leaking raw serial bytes.
     */
    public function test_saved_items_render_with_exact_geometry_and_verified_details(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixtures = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-warehouse-items.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = str_repeat("\xFF", 6000);

        foreach ($fixtures as $fixture) {
            $binary = substr_replace($binary, hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        }

        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $response = $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']));

        $response->assertOk()
            ->assertSee('Brilliant Knight Helm [S] +15')
            ->assertSee("Warrior's Leather Armor +9")
            ->assertSee('/images/items/mudevs_season_21/flameberge.png')
            ->assertSee('/images/items/mudevs_season_21/dragon-knight-armor.png')
            ->assertSee('images/items/unknown-item.svg')
            ->assertSee('width: calc(var(--inventory-cell-size) * 2 - 0.36rem)', false)
            ->assertSee('height: calc(var(--inventory-cell-size) * 4 - 0.36rem)', false)
            ->assertSee('Socket 3')
            ->assertSee('Socket bonus code')
            ->assertDontSee('Socket 4')
            ->assertDontSee('nativeHex')
            ->assertDontSee($fixtures[0]['hex']);
        $this->assertSame($binary, $connection->table('warehouse')->where('AccountID', 'owner')->value('Items'));
    }

    /**
     * Honors the requested account and exact extended-vault number.
     */
    public function test_preview_does_not_substitute_another_accounts_extended_vault(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $connection->table('ExtWarehouse')->insert([
            'AccountID' => 'other', 'AccountIndex' => 2, 'Number' => 1, 'Items' => str_repeat("\xFF", 6000),
        ]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner', 'vault' => 1]))
            ->assertOk()
            ->assertSee('No saved warehouse matches this account and vault number.')
            ->assertViewHas('hasStorage', false);
    }

    /**
     * Reports a malformed blob through a localized status without exposing native internals.
     */
    public function test_invalid_native_blob_has_a_readable_error(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => 'bad']);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('This warehouse cannot be displayed: its native item data is incomplete or has an unsupported length.')
            ->assertViewHas('hasStorage', false);
    }

    /**
     * Rejects account markup instead of passing it to storage or rendered form fields.
     */
    public function test_account_markup_is_rejected_with_a_validation_message(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')->getJson(route('admin.s21-warehouse', ['account' => '<x>']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.account.0', 'The account field format is invalid.');
    }

    /**
     * Rejects negative page identifiers before selecting a native table.
     */
    public function test_negative_vault_is_rejected_with_a_validation_message(): void
    {
        $administrator = Administrator::factory()->create();

        $this->actingAs($administrator, 'admin')->getJson(route('admin.s21-warehouse', ['account' => 'owner', 'vault' => -1]))
            ->assertUnprocessable()
            ->assertJsonPath('errors.vault.0', 'The vault field must be between 0 and 2147483647.');
    }

    /**
     * Displays a standalone seed sphere with its source tier and no invented weapon socket fields.
     */
    public function test_standalone_fire_seed_sphere_is_named_by_its_native_identity(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-fire-seed-sphere.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('Seed Sphere [Fire][Lvl. 1]')
            ->assertDontSee('Seed Sphere [Fire][Lvl. 1] +1')
            ->assertSee('images/items/unknown-item.svg')
            ->assertDontSee('Harmony option code')
            ->assertDontSee('Socket 1');
    }

    /**
     * Names a client-matched Fire seed even though its native filled value is zero.
     */
    public function test_filled_zero_seed_is_shown_with_its_verified_fire_effect(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-flameberge-fire-socket.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('Socket 1')
            ->assertSee('Level 1 Fire — Damage and wizardry damage per 20 levels +1.1')
            ->assertSee('Socket 2')
            ->assertSee('Socket 3')
            ->assertDontSee('Socket 4')
            ->assertDontSee('Unverified seed code 0');
    }

    /**
     * Keeps unverified seed codes visible without assigning a guessed element or tier.
     */
    public function test_unverified_filled_seed_keeps_its_raw_code(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-flameberge-fire-socket.json')), true, flags: JSON_THROW_ON_ERROR);
        $weapon = hex2bin($fixture['hex']);
        $weapon[11] = "\x01";
        $connection->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $weapon.str_repeat("\xFF", 5975),
        ]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('Unverified seed code 1')
            ->assertDontSee('Level 1 Fire');
    }

    /**
     * Shows both verified seed effects in order while the third socket remains empty.
     */
    public function test_fire_and_lightning_descriptions_keep_their_native_socket_order(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-flameberge-fire-lightning-sockets.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSeeInOrder([
                'Socket 1',
                'Level 1 Fire — Damage and wizardry damage per 20 levels +1.1',
                'Socket 2',
                'Level 1 Lightning — Excellent damage increase +15',
                'Socket 3',
                'Empty',
            ])
            ->assertDontSee('Socket 4')
            ->assertDontSee('Unverified seed code 29')
            ->assertDontSee('Socket bonus code');
    }

    /**
     * Shows all three client-matched effects and the automatically acquired attack bonus.
     */
    public function test_three_filled_sockets_and_zero_bonus_show_their_verified_effects(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-flameberge-three-seeds-bonus.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSeeInOrder([
                'Socket 1',
                'Level 1 Fire — Damage and wizardry damage per 20 levels +1.1',
                'Socket 2',
                'Level 1 Lightning — Excellent damage increase +15',
                'Socket 3',
                'Level 1 Ice — Life increase on monster destruction +250',
                'Socket bonus code',
                'Attack increase +11',
            ])
            ->assertDontSee('Socket 4')
            ->assertDontSee('Unverified seed code');
    }

    /**
     * Presents tier seven from the matched shared-header context without a false ancient label.
     */
    public function test_extended_fire_socket_shows_tier_seven_without_false_ancient_fields(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-cyclone-fire-tier-seven.json')), true, flags: JSON_THROW_ON_ERROR);
        $binary = substr_replace(str_repeat("\xFF", 6000), hex2bin($fixture['hex']), $fixture['slot'] * 25, 25);
        $connection->table('warehouse')->insert(['AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $binary]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('Cyclone Sword')
            ->assertSee('Level 7 Fire — Damage and wizardry damage per 20 levels +1.7')
            ->assertSee('Socket header flags')
            ->assertDontSee('Ancient option code')
            ->assertDontSee('Ancient set')
            ->assertDontSee('Level 1 Fire');
    }

    /**
     * Keeps unknown extension tails unverified instead of applying the captured grade-seven label.
     */
    public function test_unknown_extension_tail_does_not_acquire_a_verified_seed_description(): void
    {
        $administrator = Administrator::factory()->create();
        $connection = $this->createGameSchema();
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-cyclone-fire-tier-seven.json')), true, flags: JSON_THROW_ON_ERROR);
        $weapon = hex2bin($fixture['hex']);
        $weapon[16] = "\x00";
        $connection->table('warehouse')->insert([
            'AccountID' => 'owner', 'AccountIndex' => 1, 'Items' => $weapon.str_repeat("\xFF", 5975),
        ]);

        $this->actingAs($administrator, 'admin')->get(route('admin.s21-warehouse', ['account' => 'owner']))
            ->assertOk()
            ->assertSee('Unverified seed code 46')
            ->assertDontSee('Level 7 Fire')
            ->assertDontSee('Level 1 Fire');
    }

    /**
     * Creates only the sanitized native projection on the isolated game connection.
     */
    private function createGameSchema(): ConnectionInterface
    {
        $connection = app(DatabaseManager::class)->connection('game');
        $connection->unprepared(file_get_contents(base_path('tests/Fixtures/SqlServer/mudevs-season-21-read-schema.sql')));

        return $connection;
    }
}
