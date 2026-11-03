<?php

namespace Tests\Feature;

use App\Admin\Audit\AuditLogReader;
use App\Admin\Authorization\AdministratorPermission;
use App\Game\Contracts\ItemEscrowRepository;
use App\Game\Contracts\ItemSerialRepository;
use App\Game\Contracts\ModerationRepository;
use App\Game\Operations\CharacterOperationStatus;
use App\Game\Versions\Version97d99i\ItemCodec97d99i;
use App\Market\Exceptions\MarketListingRejected;
use App\Market\MarketEscrowResult;
use App\Market\MarketListingBlockReason;
use App\Models\Administrator;
use App\Models\ItemGrantOperation;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Tests\TestCase;

/**
 * Verifies permissioned, idempotent and audited administrator item grants.
 */
final class AdminItemGrantTest extends TestCase
{
    use LazilyRefreshDatabase;

    /** Next documentation-range IP suffix used to isolate named limiter state. */
    private static int $ipSuffix = 10;

    /**
     * Assigns each test a deterministic IP so limiter state cannot leak by order.
     */
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('game.writes_enabled', false);
        config()->set('database.connections.game.readonly', true);
        $this->withServerVariables([
            'REMOTE_ADDR' => '198.51.100.'.self::$ipSuffix,
        ]);
        self::$ipSuffix++;
    }

    /**
     * Confirms a Game Master without the item capability cannot access the tool.
     */
    public function test_game_master_without_item_permission_is_forbidden(): void
    {
        $gameMaster = Administrator::factory()
            ->gameMaster([AdministratorPermission::ModerateCharacters])
            ->create();

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.item-grants.index'));

        $response->assertForbidden();
    }

    /**
     * Confirms an authorized Game Master sees the catalog while writes stay closed.
     */
    public function test_authorized_game_master_can_review_safe_item_grant_page(): void
    {
        $gameMaster = $this->itemGrantAdministrator();
        $this->mock(ModerationRepository::class)
            ->shouldReceive('searchAccounts')
            ->once()
            ->with(null, 25)
            ->andReturn([]);

        $response = $this
            ->actingAs($gameMaster, 'admin')
            ->get(route('admin.item-grants.index'));

        $response
            ->assertOk()
            ->assertSee('Warehouse item grants')
            ->assertSee('Kris')
            ->assertSee('Game writes are disabled');
    }

    /**
     * Confirms disabled game writes reject before a serial or audit row is created.
     */
    public function test_disabled_writes_reject_without_reserving_serial(): void
    {
        $administrator = $this->itemGrantAdministrator();
        $this->mock(ItemSerialRepository::class)
            ->shouldNotReceive('nextSerial');
        $csrfToken = 'disabled-item-grant-token';

        $response = $this
            ->from(route('admin.item-grants.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
            ]);

        $response
            ->assertRedirect(route('admin.item-grants.index'))
            ->assertSessionHasErrors('item_grant');
        $this->assertSame(0, ItemGrantOperation::query()->count());
    }

    /**
     * Confirms one valid grant uses one server serial and remains idempotent.
     */
    public function test_valid_grant_delivers_exact_item_once_and_records_audit(): void
    {
        $this->enableGameWrites();
        $administrator = $this->itemGrantAdministrator();
        $this->mock(ItemSerialRepository::class)
            ->shouldReceive('nextSerial')
            ->once()
            ->andReturn(123456);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->withArgs(static function (
                string $accountId,
                string $itemBinary,
                int $width,
                int $height,
            ): bool {
                return $accountId === 'owner05'
                    && strlen($itemBinary) === 10
                    && $width === 1
                    && $height === 2;
            })
            ->andReturn(MarketEscrowResult::Deposited);
        $csrfToken = 'valid-item-grant-token';
        $payload = [
            '_token' => $csrfToken,
            ...$this->validPayload(),
        ];

        $firstResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), $payload);
        $secondResponse = $this
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), $payload);

        $firstResponse
            ->assertRedirect(route('admin.item-grants.index', ['search' => 'owner05']))
            ->assertSessionHas('status');
        $secondResponse->assertRedirect(route('admin.item-grants.index', ['search' => 'owner05']));

        $operation = ItemGrantOperation::query()->sole();
        $decodedItem = $this->app
            ->make(ItemCodec97d99i::class)
            ->decode($operation->item_binary, 1)[0];

        $this->assertSame(CharacterOperationStatus::Completed, $operation->status);
        $this->assertSame(123456, $operation->serial);
        $this->assertSame('0001E240', $decodedItem->serial);
        $this->assertTrue($operation->after_snapshot['delivered']);

        $auditEntries = $this->app->make(AuditLogReader::class)->latest(type: 'item_grant');

        $this->assertCount(1, $auditEntries);
        $this->assertSame('owner05', $auditEntries[0]['subject']);
    }

    /**
     * Confirms an unknown but structurally valid catalog code never reserves a serial.
     */
    public function test_unknown_catalog_item_is_rejected_before_serial_reservation(): void
    {
        $this->enableGameWrites();
        $administrator = $this->itemGrantAdministrator();
        $this->mock(ItemSerialRepository::class)
            ->shouldNotReceive('nextSerial');
        $csrfToken = 'unknown-item-grant-token';

        $response = $this
            ->from(route('admin.item-grants.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
                'item_code' => '15:31',
            ]);

        $response
            ->assertRedirect(route('admin.item-grants.index'))
            ->assertSessionHasErrors('item_grant');
        $this->assertSame(0, ItemGrantOperation::query()->count());
    }

    /**
     * Confirms crafted advanced options remain closed until family rules are verified.
     */
    public function test_unverified_excellent_option_is_rejected_by_validation(): void
    {
        $this->enableGameWrites();
        $administrator = $this->itemGrantAdministrator();
        $this->mock(ItemSerialRepository::class)
            ->shouldNotReceive('nextSerial');
        $csrfToken = 'excellent-item-grant-token';

        $response = $this
            ->from(route('admin.item-grants.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
                'excellent_mask' => 1,
            ]);

        $response
            ->assertRedirect(route('admin.item-grants.index'))
            ->assertSessionHasErrors('excellent_mask');
        $this->assertSame(0, ItemGrantOperation::query()->count());
    }

    /**
     * Confirms a full warehouse produces a failed immutable operation record.
     */
    public function test_full_warehouse_records_failed_grant_without_success_snapshot(): void
    {
        $this->enableGameWrites();
        $administrator = $this->itemGrantAdministrator();
        $this->mock(ItemSerialRepository::class)
            ->shouldReceive('nextSerial')
            ->once()
            ->andReturn(654321);
        $this->mock(ItemEscrowRepository::class)
            ->shouldReceive('deposit')
            ->once()
            ->andThrow(new MarketListingRejected(MarketListingBlockReason::StorageFull));
        $csrfToken = 'full-item-grant-token';

        $response = $this
            ->from(route('admin.item-grants.index'))
            ->withSession(['_token' => $csrfToken])
            ->actingAs($administrator, 'admin')
            ->post(route('admin.item-grants.store'), [
                '_token' => $csrfToken,
                ...$this->validPayload(),
            ]);

        $response
            ->assertRedirect(route('admin.item-grants.index'))
            ->assertSessionHasErrors('item_grant');

        $operation = ItemGrantOperation::query()->sole();

        $this->assertSame(CharacterOperationStatus::Failed, $operation->status);
        $this->assertSame('warehouse_full', $operation->failure_code);
        $this->assertNull($operation->after_snapshot);
    }

    /**
     * Returns one Game Master identity with only the item-grant permission.
     */
    private function itemGrantAdministrator(): Administrator
    {
        return Administrator::factory()
            ->gameMaster([AdministratorPermission::GrantItems])
            ->create();
    }

    /**
     * Opens both explicit game write safety switches for isolated fixtures.
     */
    private function enableGameWrites(): void
    {
        config()->set('game.writes_enabled', true);
        config()->set('database.connections.game.readonly', false);
    }

    /**
     * Returns one valid basic Kris grant request.
     *
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'account_id' => 'owner05',
            'item_code' => '0:0',
            'level' => 0,
            'durability' => 255,
            'option_level' => 0,
            'excellent_mask' => 0,
            'ancient_option' => 0,
            'reason' => 'Tournament reward',
            'idempotency_key' => '13af2b4b-7397-4d50-b430-d85bd20d18df',
        ];
    }
}
